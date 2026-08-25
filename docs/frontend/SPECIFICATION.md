# Cahier des charges — Frontend Nuxt/Vue (`frontend/`)


## 1. Présentation générale

`frontend` est une **application de démonstration Nuxt** dont l'unique rôle fonctionnel est d'afficher un **widget de chat flottant** ("bulle" en bas à droite de l'écran) qui dialogue avec l'API du backend Symfony, en consommant les endpoints spécifiques d'API Platform exposés par `backend`.

Ce frontend **ne contient aucune logique métier IA** : il n'appelle pas de LLM, ne fait pas de RAG, ne gère pas de base vectorielle — toute l'intelligence (chat, RAG, tool-calling) réside côté `backend`. Le rôle de ce projet se limite à :

1. Afficher une **interface de chat** (bulle flottante + fenêtre de conversation).
2. Envoyer les messages de l'utilisateur à l'API backend (conversation persistée, réponse en streaming SSE — voir §5.1) et afficher la réponse du LLM au fil de l'eau.
3. Utiliser automatiquement l'**agent IA** actif exposé par le backend (`GET /api/ai_agents`, sélection automatique côté frontend, aucun choix laissé à l'utilisateur) — son prompt système, son RAG (collection documentaire) et ses outils (workflows) sont configurés côté backend.
4. Relayer (proxy) les appels `/api/*` du navigateur vers le backend Symfony, pour contourner les problèmes de réseau Docker / CORS.

### 1.1 Identité visuelle

Le head HTML (`nuxt.config.ts`) définit un titre **"Maxime - Chatbot IA"** et charge les polices Google Fonts *IBM Plex Sans* / *IBM Plex Mono*. Palette Tailwind en variables CSS (`assets/css/main.css`, tokens `--background`/`--foreground`/`--card`/`--muted`/`--accent`/`--primary`/`--destructive`/`--border`, triplet RGB pour supporter les modificateurs d'opacité `bg-accent/10`), claire par défaut (encre `#17151f` sur blanc cassé, accent indigo `#3a3170`) et sombre au choix du visiteur (encre devient le fond, lilas `#efebfb` devient le texte — voir `composables/useColorScheme.ts`, §4.2).

---

## 2. Stack technique

| Composant             | Technologie / version                                                                                                    | Rôle                                                                                                                             |
| --------------------- | ------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------- |
| Runtime               | **Node.js 24**                                                                                                           |                                                                                                                                  |
| Framework             | **Nuxt 4.5**                                                                                                             | SSR + routage fichier + serveur Nitro intégré                                                                                    |
| Bundler / dev server  | **Vite 8.2** (via Nuxt)                                                                                                  |                                                                                                                                  |
| UI                    | **Vue 3.5** (Composition API, `<script setup>`)                                                                          |                                                                                                                                  |
| Style                 | **`@nuxtjs/tailwindcss` 6.14** (Tailwind CSS)                                                                            | Thème custom (`tailwind.config.js`), classes utilitaires (`assets/css/main.css`)                                                 |
| Langage               | **TypeScript 7.0**                                                                                                       |                                                                                                                                  |
| HTTP client (déclaré) | **axios 1.19**                                                                                                           | Présent en dépendance mais **non utilisé dans le code actuel** — les appels réseau passent tous par `$fetch` (natif Nuxt/ofetch) |
| Emojis                | **`unicode-emoji-json` 0.9**                                                                                             | Données statiques (par groupe) pour le sélecteur d'emoji du composant `Chatbot`                                                  |
| i18n                  | **`@nuxtjs/i18n` 10.6** (vue-i18n 11)                                                                                    | Une seule locale active (`fr`) pour l'instant — infrastructure prête pour une 2ᵉ langue, voir §8.7                               |
| Tests                 | **Vitest 4** + **`@nuxt/test-utils` 4.1** + **`@vue/test-utils`** (environnement `nuxt`, `happy-dom`)                    | Tests unitaires des composables — voir §8.6                                                                                      |
| Formatage             | **Prettier 3.9** (`.prettierrc.json` : single quotes, semicolons, `printWidth: 100`)                                     |                                                                                                                                  |
| Devtools              | **`@nuxt/devtools`**                                                                                                     |                                                                                                                                  |
| Conteneurisation      | Servi comme service `nuxt` dans `backend/compose.yaml` (image `node:24-alpine`, build + `node .output/server/index.mjs`) | Pas de `Dockerfile` propre à ce projet                                                                                           |

**Aucun état global (Pinia/Vuex)** : l'état de l'ouverture du widget passe par `useState` (état Nuxt partagé SSR/client), l'état de la conversation par un simple `ref` local au composable `useChatbot`.

---

## 3. Architecture applicative

### 3.1 Arborescence

```
frontend/
├── app.vue                      # Racine de l'app — monte <ChatWidget /> en superposition plein écran
├── pages/index.vue              # Page d'accueil, vide (app.vue suffit)
├── components/
│   ├── ChatWidget.vue           # Bulle flottante + tooltip d'accroche + panneau de conversation
│   ├── Chatbot.vue              # Fenêtre de chat complète (en-tête, historique, saisie, emoji picker)
│   ├── MessageBubble.vue        # Une bulle de message (utilisateur ou assistant)
│   └── TypingIndicator.vue      # Indicateur "en train d'écrire" (3 points animés)
├── composables/
│   ├── useChatbot.ts            # Logique métier du chat : état, envoi de message, agents
│   └── useChatWidget.ts         # État partagé d'ouverture/fermeture du widget (useState)
├── server/api/[...path].ts      # Route serveur Nitro — proxy générique vers le backend Symfony
├── types/index.ts                # Types partagés (Message, AIAgent, ChatbotProps, ChatbotState)
├── assets/css/main.css           # Directives Tailwind + classes utilitaires custom
├── tailwind.config.js            # Thème "Minitel" (couleurs, polices, animations)
└── nuxt.config.ts                # Config Nuxt (head, modules, runtimeConfig, workaround Vite/TS7)
```

### 3.2 Hiérarchie des composants

```
app.vue
 └─ ChatWidget.vue          (bulle flottante, gère isOpen via useChatWidget)
     └─ Chatbot.vue         (si isOpen) — utilise useChatbot()
         ├─ MessageBubble.vue   (× N, un par message de l'historique)
         └─ TypingIndicator.vue (affiché tant que isLoading === true)
```

### 3.3 Flux de données — envoi d'un message

```
Utilisateur tape un message dans Chatbot.vue
        │
        ▼
useChatbot().sendMessage(content)
        │  1. push immédiat du message "user" dans l'état local (affichage optimiste)
        │  2. ensureConversation() -- POST /api/conversations la toute première fois
        │     (id ensuite persisté en localStorage, réutilisé par les tours suivants)
        │  3. isLoading = true
        ▼
fetch('/api/conversations/{id}/stream', { method: 'POST', body: { message, agent_id? } })
        │  fetch brut + ReadableStream (pas $fetch -- il faut lire le flux au fil de l'eau),
        │  URL relative → interceptée par le serveur Nitro de CE projet
        ▼
server/api/conversations/[id]/stream.post.ts  (route Nitro dédiée, prioritaire sur le catch-all)
        │  proxyRequest() sans buffering vers ${API_URL}/api/conversations/{id}/stream
        ▼
Backend Symfony — POST /api/conversations/{id}/stream (ConversationStreamController)
        │  persiste le message utilisateur, exécute RAG + tool-calling si un agent est sélectionné,
        │  répond en Server-Sent Events (voir le cahier des charges backend §5.5)
        ▼
Frames SSE : user_message → zéro ou plusieurs delta (+ tool_call si un outil s'exécute) → ai_complete → done
        │
        ▼
useChatbot() construit la bulle "assistant" en direct depuis les delta (tool_call met juste à
jour le libellé affiché par TypingIndicator entre-temps, voir §4.4), complète
id/sources/tool_calls/feedback depuis ai_complete, isLoading = false, auto-scroll (sauf si le
visiteur a remonté dans l'historique)
```

### 3.4 Le proxy serveur — `server/api/[...path].ts`

C'est la pièce d'infrastructure la plus importante du projet. Route Nitro **catch-all** (`[...path].ts`) qui intercepte **toute requête entrante sous `/api/*`** faite au serveur Nuxt lui-même, et la relaie vers le backend Symfony :

- URL cible reconstruite : `${API_URL}/api/<chemin capturé><?query string>`.
- Transmet la méthode HTTP, le `Content-Type: application/json`, le corps de la requête (`readBody`), et le cookie `Cookie` s'il est présent (transmis "au cas où" — le firewall `api` du backend étant stateless, aucune session n'est réellement échangée ici, voir le cahier des charges backend, §10).
- **Ajoute un en-tête `Authorization: Basic ...`** construit depuis `runtimeConfig.adminUsername`/`adminPassword` (variables serveur `ADMIN_USERNAME`/`ADMIN_PASSWORD`) : depuis que le backend exige une authentification sur `/api/*` (firewall `api`, HTTP Basic), le proxy s'authentifie comme compte de service au nom des visiteurs du widget, qui n'ont donc rien à saisir.
- **Journalise en console** chaque appel (méthode, URL d'origine, URL cible, aperçu du body et de la réponse, durée en ms) — pratique en dev, bruyant en production.
- Propage les erreurs HTTP du backend (`statusCode`, `statusMessage`, `data`) via `createError()`.

Pourquoi ce proxy existe : en environnement Docker (`backend/compose.yaml`), le navigateur de l'utilisateur ne peut pas résoudre `chatbot-symfony` (nom de conteneur, valable uniquement sur le réseau Docker interne). Le **serveur** Nuxt (Nitro), lui, tourne sur ce même réseau et peut y accéder. En passant par des URLs relatives (`/api/...`) côté client, les appels sont toujours faits vers *le même hôte que la page*, puis c'est le serveur Nitro qui, côté serveur (où `chatbot-symfony` est résolvable), relaie vers le vrai backend.

Les agents étant exposés par API Platform sous `/api/ai_agents` (hors du préfixe `chat`), le proxy relaie **tout `/api/*`** qui passe l'allowlist ci-dessous, sans distinction de préfixe.

> [!CAUTION]
> **Allowlist ajoutée suite à un audit de sécurité** — avant, ce proxy relayait *littéralement n'importe quel* `/api/{...path}` vers le backend, toujours avec les vraies credentials admin (le point précédent). Comme le backend traite "authentifié en `ROLE_ADMIN`" comme "c'est vraiment l'opérateur admin" (voir `OwnershipVoter`, cahier des charges backend §10), n'importe quel visiteur pouvait atteindre des ressources admin-only via ce même proxy : confirmé en conditions réelles, `GET /api/conversations` (75 conversations, noms + messages complets) et `GET /api/workflow_executions` (91 exécutions, emails de recruteurs) répondaient `200` sans aucun credential. Le proxy applique désormais une **allowlist stricte en début de handler** (`ALLOWED_ROUTES`, méthode + regex de chemin) : seule une dizaine de routes précises (celles listées en §7.2) sont relayées ; tout le reste reçoit un `404` avant même d'appeler le backend. Vérifié : les 4 endpoints ci-dessus (et `/api/workflows/{id}/steps`, `/api/documents`) renvoient bien `404` via ce proxy depuis le fix ; les chemins légitimes du widget (création de conversation, lecture de ses messages, feedback, faqs, ai_agents, llm-status, health) restent `200`. Une dizaine de tentatives de contournement testées (slash final, casse, `../`, verbe invalide, query string) — aucune n'a fonctionné. Toute nouvelle route backend que le widget doit consommer doit être ajoutée explicitement à `ALLOWED_ROUTES`, jamais supposée passer par défaut.

---

## 4. Composants

### 4.1 `StickyChatBubble.vue` — bulle flottante

Montée uniquement sur `pages/index.vue` (`pages/chat.vue` n'en a pas besoin, le chat y occupe déjà toute la page) ; `isOpen` est un simple `ref` local, pas d'état partagé — rien d'autre n'a besoin de le lire.

- Positionnée en `fixed bottom-6 right-6` (coin bas-droit), z-index élevé.
- **Bouton bulle** (icône bulle de dialogue / croix selon `isOpen`), avec un anneau pulsant (`animate-pulse-ring`, `motion-reduce:animate-none`) **seulement lors de la toute première visite** (`showFirstVisitBadge`, persisté via `chatbot:bubble_seen` en `localStorage` — disparaît pour de bon dès la première interaction, sur n'importe quel onglet/visite ultérieure) pour attirer l'œil.
- **Tooltip d'accroche** ("Commencer la conversation") révélé au survol/focus (CSS `group-hover`/`group-focus-within`, pas de minuterie JS), uniquement tant que `!isOpen`.
- Au clic (`onBubbleClick`) : si une conversation existe déjà (`CONVERSATION_ID_STORAGE_KEY` en `localStorage`, posé par `useChatbot`), redirige vers `/chat` pour la reprendre là plutôt que de rouvrir un second fil dans le popin ; sinon bascule `isOpen`.
- **Raccourci `Cmd`/`Ctrl+K`** : ouvre/ferme le widget depuis n'importe où sur la page (même logique que le clic sur la bulle, `onBubbleClick` appelé directement) — écouteur `window` posé à `onMounted`/retiré à `onBeforeUnmount`. Distinct du raccourci `/` de `Chatbot.vue` (§4.2), qui ne fait que redonner le focus une fois le panneau déjà ouvert.
- Rendu conditionnel de `<Chatbot variant="widget" />` uniquement quand `isOpen === true`, avec transitions Vue (`<Transition>`, fade + scale/translate).

### 4.2 `Chatbot.vue` — fenêtre de conversation

Composant principal, réutilisable indépendamment du widget flottant (documenté comme tel dans le `README.md`, utilisable directement avec des props `title`/`theme`/`api-url`/`placeholder`/`show-close`).

Sections :
1. **En-tête** : avatar (icône bulle), titre, statut "En ligne" (pastille verte statique — pas de vérification réelle de disponibilité du backend), boutons *effacer la conversation*, *couper/activer le son des notifications* (`soundMuted`/`toggleSoundMuted`, voir §5.1 — persisté en `localStorage`, même schéma que le thème), *plein écran* (`isFullscreen`, passe en `fixed inset-4`), *fermer* (si `showClose`).
2. **Zone de messages** : liste de `MessageBubble`, placeholder "Commencez la conversation" si vide, `TypingIndicator` pendant le chargement, ancre de scroll automatique — sauf si le visiteur a remonté dans l'historique : `onMessagesScroll` (`@scroll` sur le conteneur) désactive `autoScroll` (ref exposée par `useChatbot`, voir §5.1) dès que la distance au bas dépasse 48px, ce qui rend `scrollToBottom()` sans effet ; un `watch` profond sur `messages` détecte alors une réponse arrivée pendant ce temps et affiche une pastille flottante "Nouveau message" (`hasNewMessage`) plutôt que de forcer le défilement. `autoScroll` est remis à `true` par `useChatbot` lui-même dès qu'un envoi/regénération est déclenché (action explicite du visiteur), et par le scroll manuel du visiteur jusqu'en bas. Le même `onMessagesScroll` pilote aussi un bouton symétrique "remonter en haut" (`showScrollToTop`, au-delà de 400px depuis le haut, `jumpToTop()` fait un `scrollTo({ top: 0, behavior: 'smooth' })`). Pendant la restauration d'une conversation précédente (`isRestoringHistory`, voir §5.1), affiche un skeleton (3 bulles `animate-pulse`) plutôt qu'un écran vide qui se remplit d'un coup. `messageItems` (computed) insère un séparateur de date ("Aujourd'hui"/"Hier"/date complète) à chaque changement de jour et resserre l'espacement (`isGrouped`, voir §4.3) entre deux messages consécutifs du même rôle — chaque séparateur est `position: sticky` (décalé sous la barre de navigation collante en variante `page`, `top-0` en widget qui n'en a pas) et reste affiché en haut du défilement jusqu'au suivant, façon WhatsApp/Telegram.
3. **Bandeau d'erreur** : affiché si `useChatbot().error` est renseigné.
4. **Formulaire de saisie** : `<textarea>` auto-agrandissant (`resizeTextarea`, jusqu'à 120px puis défilement interne, remis à une ligne quand `inputValue` se vide) plutôt qu'un `<input>` à une ligne — coller un extrait de code ou écrire un message sur plusieurs lignes ne fait plus défiler le texte horizontalement. Bouton d'envoi (spinner pendant `isLoading`), **sélecteur d'emoji** custom (recherche + groupes, données de `unicode-emoji-json`) insérant l'emoji choisi dans le champ — navigable au clavier (flèches dans la grille, `ArrowDown`/`Enter` depuis la recherche pour y entrer directement) via un roving tabindex (`focusedEmojiIndex`, un seul bouton dans l'ordre de tabulation à la fois, vrai focus DOM déplacé par `.focus()` plutôt qu'un simple surlignage CSS — Entrée/Espace déclenchent alors le `@click` du bouton nativement). Compteur de caractères discret (`showCharCount`) : masqué en dessous de 500 caractères, aucun maximum imposé — purement informatif pour un message qui commence à être long.

**Astuce de découverte** : bandeau discret ("💡 Tape `/` pour les commandes rapides…, ou Cmd/Ctrl+K…") affiché une seule fois, tous visiteurs/sessions confondus (`localStorage`, clé `chatbot:hint_seen`), après la toute première réponse assistant reçue via `onMessage` — le visiteur est déjà engagé à ce moment-là, contrairement à l'ouverture du panneau où rien ne s'est encore passé. Se referme manuellement, automatiquement après 8s, ou dès que le visiteur découvre `/` par lui-même (`watch` sur `showSlashMenu`).

**Commande `/cv`** (remplace une carte de contact `.vcf` retirée depuis) : ouvre le vrai CV en ligne de Maxime (`https://www.maxime.bzh/cv-...pdf`) dans un nouvel onglet — lien direct vers son propre site plutôt qu'une copie re-hébergée ou générée, pour ne jamais devenir obsolète si le PDF change. La base de connaissances RAG contient bien un document "CV" mais c'est une extraction `.txt` pensée pour l'indexation, pas un fichier présentable à un visiteur.

Pas de sélecteur d'agent dans l'UI : l'agent est choisi **automatiquement** par `useChatbot` (voir §5.1) plutôt que par l'utilisateur — un choix délibéré pour un widget mono-agent (voir §1).

**Raccourcis clavier** : Entrée envoie le message (`onInputKeydown`, `e.preventDefault()` + `sendMessage()` — un `<textarea>` ne soumet jamais son formulaire sur Entrée, contrairement à l'ancien `<input type="text">` à une ligne), Maj+Entrée insère un saut de ligne. Échap ferme la couche la plus au premier plan, dans l'ordre : sélecteur d'emoji ouvert → mode plein écran → sinon, si une génération est en cours (`isLoading`), l'annule (`cancelReply()` de `useChatbot`, `AbortController` sur le `fetch` du flux SSE — voir §5.1). `/` ramène le focus dans le champ de saisie depuis n'importe où dans la page (`isTypingTarget` évite de voler un `/` tapé légitimement dans un champ déjà actif — le message, la recherche d'emoji…). Écouteurs posés sur `window` (Échap, `/`) à `onMounted`/retirés à `onBeforeUnmount`. Le champ reçoit aussi le focus automatiquement au montage (widget ouvert ou page `/chat` chargée) et après chaque envoi (`focusInput()`, y compris via clic sur le bouton d'envoi — Entrée ne perd jamais le focus par elle-même).

Mode sombre activable par le visiteur (bouton lune/soleil dans l'en-tête) — classe CSS `dark` (Tailwind `darkMode: 'class'`) posée sur le conteneur racine selon `composables/useColorScheme.ts` : choix explicite du visiteur (`localStorage`) > `prefers-color-scheme` OS > prop `theme` (simple valeur de repli désormais, ne force plus rien). Palette claire/sombre en variables CSS (`assets/css/main.css` `:root`/`.dark`), voir `docs/BACKLOG.md` pour le détail des tokens.

**Notification desktop en arrière-plan** : complète le son (voir plus haut) pour le cas où le visiteur a changé d'onglet et est muet. Permission demandée au plus une fois par montage, uniquement depuis `sendMessage()` (un geste utilisateur, requis par la plupart des navigateurs) et seulement si elle n'a jamais été tranchée (`Notification.permission === 'default'`). Une fois accordée, chaque réponse déclenche une `Notification` **seulement si `document.hidden`** — jamais si l'onglet est déjà au premier plan. Clic sur la notification : `window.focus()` + fermeture.

**Toast "connexion rétablie"** : `useOnlineStatus()` (via `useChatbot`, voir §5.1) signalait déjà le passage hors ligne (bandeau d'erreur "Vous êtes hors ligne"), mais rien ne confirmait le retour. Un `watch` sur `isOnline` détecte spécifiquement la transition `false → true` (jamais au montage, `watch` non-`immediate`) et affiche un toast pendant 3s.

**`prefers-reduced-motion`** : chaque animation en boucle (`animate-bounce-slow` des indicateurs de frappe, `animate-blink` du curseur de streaming, `animate-pulse-dot` des pastilles de statut/écoute, `animate-pulse-ring` du badge de première visite sur `StickyChatBubble.vue`, `animate-pulse` des skeletons — plus `animate-aura-drift` sur le fond des pages `/` et `/chat`, déjà couvert avant ce passage) porte la variante Tailwind `motion-reduce:animate-none` : coupée net (`animation-name: none`, l'élément reste visible dans son état de base — pas de `display:none`) pour un visiteur qui a demandé moins de mouvement au niveau OS. `animate-spin` (spinners de chargement) est volontairement épargné : il porte une information fonctionnelle (une opération est en cours), pas une décoration. Les transitions Vue (`<Transition>` sur le sélecteur d'emoji, la pastille "nouveau message"…) et les `transition-*` déclenchées par une interaction (survol, focus) ne sont pas concernées — mouvement bref et localisé, pas la catégorie visée par cette préférence. Vérifié en conditions réelles (Chrome headless, `emulateMediaFeatures`) : `animation-name` bien `none` sur le skeleton de restauration d'historique sous `prefers-reduced-motion: reduce`, `aura-drift` bien présent sans cette préférence.

**Workaround technique notable** (documenté dans le README et `nuxt.config.ts`) : sous TypeScript 7, `@vue/compiler-sfc` échoue à résoudre les props typées (`defineProps<ChatbotProps>()`) car il ne détecte plus l'environnement Node — corrigé en injectant manuellement le module `fs` de Node dans la config Vite (`vite.vue.script.fs`).

### 4.3 `MessageBubble.vue`

Affiche un message unique, alignement à droite/bleu pour l'utilisateur, à gauche/gris (avatar bulle) pour l'assistant. Gère un état `isTyping` (3 points animés à la place du contenu — actuellement non déclenché par `useChatbot`, qui affiche plutôt `TypingIndicator` séparément). Horodatage formaté en `fr-FR` (`HH:mm`). Prop `isGrouped` (calculée par `Chatbot.vue`, voir §4.2) : resserre la marge au-dessus de la bulle (`mb-1` au lieu de `mb-3`) quand le message précédent est du même rôle, sans toucher au reste (avatar/horodatage/actions restent affichés sur chaque bulle).

**Actions au survol** (écouter/copier/feedback/régénérer) : masquées par défaut à partir de `sm:` (`opacity-0`), révélées par `group-hover`/`group-focus-within` sur la bulle (classe `group`) — décharge visuellement le fil sans les retirer de l'arbre d'accessibilité (`opacity` reste focusable au clavier, contrairement à `hidden`/`display:none`). En dessous de `sm:` (tactile, pas de vrai survol) elles restent visibles en permanence, aucune régression. Le bouton copier et les deux boutons feedback ont chacun une exception qui force `opacity-100` même hors survol : la confirmation "Copié !" (`copied`, 1.5s) et un feedback déjà actif (`message.feedback === 'positive'/'negative'`) — un état que le visiteur a lui-même posé ne doit pas disparaître simplement parce que la souris a quitté la bulle.

**Copier un bloc de code** : le rendu markdown passe par `v-html` (aucun gestionnaire Vue possible sur un élément injecté ainsi, et DOMPurify retire de toute façon les attributs `on*`). `marked`'s reçoit un `Renderer` custom dont seule la méthode `code` est surchargée : elle appelle le renderer par défaut pour obtenir le `<pre><code>` correctement échappé, puis l'enveloppe dans un `<div class="code-block-wrapper">` avec un bouton "copier" superposé (visible en permanence sous `sm:`, révélé au survol du bloc au-delà — `group/code`, un groupe Tailwind nommé, indépendant du `group` de la bulle). Un seul `@click` délégué sur le conteneur `v-html` (`onContentClick`) route vers le bon bouton via `closest('.code-copy-button')`, lit le texte exact depuis le `<code>` voisin (pas de duplication dans un data-attribute) et anime l'icône (coché 1,5s) directement en manipulant le DOM du bouton — imperatif, puisqu'il vit hors de l'arbre réactif de Vue.

**Tableaux markdown défilants** : même technique de `Renderer` custom, méthode `table` cette fois — enveloppe le `<table>` déjà rendu dans un `<div class="overflow-x-auto">` (un tableau plus large que la bulle, `max-w-[80%]`, débordait sinon franchement ; `display: block` sur `<table>` casserait l'algorithme de mise en page tabulaire, d'où le wrapper plutôt qu'une classe directe sur l'élément). Piège rencontré : contrairement à `code()`, `table()` parse le markdown inline de chaque cellule (gras, liens...) via `this.parser.parseInline()` — le `Renderer` par défaut instancié séparément (`defaultRenderer`, réutilisé aussi par `code()`) n'a jamais son `.parser` positionné par `marked` (seul le renderer réellement actif via `marked.setOptions` l'obtient), donc chaque tableau plantait (`Cannot read properties of undefined (reading 'parseInline')`) jusqu'à copier `renderer.parser` sur `defaultRenderer.parser` juste avant l'appel.

### 4.4 `TypingIndicator.vue`

Indicateur "en train d'écrire" façon Messenger (avatar + 3 points qui rebondissent en cascade, `animate-bounce-slow` avec délais échelonnés). Affiché entre le dernier message et le champ de saisie tant que `isLoading === true`.

**Prop `label` optionnelle** : affiche une phrase de progression à côté des points plutôt qu'un silence pendant le chemin bufferisé du tool-calling (aucun `delta` n'arrive tant qu'un outil s'exécute, voir le cahier des charges backend §5.5). Alimentée par `useChatbot().toolCallLabel` (computed) : la frame SSE `type: tool_call` (backend §5.5) transmet le nom interne de l'outil (snake_case), traduit vers un libellé français convivial via une petite table de correspondance connue (`planifier_entretien`, `lister_creneaux_disponibles`, `enregistrer_identite`), avec repli générique ("Traitement en cours…") pour tout nom non reconnu — jamais le nom brut affiché, même logique "curatée" que le reste des tool calls dans `MessageBubble.vue`. Réinitialisé au premier `delta` (le nom n'a alors plus d'utilité) et à chaque nouvel envoi.

### 4.5 `LinkPreviewCard.vue`

Carte d'aperçu (favicon + titre + domaine) affichée sous un message qui contient un lien — le lien lui-même reste cliquable dans le texte, la carte est un complément en dessous (même schéma que Slack/Discord), pas un remplacement. `MessageBubble.vue` extrait jusqu'à 3 URLs `http(s)://` uniques par simple regex sur `formattedContent` (le HTML déjà sanitizé, pas de `DOMParser`/`document` — ce computed doit pouvoir tourner côté serveur aussi) ; rien n'est extrait tant que `isStreaming` est vrai, un lien encore en train de s'écrire n'est pas exploitable. Chaque carte s'appuie sur `GET /api/link-preview` (§7.1) pour récupérer titre/favicon, chargé au montage (`onMounted`, pas de blocage SSR) ; ne s'affiche pas du tout si l'aller-retour échoue ou si la page cible n'a pas de titre exploitable — pas de carte cassée/vide.

**Aperçu d'image** : si le lien pointe directement sur une image (le serveur tranche sur le vrai `Content-Type` de la réponse, pas sur l'extension de l'URL — plus fiable), la carte affiche l'image elle-même (`imageDataUri`, jusqu'à 3 Mo) plutôt qu'une carte titre/favicon — un seul champ de la réponse `/api/link-preview` change de forme, `LinkPreviewCard.vue` bascule son rendu (`isImage`) en conséquence.

---

## 5. Composables (logique métier)

### 5.1 `useChatbot(options)` — cœur fonctionnel du chat

Composable Vue exposant tout l'état et les actions nécessaires à un composant `Chatbot` :

> [!NOTE]
> Cette section décrivait jusqu'ici une version bien plus ancienne du composable (mode `quick-send` anonyme uniquement, pas de persistance, pas de streaming) — corrigée ci-dessous pour refléter l'état réel. `useChatbot.ts` a beaucoup grossi depuis (~30 exports) ; le détail fin de chaque fonctionnalité (épinglage, commandes slash, séparateurs de date, export, aperçus de liens…) vit dans `docs/BACKLOG.md`, pas ici — cette section ne couvre que l'architecture générale.

**État interne** (`ChatbotState`, `useState` partagé — survit à une navigation entre `/` et `/chat` sans perdre le fil) : `messages[]`, `isLoading`, `inputValue`, `error`, `selectedAgentId`, `agents[]`. Le `conversation_id` réel vit séparément (autre `useState`), persisté en `localStorage` (`CONVERSATION_ID_STORAGE_KEY`) pour survivre à un rechargement de page.

**Persistance et streaming réels** : le premier `sendMessage()` crée une vraie `Conversation` côté backend (`POST /api/conversations`), puis chaque tour de parole passe par `POST /api/conversations/{id}/stream` (SSE réel — `fetch` + `ReadableStream`, pas `$fetch`, pour pouvoir lire le flux au fil de l'eau) plutôt que par `/api/chat/quick-send` (voir §7.2 : cet endpoint existe toujours côté backend mais n'est plus appelé par ce widget). Au montage, `restoreConversation()` récupère l'historique complet (`GET /api/conversations/{id}/messages`) si un `conversation_id` existe déjà en `localStorage`.

**Principales actions exposées** (liste non exhaustive — voir le fichier source pour le reste) :
| Fonction                                | Rôle                                                                                                                          |
| ---------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------- |
| `sendMessage(content)`                   | Push optimiste du message utilisateur, puis délègue à `requestAssistantReply()`                                                  |
| `requestAssistantReply(content)`         | Lit le flux SSE de `/stream`, construit la bulle assistant en direct depuis les frames `delta`, complète depuis `ai_complete`    |
| `retryLastMessage()` / `regenerateLastReply()` | Rejouent le dernier tour sans dupliquer la bulle utilisateur (retry) ou en remplaçant la dernière réponse (regenerate)      |
| `cancelReply()`                          | Abandonne la requête en cours (`AbortController`), lié à Échap                                                                    |
| `restoreConversation()`                  | `GET /api/conversations/{id}/messages`, appelé au montage si un id est en `localStorage`                                         |
| `setFeedback(messageId, feedback)`       | `PATCH .../feedback`, appliqué de façon optimiste avec rollback si la requête échoue                                             |
| `exportConversation()` / `openCV()`      | Export Markdown local (`Blob`) / ouverture du vrai CV en ligne dans un nouvel onglet (voir §4.2)                                  |
| `handleSubmit(event)` / `handleInputChange(event)` | Wrappers de formulaire                                                                                                  |
| `clearMessages()`                        | Vide l'historique local **et** oublie le `conversation_id` (`localStorage`) — un nouveau message recréera une conversation neuve |
| `setSelectedAgent(agentId)` / `fetchAgents()` | Changement d'agent (voir sélection automatique ci-dessous) / `GET /api/ai_agents`, appelé à `onMounted`                     |

**Gestion des agents** : le backend renvoie une collection **JSON-LD Hydra** (`{ member: [...] }`, convention API Platform) où le champ booléen `AiAgent.isActive` est sérialisé `active` (convention Symfony pour les getters `is*`). Le composable **déballe** `.member`, **renomme** `active` → `is_active`, puis **sélectionne automatiquement** le premier agent actif (`agents.find(a => a.is_active)`) comme `selectedAgentId` — sans intervention de l'utilisateur. Avec un seul agent actif côté backend (cas courant), c'est équivalent à un widget mono-agent fixe.

### 5.2 État d'ouverture du widget

Pas de composable dédié : `isOpen` est un `ref` local à `StickyChatBubble.vue` (§4.1), le seul endroit qui en a besoin — rien d'autre dans l'app ne lit ni ne pilote l'état d'ouverture du widget flottant depuis l'extérieur.

---

## 6. Intégration avec les fonctionnalités LLM / RAG du backend

Ce frontend **ne met en œuvre aucune fonctionnalité LLM ou RAG lui-même** — il ne fait qu'exposer, via son UI, les capacités déjà orchestrées côté `backend`. Concrètement :

| Fonctionnalité (implémentée côté backend)                                            | Ce que fait ce frontend                                                                                                                                                                                                                                                                                                                              |
| ------------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Chat / complétion LLM** (Ollama ou endpoint OpenAI-compatible)                     | Envoie le message via `POST /api/conversations/{id}/stream`, lit la réponse en SSE (§3.3, §5.1). Rendu markdown complet (`marked` + `isomorphic-dompurify`, tableaux/blocs de code/listes...) plutôt que du texte brut, avec un effet machine à écrire qui rattrape les deltas à rythme fixe pour lisser les rafales de tokens                        |
| **Sélection d'agent IA** (prompt système, RAG, outils spécifiques par agent)         | Récupère la liste via `GET /api/ai_agents` et sélectionne **automatiquement** le premier agent actif (aucun choix laissé à l'utilisateur, pas de `<select>` dans l'UI) ; transmet son `agent_id` dans le body du message. Le frontend ne fait aucune recherche vectorielle lui-même, ne configure aucun paramètre RAG (top-k, collection, etc.) |
| **RAG (recherche documentaire contextuelle)**                                        | Totalement transparent pour ce frontend : si l'agent sélectionné a une collection documentaire liée côté backend, le contexte RAG est injecté silencieusement dans le prompt système par le backend ; le frontend reçoit bien `sources`/`sources_hidden: true` mais ne les affiche jamais (décision produit assumée, voir `docs/BACKLOG.md`)          |
| **Tool-calling (exécution de workflows)**                                            | `tool_calls` (trace des outils exécutés) n'est jamais affiché brut — mais deux workflows connus ont une UI **curatée** dans `MessageBubble.vue`, construite uniquement à partir de leurs propres arguments (jamais de la réponse de l'API tierce) : `planifier_entretien` → carte "✅ Entretien confirmé" ; `lister_creneaux_disponibles` → chips de créneaux cliquables qui renvoient directement le créneau choisi comme message. Pendant l'exécution elle-même, la frame SSE `tool_call` (backend §5.5) alimente un libellé de progression dans `TypingIndicator` (§4.4) |
| **Usage de tokens** (`token_usage`)                                                  | Affiché sous chaque réponse assistant, **uniquement en mode debug** (`?debug=1` dans l'URL, `composables/useDebugMode.ts` — pas de notion d'auth visiteur côté frontend pour distinguer un "visiteur admin")                                                                                                                                        |
| **Statuts LLM/embedding** (`GET /api/chat/llm-status`, `/api/chat/embedding-status`) | `llm-status` est consommé (`checkLlmStatus()` au montage du panneau) et pilote la vraie pastille de statut dans l'en-tête (`checking`/`online`/`offline`) — plus un texte statique. `embedding-status` reste **non consommé** par ce widget (voir §7.2)                                                                                              |
| **Streaming SSE** (`POST /api/conversations/{id}/stream`)                            | **Consommé** — c'est le chemin d'envoi réel de tout message depuis ce widget (§3.3, §5.1), pas `quick-send`                                                                                                                                                                                                                                          |

En résumé : ce frontend est une **vitrine minimaliste** du backend — beaucoup de capacités backend (streaming, traçabilité des outils, usage de tokens, statut des providers, conversations persistées) existent côté API mais ne sont **pas exploitées** dans l'UI actuelle. Elles constituent des évolutions naturelles (voir §9).

---

## 7. Référence API

### 7.1 Ce que ce frontend expose

| Méthode | Route (côté Nuxt)   | Comportement                                                                                                                                          |
| ------- | -------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `ANY`   | `/api/*`             | Route Nitro catch-all (`server/api/[...path].ts`) — proxy transparent vers `${API_URL}/api/*` sur le backend Symfony                                    |
| `GET`   | `/api/link-preview`  | `server/api/link-preview.get.ts` — récupère titre + favicon (inliné en `data:` URI, contrainte CSP `img-src`) d'une URL externe pour `LinkPreviewCard.vue`, ou l'image elle-même (`imageDataUri`, jusqu'à 3 Mo) si l'URL pointe directement sur une image (tranché sur le `Content-Type` réel de la réponse). N'appelle jamais le backend Symfony (`fetch` direct vers l'URL demandée, avec garde-fous SSRF — voir le fichier). Mis en cache 24h par URL (`defineCachedEventHandler`). |

`pages/index.vue` est une page vide (le widget est monté globalement depuis `app.vue`).

### 7.2 Ce que ce frontend consomme (endpoints backend Symfony réellement appelés)

Cette liste est aussi, depuis l'audit de sécurité, l'**allowlist exacte** que `server/api/[...path].ts` autorise à traverser le proxy (`ALLOWED_ROUTES`, voir §3.4) — tout ce qui n'y figure pas reçoit `404` avant d'atteindre le backend.

| Méthode | Endpoint backend                                         | Appelé depuis                                                    | Usage                                                                                |
| ------- | ---------------------------------------------------------- | ------------------------------------------------------------------ | --------------------------------------------------------------------------------------- |
| `GET`   | `/api/chat/llm-status`                                    | `useChatbot().checkLlmStatus()` (au montage du panneau)            | Statut du provider LLM actif, pastille dans l'en-tête                                   |
| `POST`  | `/api/chat/quick-send`                                    | Non appelé par ce widget aujourd'hui                                | Chat anonyme non persisté, pensé pour des **embedders tiers** directs — laissé dans l'allowlist car public-safe et rate-limité par design |
| `GET`   | `/api/chat/embedding-status`                               | Non appelé par ce widget aujourd'hui                                | Statut du provider d'embedding — même raison que `quick-send` (endpoint public-safe déjà existant côté backend, gardé dans l'allowlist par cohérence, pas par besoin actuel) |
| `POST`  | `/api/chat/follow-up-questions`                             | Non appelé par ce widget aujourd'hui (retiré du frontend, voir `docs/BACKLOG.md`) | Idem — endpoint toujours fonctionnel côté backend (rate-limité), juste plus consommé ici |
| `POST`  | `/api/conversations`                                       | `useChatbot().ensureConversation()`                                 | Création de la conversation, au premier message (`{title, is_active}`)                  |
| `GET`   | `/api/conversations/{id}/messages`                          | `useChatbot().restoreConversation()`                                | Restauration de l'historique au montage, si un `conversation_id` existe en `localStorage` |
| `POST`  | `/api/conversations/{id}/messages`                          | Non utilisé par ce widget (le vrai chemin d'envoi est `/stream` ci-dessous) | Chemin non-streaming de `ConversationMessagesController` — laissé dans l'allowlist car même contrôleur/protection que le `GET` ci-dessus |
| `PATCH` | `/api/conversations/{id}/messages/{messageId}/feedback`      | `useChatbot().setFeedback()`                                        | 👍/👎 sur une réponse assistant                                                          |
| `GET`   | `/api/faqs`                                                 | `useFaqs().fetchSuggestedQuestions()` (aussi via la route Nitro dédiée `server/api/faqs.get.ts`, cache 5 min — voir §3.4) | Questions suggérées (état vide + après chaque réponse)                                  |
| `GET`   | `/api/ai_agents`                                            | `useChatbot().fetchAgents()` (aussi via `server/api/ai_agents.get.ts`, cache 5 min) | Liste des agents IA, pour sélectionner automatiquement le premier actif                 |
| `GET`   | `/api/health`                                               | Non appelé par ce widget aujourd'hui                                | Endpoint de santé agrégé (DB/Qdrant/Redis/Ollama) — gardé dans l'allowlist pour un futur monitoring externe |

`POST /api/conversations/{id}/stream` (l'envoi réel de message, streaming SSE) passe par une route Nitro dédiée (`server/api/conversations/[id]/stream.post.ts`), prioritaire sur le catch-all — voir §5.1.

> [!NOTE]
> Voir [le cahier des charges backend](../backend/SPECIFICATION.md#8-référence-api-complète) pour le détail complet de l'API Symfony (bien plus large que cette liste — tout le reste, ressources admin comprises, existe côté backend mais n'est justement **pas** dans cette allowlist).

### 7.3 Configuration de la cible API

| Variable         | Défaut                        | Rôle                                                                                                                                                                                                                                                                                                                                                                    |
| ---------------- | ----------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `API_URL`        | `http://chatbot-symfony:8000` | URL du backend Symfony, résolue **côté serveur** (Nitro) au moment du proxy. Le nom `chatbot-symfony` n'est résolvable que dans le réseau Docker de `backend/compose.yaml` — en dehors de Docker Compose, il faut la surcharger explicitement (ex. `http://symfony.chatbot.localhost` via Traefik ; `localhost:8000` ne fonctionne plus, le port fixe ayant été retiré) |
| `ADMIN_USERNAME` | `''`                          | Identifiant du compte de service utilisé pour l'en-tête `Authorization: Basic` envoyé au backend (voir §3.4)                                                                                                                                                                                                                                                            |
| `ADMIN_PASSWORD` | `''`                          | Mot de passe en clair du même compte — jamais exposé côté client (`runtimeConfig` non-`public`, donc server-only)                                                                                                                                                                                                                                                       |

`API_URL` est exposée dans `nuxt.config.ts` via `runtimeConfig.public.apiUrl`, lue par `server/api/[...path].ts` via `useRuntimeConfig().public.apiUrl`. `ADMIN_USERNAME`/`ADMIN_PASSWORD` sont dans `runtimeConfig` (hors de `public`) — accessibles uniquement côté serveur, jamais sérialisées vers le client.

---

## 8. Installation et mise en place

### 8.1 Prérequis

- **Node.js 24**.
- Un backend Symfony accessible (voir [cahier des charges backend](../backend/SPECIFICATION.md#11-installation-et-mise-en-place)), via `http://symfony.chatbot.localhost` (Traefik) ou via le réseau Docker de `backend/compose.yaml`.
- Les identifiants du compte admin backend (`ADMIN_USERNAME`/`ADMIN_PASSWORD`, voir `backend/.env`) : depuis que `/api/*` exige une authentification HTTP Basic (cahier des charges backend, §10), le proxy Nitro doit les connaître pour relayer les appels.

> [!WARNING]
> Sans `ADMIN_USERNAME`/`ADMIN_PASSWORD` renseignés, tout appel `/api/*` échoue en `401` — le widget de chat reste silencieusement cassé pour tous les visiteurs.

### 8.2 Installation

```bash
cd frontend
npm install
```

### 8.3 Développement

```bash
API_URL=http://symfony.chatbot.localhost ADMIN_USERNAME=admin ADMIN_PASSWORD=*** npm run dev
```

Démarre sur **http://localhost:3000**. `API_URL` doit pointer vers le backend Symfony réellement joignable depuis la machine qui exécute `npm run dev` — en dev local hors Docker, ce n'est **pas** la valeur par défaut (`http://chatbot-symfony:8000`, un nom de conteneur résolvable uniquement dans le réseau Docker), ni `http://localhost:8000` (le port fixe du service `app` a été retiré, voir le cahier des charges backend) : il faut passer par le domaine Traefik `http://symfony.chatbot.localhost`. `ADMIN_USERNAME`/`ADMIN_PASSWORD` (valeurs de `backend/.env`) sont nécessaires depuis que `/api/*` exige une authentification HTTP Basic — sans eux, tout appel proxié échoue en `401`.

### 8.4 Build et production

```bash
npm run build      # build client + serveur (SSR) + Nitro
npm run generate   # génération statique (SSG)
npm run preview    # sert le build localement
```

**Via Docker** (`backend/compose.yaml`, service `nuxt`) : le conteneur exécute `npm ci && npm run build && HOST=0.0.0.0 PORT=3000 node .output/server/index.mjs` — build puis lancement direct du serveur Nitro compilé, pas de `npm run dev`. `API_URL` y est fixée à `http://chatbot-symfony:8000` (résolution via le réseau Docker interne), `ADMIN_USERNAME`/`ADMIN_PASSWORD` proviennent de `backend/.env`. Exposé sur le port hôte **3010**, et routable via `http://nuxt-symfony.chatbot.localhost` (Traefik, provider fichier — pas de label Docker, voir le cahier des charges backend §2).

### 8.5 Qualité de code

```bash
npm run format        # Prettier — reformate tous les fichiers
npm run format:check  # Prettier — vérifie sans modifier (CI)
npm run test          # Vitest — suite complète, une fois
npm run test:watch    # Vitest — mode watch
```

Aucun linter (ESLint) n'est configuré. Tests unitaires : voir §8.7.

### 8.6 Intégrer le widget dans une autre page/app Nuxt

```vue
<template>
  <Chatbot
    title="Mon Assistant"
    theme="dark"
    api-url="/api/chat"
    placeholder="Tapez votre message..."
  />
</template>

<script setup lang="ts">
import { Chatbot } from '~/components/Chatbot';
</script>
```

| Prop          | Type                | Défaut                     | Description                                                                                                           |
| ------------- | ------------------- | -------------------------- | --------------------------------------------------------------------------------------------------------------------- |
| `title`       | `string`            | `'Assistant IA'`           | Titre affiché dans l'en-tête                                                                                          |
| `theme`       | `'light' \| 'dark'` | `'light'`                  | Valeur de repli seulement si le visiteur n'a rien choisi et que l'OS n'a pas de préférence — voir §4.2, `useColorScheme.ts` |
| `apiUrl`      | `string`            | `'/api'`                   | *(non utilisée pour construire les URLs d'appel réel — voir §5.1, les endpoints sont codés en dur dans `useChatbot`)* |
| `placeholder` | `string`            | `'Tapez votre message...'` | Placeholder du champ de saisie                                                                                        |
| `className`   | `string`            | `''`                       | Classes CSS supplémentaires sur le conteneur racine                                                                   |
| `showClose`   | `boolean`           | `false`                    | Affiche un bouton de fermeture (utilisé par `ChatWidget`)                                                             |

### 8.7 Tests

**Vitest** (`vitest.config.ts`, `environment: 'nuxt'` via `@nuxt/test-utils/config`) — un vrai contexte Nuxt est démarré pour chaque fichier de test, donc les imports automatiques du projet (`useState`, `useI18n`, `useRoute`, `$fetch`, composables locaux comme `useFaqs`/`useOnlineStatus`) fonctionnent dans les tests exactement comme dans l'app, sans les importer explicitement. Fichiers `*.test.ts` colocalisés avec le code testé (`composables/useChatbot.test.ts` à côté de `useChatbot.ts`, etc.) plutôt qu'un dossier `tests/` séparé.

Couverture actuelle — composables uniquement, pas de test de composant `.vue` ni e2e :
- **`useOnlineStatus`** : reflète `navigator.onLine`, réagit aux events `online`/`offline`, arrête de réagir après unmount.
- **`useDebugMode`** : lecture de `?debug=1` dans l'URL, via `mockNuxtImport('useRoute', ...)`.
- **`useFaqs`** : peuple `suggestedQuestions` depuis `GET /api/faqs` (mocké avec `registerEndpoint`), ne fetch qu'une fois (`hasFetched`), dégrade silencieusement en cas d'échec, état partagé entre deux appels indépendants (`useState`).
- **`useChatbot`** : le plus gros morceau — garde-fous de `sendMessage()` (message vide, déjà en cours d'envoi, hors ligne), le chemin heureux complet (parsing des frames SSE `data: {...}\n\n`, y compris `ai_complete`/`sources`/`token_usage`), un frame `error`, une réponse HTTP non-`ok`, `retryLastMessage()` (rejoue sans dupliquer la bulle utilisateur), `clearMessages()`, `cancelReply()` (abandonne une requête en cours via `AbortController` sans poser d'erreur — le rejet `AbortError` est distingué d'un vrai échec réseau), gate `autoScroll` (`scrollToBottom()` ne fait rien tant que désactivé, `sendMessage()` le remet à `true`), notification desktop (`Notification` global stubbé — déclenchée seulement si `document.hidden` et permission accordée, jamais sinon).
- **`useNotificationSound`** : `muted` démarre à `false`, lit un choix persisté au montage (même schéma `localStorage` que `useColorScheme`), `toggleMuted()` bascule et persiste, `playMessageSound()` ne lève pas d'erreur pendant que `muted` est actif (le chime est simplement sauté).

Deux techniques de mock spécifiques à connaître avant d'y toucher :
- **`registerEndpoint`** (`@nuxt/test-utils/runtime`) mocke une route Nitro atteinte via `$fetch`/ofetch (ex. `ensureConversation`'s `POST /api/conversations`). Le flux SSE de `sendMessage()` (`server/api/conversations/[id]/stream.post.ts`) passe lui par le `fetch` brut du navigateur, pas `$fetch` — `registerEndpoint` ne l'intercepte donc pas. `useChatbot.test.ts` stub `globalThis.fetch` directement pour les URLs `/stream` uniquement (tout le reste repasse par le vrai `fetch`, donc `registerEndpoint` continue de fonctionner en parallèle dans le même test).
- **`mockNuxtImport`** est une macro transpilée à la compilation : elle doit être appelée au **niveau racine** du fichier de test, jamais à l'intérieur d'un `it(...)` (sinon `SyntaxError`/comportement silencieusement incorrect). Pour faire varier la valeur mockée d'un test à l'autre, importer la fonction déjà mockée (ex. `const { useRoute } = await import('#app')`) et appeler `vi.mocked(useRoute).mockReturnValue(...)`.
- Les composables utilisant des lifecycle hooks (`onMounted`/`onBeforeUnmount` — `useOnlineStatus`, `useChatbot`) doivent être invoqués dans un vrai contexte de composant, pas appelés nus dans un test : `test/withSetup.ts` monte un composant jetable via **`mountSuspended`** (`@nuxt/test-utils/runtime`) — pas le `mount()` classique de `@vue/test-utils`, qui n'installe pas les plugins Nuxt (ex. `useI18n()` échoue avec *"Need to install with `app.use` function"* sans ça).
- `useFaqs`/`useChatbot` partagent leur état via `useState` (clés globales à l'app Nuxt), donc au sein d'un même fichier de test il faut réinitialiser ces clés dans un `beforeEach` — sinon un test peut hériter silencieusement de l'état laissé par le précédent (ex. `hasFetched` déjà à `true`).

**Piège d'environnement (pas lié au code du projet)** : `backend/compose.yaml`'s service `nuxt` exécute `npm ci` à **chaque démarrage/redémarrage** du conteneur, sur un `node_modules` en bind-mount partagé avec l'hôte. Lancer `npm ci`/`npm install` depuis le conteneur Linux puis `npx vitest` depuis un hôte macOS (ou l'inverse) casse les binaires natifs optionnels (`rolldown`, etc.) — `Cannot find native binding`. Si ça arrive, relancer `npm install` depuis l'environnement où les tests doivent tourner.

### 8.8 Internationalisation (i18n)

**`@nuxtjs/i18n`**, configuré dans `nuxt.config.ts` (`strategy: 'no_prefix'`, une seule locale `fr` déclarée pour l'instant — pas de préfixe `/fr`/`/en` dans les URLs tant qu'il n'y a qu'une langue). Toutes les chaînes visibles par un visiteur vivent dans **`i18n/locales/fr.json`** (clés groupées par composant/zone : `home.*`, `heroChatBar.*`, `stickyBubble.*`, `chatbot.*`, `messageBubble.*`, `errors.*`, `meta.*`), plutôt qu'en dur dans les `.vue`/`.ts` — objectif : ajouter une 2ᵉ langue plus tard doit être « traduire ce fichier », pas « rechercher les chaînes dans chaque composant ».

- Dans un template : `{{ $t('chatbot.send') }}` / `:title="$t('chatbot.close')"`.
- Dans un `<script setup>` ou composable : `const { t } = useI18n();` puis `t('errors.offline')`. Utilisé dans `useChatbot.ts` pour les deux messages d'erreur utilisateur (`errors.sendFailed`/`errors.offline`) — pas pour les `console.error(...)` de debug juste au-dessus, qui restent en français en dur (jamais montrés à un visiteur).
- `app.vue` pose `<title>`/`<meta name="description">` via `useHead()` + `t('meta.title')`/`t('meta.description')` plutôt que dans `nuxt.config.ts` (évalué avant que le runtime i18n existe).
- **Piège rencontré** : `withDefaults(defineProps<T>(), {...})` est hoisté au niveau module par le compilateur `<script setup>` — ses valeurs par défaut ne peuvent **pas** référencer un `const { t } = useI18n()` local (`SyntaxError` au build). `Chatbot.vue` garde donc `title`/`placeholder` par défaut en littéraux français en dur ; sans conséquence pratique, les deux call sites actuels (`StickyChatBubble.vue`, `pages/chat.vue`) passent toujours une prop explicite traduite.
- Interpolation : `t('chatbot.bookSlotMessage', { label, iso })` avec `"bookSlotMessage": "Je réserve le créneau du {label} ({iso})."` dans le JSON.

> [!TIP]
> Pour le widget flottant complet (bulle + tooltip + panneau), utiliser directement `<ChatWidget />` (déjà monté globalement dans `app.vue`) plutôt que `<Chatbot />` seul.
