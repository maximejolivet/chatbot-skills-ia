# Chantier — pistes d'amélioration

> Backlog de pistes identifiées en analysant `docs/backend/SPECIFICATION.md` et
> `docs/frontend/SPECIFICATION.md`. Non priorisé formellement,
> non planifié — à trier au fur et à mesure.

## Sommaire

75 faites, 8 retirées/rejetées, 2 restantes. Détail complet (rationale, notes de vérification) dans les sections ci-dessous.

| Statut    | Domaine  | Fonctionnalité                                                  | Résumé                                                                                                                          |
| --------- | -------- | --------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| ✅ Fait    | Frontend | Rendu Markdown                                                  | déjà fait (`MessageBubble.vue`, `marked` + `isomorphic-dompurify`).                                                             |
| ✅ Fait    | Frontend | Statut LLM réel                                                 | `useChatbot.ts` appelle `GET /api/chat/llm-status` au montage du panneau (`checkLlmStatus`), expose `llmStatus`…                |
| ✅ Fait    | Frontend | Persistance de conversation                                     | déjà fait (`useChatbot.ts` utilise `/api/conversations` + `conversation_id` en `localStorage`, restauré au montage).            |
| ✅ Fait    | Frontend | Streaming (SSE)                                                 | branché malgré l'absence de gain UX (voir note) : - Backend (`ConversationStreamController.php`) : ajout du rate-limiting (20…  |
| ✅ Fait    | Frontend | Questions suggérées pilotées par API                            | `HeroChatBar.vue` (hero de la home) et le panneau `Chatbot.vue` (état vide) affichaient chacun la même liste de questions…      |
| ✅ Fait    | Frontend | Afficher `token_usage`                                          | au moins en mode debug/admin. La donnée existait déjà côté API (`metadata.token_usage`, cf. `MessageSerializer`) mais n'était…  |
| ✅ Fait    | Frontend | Tests                                                           | aucun test unitaire/e2e n'était configuré. Scope choisi : **tests unitaires des composables** (pas de test de composant…        |
| ✅ Fait    | Frontend | i18n                                                            | tout était en français en dur. Scope choisi (décision produit explicite) : **infrastructure seulement, une seule locale** pour… |
| ✅ Fait    | Frontend | Gestion d'erreur réseau plus robuste                            | pousser la bulle utilisateur (`sendMessage`) vs. requête/réponse (`requestAssistantReply`, nouveau). `retryLastMessage()`…      |
| ✅ Fait    | Frontend | Bouton "copier la réponse"                                      | pas de feature-detection comme pour `speechSupported`, le Clipboard API est largement disponible, l'échec (permission refusée,… |
| ✅ Fait    | Frontend | Questions de relance dynamiques                                 | prend `{message, answer}` directement, pas de lookup DB) et `App\Chat\FollowUpQuestionsService::generate()` — appelle le…       |
| ✅ Fait    | Frontend | Indicateur discret sur la bulle sticky                          | localStorage pour ne l'afficher qu'une fois. `StickyChatBubble.vue` : nouvelle clé `chatbot:bubble_seen` (distincte de…         |
| ✅ Fait    | Frontend | Mode sombre activable par le visiteur                           | le prop `theme="dark"` existait déjà sur `Chatbot.vue` mais rien ne le pilotait (pas de toggle, pas de détection…               |
| ✅ Fait    | Frontend | Chargement paresseux des données emoji                          | au premier clic sur le sélecteur plutôt qu'eagerly. `import()` dynamique dans `loadEmojiData()`, mémoïsé (un seul chargement…   |
| ✅ Fait    | Frontend | Feedback 👍/👎 câblé côté frontend                                | le backend l'exposait déjà (`Message.feedback`, `PATCH /conversations/{id}/messages/{messageId}/feedback` via…                  |
| ✅ Fait    | Frontend | Bouton "régénérer la réponse"                                   | relance la dernière réponse assistant sans retaper le message. Réutilise le mécanisme existant : nouveau…                       |
| ✅ Fait    | Frontend | En-têtes de sécurité HTTP                                       | `nuxt.config.ts`, `routeRules['/**'].headers`. Pas de nonce CSP (aurait demandé de brancher l'injection par requête dans le…    |
| ✅ Fait    | Frontend | Curseur clignotant pendant le streaming                         | visuel indiquant que la réponse est en train de se générer, maintenant que le streaming token-par-token existe vraiment (voir…  |
| ✅ Fait    | Frontend | Cache HTTP léger sur les endpoints publics en lecture seule     | ces deux endpoints sont identiques pour tout visiteur (pas de variation par utilisateur, gérés uniquement via le backoffice…    |
| ✅ Fait    | Frontend | Mode sombre appliqué à toute l'app (2026-08-25)                 | ne changeait en réalité que `Chatbot.vue` ; `pages/index.vue` restait toujours clair. `app.vue` résout désormais lui aussi…     |
| ✅ Fait    | Frontend | Avatar photo réel (2026-08-25)                                  | remplace l'avatar-lettre "M" partout (en-tête, écran vide, `TypingIndicator`, chaque réponse assistant, portrait du hero)…      |
| ✅ Fait    | Frontend | Suppression du plein écran par expansion du widget (2026-08-25) | `isFullscreen`/`fixed inset-4` retiré, remplacé par une navigation directe vers `/chat` (page déjà plein écran dédiée)…         |
| ✅ Fait    | Frontend | Panneau des épingles qui restait ouvert et vide (2026-08-25)    | bug : désépingler le dernier item depuis le panneau le laissait ouvert et vide, le bouton pour le fermer disparaissant…         |
| ✅ Fait    | Frontend | Passe accessibilité/responsive (audit mobile) (2026-08-25)      | cibles tactiles à 44px, contraste sombre renforcé, `text-base sm:text-sm` anti-zoom iOS, `env(safe-area-inset-bottom)`…         |
| ✅ Fait    | Frontend | En-têtes de sécurité HTTP sur le backoffice admin               | le durcissement CSP/HSTS précédent ne couvrait que le widget Nuxt public ; `/admin` (données métier réelles, plus sensible)…    |
| ✅ Fait    | Frontend | Annuler une réponse en cours au clavier (Échap)                 | le widget de chat n'avait aucun raccourci clavier : Entrée pour envoyer marchait déjà nativement (`<input>` simple dans un…     |
| ✅ Fait    | Frontend | Champ de saisie multi-ligne                                     | le champ était un `<input type="text">` à une seule ligne : impossible de coller un extrait de code ou d'écrire un message sur… |
| ✅ Fait    | Frontend | Pastille "nouveau message"                                      | un message qui arrivait pendant que le visiteur avait remonté dans l'historique le ramenait brutalement en bas…                 |
| ✅ Fait    | Frontend | Actions de bulle révélées au survol                             | les boutons écouter/copier/feedback/régénérer d'une bulle assistant étaient visibles en permanence, alourdissant visuellement…  |
| ✅ Fait    | Frontend | Séparateurs de date                                             | une conversation restaurée après plusieurs jours (`restoreConversation`, id persisté en `localStorage`) affichait tous les…     |
| ✅ Fait    | Frontend | Regroupement des messages consécutifs                           | deux messages du même rôle envoyés à la suite (ex. l'utilisateur qui complète sa question) s'affichaient avec le même…          |
| ✅ Fait    | Frontend | Skeleton pendant la restauration de l'historique                | `restoreConversation` fait un aller-retour réseau avant d'afficher quoi que ce soit ; le panneau passait d'un écran vide à la…  |
| ✅ Fait    | Frontend | Contrôle du son des notifications                               | `playMessageSound()` se déclenchait à chaque réponse sans qu'il y ait de moyen de le couper depuis l'UI.…                       |
| ✅ Fait    | Frontend | Copier un bloc de code                                          | le seul bouton copier existant (`MessageBubble.vue`) copiait tout le message ; copier juste un extrait de code depuis une…      |
| ✅ Fait    | Frontend | Focus automatique + raccourci `/`                               | le champ de saisie n'était jamais focus automatiquement (ouverture du widget, chargement de `/chat`, après un envoi via clic…   |
| ✅ Fait    | Frontend | Notification desktop en arrière-plan                            | le son (voir plus haut) ne signale rien si le visiteur a changé d'onglet et est muet. `useChatbot.ts` gagne…                    |
| ✅ Fait    | Frontend | Aperçu de lien (titre + favicon)                                | un lien dans une réponse s'affichait comme du texte brut souligné, sans contexte sur la destination. Nouvelle route Nitro `GET… |
| ✅ Fait    | Frontend | Compteur de caractères                                          | aucun repère sur la longueur d'un message en train d'être tapé. `showCharCount` (`Chatbot.vue`) affiche `inputValue.length` en… |
| ✅ Fait    | Frontend | Respect de `prefers-reduced-motion`                             | les animations en boucle s'étaient accumulées au fil des features (frappe, curseur de streaming, pastilles de statut/écoute,…   |
| ✅ Fait    | Frontend | Tableaux markdown défilants                                     | `prose-table` n'avait qu'une marge (`prose-table:my-2`), aucun `overflow-x-auto` : un tableau plus large que la bulle…          |
| ✅ Fait    | Frontend | Aperçu d'image inline                                           | extension du système d'aperçu de lien (`GET /api/link-preview`) : si l'URL pointe directement sur une image, tranché sur le…    |
| ✅ Fait    | Frontend | Séparateur de date collant                                      | "Aujourd'hui"/"Hier" défilait avec le contenu au lieu de rester visible pendant qu'on parcourt l'historique. Chaque séparateur… |
| ✅ Fait    | Frontend | Bouton "remonter en haut"                                       | symétrique de la pastille "nouveau message" existante. Le même `onMessagesScroll` (`Chatbot.vue`) pilote maintenant aussi…      |
| ✅ Fait    | Frontend | Toast "connexion rétablie"                                      | `useOnlineStatus()` signalait déjà le passage hors ligne (bandeau d'erreur), mais rien ne confirmait le retour. Nouveau…        |
| ✅ Fait    | Frontend | Raccourci `Cmd`/`Ctrl+K`                                        | ouvre/ferme le widget flottant (`StickyChatBubble.vue`) depuis n'importe où sur la page, pas seulement en cliquant la bulle —…  |
| ✅ Fait    | Frontend | Navigation clavier dans le picker d'emoji                       | seuls le clic et la recherche texte fonctionnaient. Roving tabindex sur la grille (un seul bouton dans l'ordre de tabulation à… |
| ✅ Fait    | Frontend | Commandes slash                                                 | menu déclenché en tapant `/` en tout début de champ (filtré à la frappe, navigable au clavier), même style visuel que le…       |
| ✅ Fait    | Frontend | Avatar animé                                                    | animation `breathe` (Tailwind, scale 1→1.06→1, 4s, `motion-reduce:animate-none`) sur les avatars "M" de l'en-tête et de…        |
| ✅ Fait    | Frontend | Effet machine à écrire réel                                     | les deltas de streaming arrivent parfois par rafales (un mot entier d'un coup), rendu saccadé. `MessageBubble.vue` fait…        |
| ✅ Fait    | Frontend | Export de conversation                                          | `useChatbot().exportConversation()` génère un `Blob` + `<a download>` à partir de `state.messages` (auteur, horodatage,…        |
| ✅ Fait    | Frontend | Épingler un message                                             | bouton sur chaque bulle (question ou réponse), persistance purement client (`localStorage`, aucun champ backend), ré-appliqué…  |
| ✅ Fait    | Frontend | Aperçu au survol de la bulle flottante fermée                   | `useChatbot.ts` sauvegarde un extrait (120 caractères) de chaque réponse assistant en `localStorage`, lu par…                   |
| ✅ Fait    | Frontend | Barre de progression indéterminée                               | segment glissant en boucle (style GitHub/YouTube) posé sur le bord supérieur du panneau pendant `isLoading`, en plus du…        |
| ✅ Fait    | Frontend | Célébration après réservation confirmée                         | effet ponctuel (pop + anneau de couleur accent qui s'étend et s'efface, une seule fois à l'insertion) sur la carte "✅…          |
| ✅ Fait    | Frontend | Formulaire inline prénom/nom                                    | quand l'assistant demande l'identité du visiteur en texte libre, une petite carte (deux champs + bouton "Valider") apparaît…    |
| ✅ Fait    | Frontend | États de progression pendant le tool-calling                    | le chemin bufferisé (LLM → exécution d'un workflow → second appel LLM) n'émettait aucun `delta`, donc rien à part le…           |
| ✅ Fait    | Frontend | Astuce de découverte des commandes                              | ce widget a accumulé plusieurs raccourcis puissants (commandes slash, Cmd/Ctrl+K) qu'un visiteur ne devine jamais seul.…        |
| ✅ Fait    | Frontend | Commande `/cv`                                                  | ouvre le vrai CV en ligne de Maxime (`https://www.maxime.bzh/cv-...pdf`, trouvé en suivant le lien fourni par lui —…            |
| 🚫 Retiré  | Frontend | Afficher les sources RAG                                        | (commit `318b03a`) : le backend force `metadata.sources_hidden = true` sur tous les messages, les sources ne remontent que…     |
| 🚫 Retiré  | Frontend | Afficher `tool_calls` (trace générique)                         | les workflows réels (`planifier_entretien` → API Cal.eu avec les coordonnées du recruteur, `enregistrer_identite`)…             |
| 🚫 Retiré  | Frontend | Mode compact                                                    | implémentée puis retirée à la demande explicite, sans remplacement.                                                             |
| 🚫 Retiré  | Frontend | Question surprise                                               | implémentée puis retirée à la demande explicite, sans remplacement.                                                             |
| 🚫 Retiré  | Frontend | Carte de contact `/contact`                                     | implémentée puis retirée à la demande explicite, remplacée par `/cv` (voir plus bas) plutôt que par un équivalent direct.       |
| 🚫 Retiré  | Frontend | Partage natif d'une réponse                                     | implémentée puis retirée à la demande explicite, sans remplacement.                                                             |
| 🚫 Retiré  | Frontend | Mode mains-libres                                               | implémentée puis retirée à la demande explicite, sans remplacement.                                                             |
| ⏳ À faire | Backend  | 2FA sur `/admin`                                                | aujourd'hui mot de passe seul (firewall `form_login` classique).                                                                |
| ⏳ À faire | Backend  | CAPTCHA/Turnstile léger sur `quick-send`                        | le seul endpoint pensé pour des embedders tiers, donc le plus exposé à un abus anonyme malgré le rate-limiting déjà en place.   |
| ✅ Fait    | Backend  | `GET /api/faqs` rendu public (lecture seule)                    | `Faq` n'exposait que du CRUD réservé `ROLE_ADMIN` sur toute la ressource. `GetCollection` et `Get` sont désormais…              |
| ✅ Fait    | Backend  | Streaming compatible tool-calling                               | limite précédemment assumée (§12.1 de la spec backend) : réponse générée entièrement côté serveur puis émise en SSE en un seul… |
| ✅ Fait    | Backend  | `quick-send` n'expose pas les `sources`                         | **la prémisse s'est révélée fausse en creusant** : `QuickSendController` renvoyait déjà `sources` en clair depuis le commit…    |
| ✅ Fait    | Backend  | Recherche hybride                                               | pas de "vraie" recherche BM25 côté Qdrant (vecteurs creux/miniCOIL : demanderait un second vectorizer dans la stack,…           |
| ✅ Fait    | Backend  | Étendre la couverture de tests                                  | `KnowledgeBase`, `VectorConnector`, `AiProvider` n'avaient aucun test. +48 tests (19 → 67, tous verts) : `TokenEstimatorTest`,… |
| ✅ Fait    | Backend  | Endpoint de santé agrégé                                        | nouveau `GET /api/health` (`App\Controller\HealthController` + `App\ApiResource\HealthAction`, même schéma que…                 |
| ✅ Fait    | Backend  | Workflow step `condition`                                       | seule l'action `set_field` était implémentée pour `true_action`/`false_action`. Étendu à `add_field` et `remove_field`, en…     |
| ✅ Fait    | Backend  | Dashboard analytics                                             | usage de tokens, feedback 👍👎 (`Message.feedback`), requêtes vectorielles (`SearchQuery`) : les données existaient déjà mais…    |
| ✅ Fait    | Backend  | Journal d'audit des actions admin                               | qui a créé/modifié/supprimé quelle ressource (`AiProviderConfig`, `Faq`, `Workflow`...) et quand. Nouvelle table `audit_log`…   |
| ✅ Fait    | Backend  | Cache d'embeddings                                              | évite un aller-retour Ollama à chaque recherche identique. Nouveau `App\VectorConnector\QueryEmbeddingCache`, même schéma que…  |
| ✅ Fait    | Backend  | Toolchain de qualité PHP                                        | absente jusqu'ici (voir l'item couverture de tests plus haut, et le commentaire laissé dans `deploy-backend.yml` : "No…         |
| ✅ Fait    | Backend  | Durcissement anti-injection de prompt sur le contenu RAG        | le contenu des chunks de documents était concaténé brut dans le prompt système, sans délimitation ni avertissement. N'importe…  |
| ✅ Fait    | Backend  | Cache du dashboard `/admin/analytics`                           | revient sur une décision explicite antérieure ("No caching: admin-only, low-traffic page, cheap queries", commentaire de…       |
| ✅ Fait    | Backend  | Recherche/filtrage des messages dans une conversation admin     | `/admin/conversations/{id}` affichait déjà tous les messages, mais sans aucun moyen de les parcourir : une conversation longue… |
| ✅ Fait    | Backend  | Priorité d'affichage des FAQ                                    | nouveau champ `Faq.priority` (entier, 0 par défaut, migration manuelle `dbal:run-sql` + `doctrine_migration_versions` —…        |
| ✅ Fait    | Backend  | Audit de sécurité + performance                                 | trouvé et corrigé : 9 contrôleurs API Platform à contrôleur personnalisé (`WorkflowStepsController`,…                           |
| ✅ Fait    | Repo     | `composer audit`/`npm audit` en CI                              | déjà disponibles en local (`make audit`), pas encore branchés sur chaque PR. Nouveau workflow `.github/workflows/audit.yml`…    |
| 🚫 Retiré  | Repo     | Temps de réflexion affiché                                      | implémentée puis retirée à la demande explicite, sans remplacement.                                                             |

---

---

## Frontend (`frontend/`)

Le widget actuel n'exploite qu'une fraction de ce que l'API backend expose déjà.

- [x] **Rendu Markdown** des réponses assistant — déjà fait
      (`MessageBubble.vue`, `marked` + `isomorphic-dompurify`).
- [x] ~~Afficher les sources RAG~~ — **rejeté intentionnellement** (commit
      `318b03a`) : le backend force `metadata.sources_hidden = true`
      sur tous les messages, les sources ne remontent que côté admin
      (`GET /conversations/{id}/sources`), jamais dans le widget public.
      Un ajout d'UI avait été tenté puis retiré après avoir constaté ce flag
      en testant en vrai. *Supprimé au passage : `components/ChatMessage.vue`,
      composant orphelin (jamais importé) qui avait une ébauche similaire
      branchée sur `stores/chatStore.js`, également supprimé.*
- [x] **Statut LLM réel** dans l'en-tête — `useChatbot.ts` appelle
      `GET /api/chat/llm-status` au montage du panneau (`checkLlmStatus`),
      expose `llmStatus` (`checking`/`online`/`offline`), câblé dans le
      pastille + texte de l'en-tête de `Chatbot.vue`. Vérifié via l'API à
      travers le proxy Nuxt (`curl http://nuxt.chatbot.localhost/api/chat/llm-status`
      → `{"status":"running",...}`) ; pas de vérification visuelle dans un
      vrai navigateur (extension Chrome indisponible pendant la session).
- [x] **Persistance de conversation** — déjà fait (`useChatbot.ts` utilise
      `/api/conversations` + `conversation_id` en `localStorage`, restauré au
      montage).
- [x] **Streaming (SSE)** — branché malgré l'absence de gain UX (voir note) :
      - Backend (`ConversationStreamController.php`) : ajout du rate-limiting
        (20 msg/min/IP, alignement sur `quick-send`/`messages` — il en était
        dépourvu) et le payload `ai_complete` envoie maintenant le message
        complet (`MessageSerializer::serialize()`) au lieu de juste `content`,
        pour ne pas perdre `sources`/`tool_calls`/`id`/`feedback`.
      - Frontend : nouvelle route de proxy dédiée
        `server/api/conversations/[id]/stream.post.ts` (`proxyRequest`, pas de
        buffering — le catch-all générique `[...path].ts` bufferise via
        `$fetch` et casserait le stream) ; `useChatbot.ts::sendMessage` lit le
        flux SSE via `fetch` + `ReadableStream` au lieu de l'appel bloquant
        JSON.
      - **Note** : n'apporte aucun effet de frappe progressive — le backend
        génère la réponse complète avant d'émettre quoi que ce soit (limite
        assumée, §12.1). Branché pour cohérence d'architecture à la demande
        explicite, pas pour un gain UX.
      - Vérifié via `curl -N` de bout en bout (backend direct + à travers le
        nouveau proxy Nuxt) et test du rate-limit (429 au 21ᵉ appel/minute).
        Pas de vérification visuelle dans un vrai navigateur (extension
        Chrome indisponible pendant la session).
- [x] ~~Afficher `tool_calls` (trace générique)~~ — **rejeté intentionnellement** :
      les workflows réels (`planifier_entretien` → API Cal.eu avec les
      coordonnées du recruteur, `enregistrer_identite`) exposeraient des
      détails internes (payload/réponse API, noms de workflow en
      snake_case) à un visiteur si affichés bruts — cohérent avec le
      masquage des sources. À la place : ajout d'une **UI curatée** pour
      `planifier_entretien` dans `MessageBubble.vue` (carte "✅ Entretien
      confirmé pour {nom} — {date}"), construite uniquement à partir des
      `arguments` du tool call (notre propre schéma), jamais de la réponse
      Cal.eu elle-même. *Non testé de bout en bout : déclencher ce workflow
      pour de vrai créerait une vraie réservation Cal.eu + un vrai email.*
- [x] **Questions suggérées pilotées par API** — `HeroChatBar.vue` (hero de la
      home) et le panneau `Chatbot.vue` (état vide) affichaient chacun la même
      liste de questions codée en dur. Remplacée par `composables/useFaqs.ts`
      (`GET /api/faqs`, `useState` partagé entre les deux composants pour
      n'appeler l'API qu'une fois) : le contenu vient désormais de
      `/admin/faqs` côté backend au lieu d'être figé dans le code (voir entrée
      backend correspondante). Vérifié via l'API à travers le proxy Nuxt
      (`curl -H "Content-Type: application/ld+json"
      http://nuxt.chatbot.localhost/api/faqs` → la FAQ active existante en
      base remonte bien) ; pas de vérification visuelle dans un vrai
      navigateur (extension Chrome indisponible pendant la session).
      **Note** : si aucune FAQ active n'existe dans `/admin/faqs`, la liste de
      suggestions est simplement vide — pas de repli codé en dur.
- [x] **Afficher `token_usage`** — au moins en mode debug/admin. La donnée
      existait déjà côté API (`metadata.token_usage`, cf. `MessageSerializer`)
      mais n'était pas capturée côté frontend. Ajout de `Message.tokenUsage`
      (`types/index.ts`), câblé dans `useChatbot.ts` (restauration + réponse
      SSE `ai_complete`), affiché dans `MessageBubble.vue` sous chaque
      réponse assistant (`🔧 N tokens (prompt↑/completion↓) · provider/model`).
      Gate via nouveau `composables/useDebugMode.ts` (`?debug=1` dans l'URL) :
      pas de vrai système d'auth côté frontend (le proxy Nuxt s'authentifie
      toujours en admin, indépendamment du visiteur, voir `[...path].ts`),
      donc pas de notion de "visiteur admin" à distinguer — un query param
      est le gate le plus léger sans construire une auth frontend pour un
      seul toggle. Vérifié via `curl -N` sur `POST .../stream` : le payload
      `ai_complete` contient bien `metadata.token_usage`. Pas de vérification
      visuelle dans un vrai navigateur (extension Chrome indisponible).
- [x] **Tests** — aucun test unitaire/e2e n'était configuré. Scope choisi :
      **tests unitaires des composables** (pas de test de composant `.vue`,
      pas d'e2e navigateur — voir `docs/frontend/SPECIFICATION.md` §8.7 pour
      le détail complet). Stack : Vitest + `@nuxt/test-utils` (environnement
      `nuxt`, un vrai contexte Nuxt par fichier de test — auto-imports du
      projet disponibles sans les importer) + `@vue/test-utils` + happy-dom.
      21 tests sur 4 composables : `useOnlineStatus`, `useDebugMode`,
      `useFaqs`, et surtout `useChatbot` (9 tests — jamais testé jusqu'ici,
      c'est la logique la plus dense du frontend : garde-fous de
      `sendMessage`, parsing des frames SSE, `retryLastMessage`,
      `clearMessages`). `npm run test` / `npm run test:watch`.
      **Piège rencontré et documenté** : le service Docker `nuxt` fait
      tourner `npm ci` à chaque redémarrage sur un `node_modules` en
      bind-mount partagé avec l'hôte — l'alterner entre le conteneur Linux
      et un `npm install` côté hôte macOS casse les binaires natifs
      optionnels (`rolldown`). Un `npm install` côté hôte après un restart
      du conteneur corrige.
- [x] **i18n** — tout était en français en dur. Scope choisi (décision
      produit explicite) : **infrastructure seulement, une seule locale**
      pour l'instant — pas de traduction vers une 2ᵉ langue, mais plus rien
      de figé dans les composants pour en ajouter une plus tard. `@nuxtjs/i18n`
      (`strategy: 'no_prefix'`, pas de préfixe `/fr`/`/en` tant qu'il n'y a
      qu'une langue), toutes les chaînes visibles déplacées dans
      `i18n/locales/fr.json`. Détail complet (organisation des clés,
      interpolation, piège rencontré avec `withDefaults()` hoisté qui ne
      peut pas référencer `useI18n()`) dans `docs/frontend/SPECIFICATION.md`
      §8.8. Vérifié via SSR réel (`curl` sur `/` et `/chat`) : les chaînes
      traduites apparaissent bien dans le HTML rendu.
- [x] **Gestion d'erreur réseau plus robuste** (retry, détection offline) :
      - Détection offline : nouveau `composables/useOnlineStatus.ts`
        (`navigator.onLine` + events `online`/`offline`). `useChatbot.ts`
        refuse d'émettre la requête et affiche directement "Vous êtes hors
        ligne..." quand hors ligne, plutôt qu'un échec réseau confus après
        coup ; le même message apparaît aussi si la connexion tombe pendant
        la requête elle-même (le `catch` vérifie l'état au moment de l'échec).
      - Retry : `sendMessage()` scindé en deux — pousser la bulle utilisateur
        (`sendMessage`) vs. requête/réponse (`requestAssistantReply`,
        nouveau). `retryLastMessage()` rejoue uniquement la seconde moitié
        pour le dernier message utilisateur, sans dupliquer sa bulle. Bouton
        "Réessayer" ajouté à côté du bandeau d'erreur dans `Chatbot.vue`.
      - Pas de retry automatique/silencieux : un échec reste visible et le
        renvoi reste une action explicite de l'utilisateur (moins surprenant
        qu'un re-envoi en arrière-plan d'un message potentiellement périmé).
      - Pas de vérification visuelle dans un vrai navigateur (extension
        Chrome indisponible) — logique testée par lecture de code + `curl -N`
        du flux SSE existant (inchangé côté backend, cf. tests Streaming
        ci-dessus).
- [x] **Bouton "copier la réponse"** sur les bulles assistant (`MessageBubble.vue`).
      Ajouté à côté du bouton "écouter" existant, même style (icône ronde
      `h-5 w-5`, `hover:text-accent`), visible pour tout message assistant non
      `isTyping` — pas de feature-detection comme pour `speechSupported`, le
      Clipboard API est largement disponible, l'échec (permission refusée,
      contexte non sécurisé) est juste absorbé silencieusement (le bouton ne
      montre simplement pas le retour "copié"). Copie `message.content` brut
      (le markdown source, pas le rendu HTML ni la version allégée utilisée
      pour la synthèse vocale) — préserve le formatage si collé ailleurs dans
      un contexte qui comprend aussi le markdown. Retour visuel : icône
      remplacée par un check pendant 1,5 s après une copie réussie (`ref`
      local + `setTimeout`, nettoyé dans `onBeforeUnmount`). Nouvelles clés
      i18n `messageBubble.copy`/`messageBubble.copied`. Vérifié : suite
      Vitest toujours au vert (23 tests, aucun test dédié à ce bouton — pas
      de test de composant `.vue` dans ce repo, voir l'item Tests plus haut),
      Prettier appliqué, page `/chat` toujours rendue (SSR, 200) après le
      changement. Pas de vérification visuelle dans un vrai navigateur
      (extension Chrome indisponible pendant la session).
- [x] **Questions de relance dynamiques** après chaque réponse assistant (pas
      seulement à l'état vide), générées à partir du contexte RAG plutôt que
      des FAQ statiques. Nouveau `POST /api/chat/follow-up-questions`
      (`App\Controller\FollowUpQuestionsController` + `App\ApiResource\
      FollowUpQuestionsAction`, même schéma stateless que `QuickSendController`
      — prend `{message, answer}` directement, pas de lookup DB) et
      `App\Chat\FollowUpQuestionsService::generate()` — appelle le client LLM
      d'analyse dédié (celui de `DocumentAnalysisService`, `OLLAMA_ANALYSIS_MODEL`)
      sur le seul échange question/réponse qui vient d'avoir lieu (déjà
      RAG-grounded, pas besoin de relancer une recherche vectorielle), lui
      demande 2-3 questions de relance en JSON, best-effort (jamais
      d'exception qui remonte, `[]` en cas d'échec).
      **Piège rencontré** : `gpt-oss:20b` (modèle "thinking") dépense la
      majorité du budget de tokens en chain-of-thought caché avant d'écrire
      le JSON final — un `maxTokens` trop serré (300, "pourtant largement
      suffisant pour 3 questions") tronquait la génération **avant** que le
      modèle atteigne le JSON, laissant `content` vide sans la moindre
      erreur. Mesuré à ~800 tokens de bout en bout pour ce prompt ;
      `maxTokens: 1200` en pratique. Découvert en testant Ollama directement
      (`docker exec chatbot-symfony curl .../api/chat`), pas visible dans les
      logs applicatifs (juste "Could not extract JSON").
      Frontend (`useChatbot.ts`) : fetch **non bloquant** lancé juste après
      l'affichage de la vraie réponse (jamais avant, jamais en attente),
      vidé au début de chaque nouvel envoi pour ne pas laisser traîner des
      suggestions périmées. Affichées comme puces sous la dernière bulle
      assistant (`Chatbot.vue`, même style que les suggestions de l'état
      vide). 10 tests backend (`FollowUpQuestionsServiceTest` — parsing JSON
      robuste : prose autour, JSON invalide, entrées non-string filtrées,
      plafond à 3 questions, troncature à 100 caractères) + 2 tests frontend
      (`useChatbot.test.ts`). Vérifié en conditions réelles de bout en bout
      (`curl` à travers le proxy Nuxt) : questions pertinentes et
      grammaticalement correctes générées à partir d'un vrai échange.
      **Retiré du frontend depuis** (décision produit explicite) :
      suppression complète de l'intégration côté widget —
      `fetchFollowUpQuestions()`/`followUpQuestions` retirés de
      `useChatbot.ts` (et du type `ChatbotState`), bloc de rendu retiré de
      `Chatbot.vue`, 2 tests dédiés retirés de `useChatbot.test.ts` (28 → 26
      tests). Le backend (`FollowUpQuestionsController`/
      `FollowUpQuestionsService`, `POST /api/chat/follow-up-questions`) n'a
      pas été touché — endpoint toujours fonctionnel, juste plus appelé par
      le widget.
- [x] **Indicateur discret sur la bulle sticky** (pulse/badge) à la première
      visite, pour attirer l'œil sans être intrusif — localStorage pour ne
      l'afficher qu'une fois. `StickyChatBubble.vue` : nouvelle clé
      `chatbot:bubble_seen` (distincte de `CONVERSATION_ID_STORAGE_KEY` —
      "a remarqué la bulle" et "a démarré une conversation" sont deux choses
      différentes), badge retiré définitivement dès le premier clic, qu'il
      ouvre le popin ou redirige vers `/chat`. Réutilise le keyframe
      `pulse-ring` de `tailwind.config.js` (déjà défini, jamais utilisé
      jusqu'ici).
- [x] **Mode sombre activable par le visiteur** — le prop `theme="dark"`
      existait déjà sur `Chatbot.vue` mais rien ne le pilotait (pas de
      toggle, pas de détection `prefers-color-scheme`). **Portée réelle plus
      large que prévu en creusant** : `darkMode: 'class'` était déjà dans
      `tailwind.config.js`, mais aucune palette sombre n'existait nulle part
      (couleurs figées en hex dans `tailwind.config.js`, zéro classe `dark:`
      dans tout `components/`/`pages/`) — le prop `theme` ne pilotait
      littéralement rien, pas seulement "pas de toggle".
      - **Palette** : tokens convertis de hex figés en variables CSS
        (triplet RGB, `assets/css/main.css` `:root`/`.dark`,
        `tailwind.config.js` en `rgb(var(--x) / <alpha-value>)` — pattern
        shadcn/ui, supporte nativement les modificateurs d'opacité déjà en
        usage type `bg-accent/10`). Mode sombre **pas** inventé from scratch :
        réutilise l'encre (`#17151f`) et le lilas (`#efebfb`) déjà de la
        palette claire, rôles inversés (encre devient le fond, lilas devient
        le texte) plutôt qu'un noir quasi-pur + accent néon générique —
        cohérent avec l'identité de marque existante. `accent`/`destructive`
        éclaircis pour rester lisibles sur fond sombre.
      - **Détection/persistance** : nouveau `composables/useColorScheme.ts` —
        priorité choix explicite du visiteur (`localStorage`) > préférence
        OS (`matchMedia('(prefers-color-scheme: dark)')`, suivie en direct
        tant qu'aucun choix explicite n'existe) > prop `theme` (devient un
        simple défaut de repli, ne force plus rien). `theme="light"` retiré
        de `StickyChatBubble.vue`/`pages/chat.vue` (sinon ce forçage aurait
        toujours gagné et rendu le toggle inopérant).
      - **Toggle** : bouton lune/soleil ajouté aux deux en-têtes existantes
        de `Chatbot.vue` (widget flottant et variante `page`), même style que
        les boutons voisins (effacer/plein écran/retour accueil).
      - **Cohérence visuelle variante `page`** (`/chat`) : le dégradé
        d'ambiance (`hero-wash`/`hero-aura`, décoratif, hors du scope de
        `Chatbot.vue`) est volontairement laissé tel quel en clair (transparence
        voulue) — non traité en sombre pour rester dans le périmètre de
        l'item. À la place, `Chatbot.vue` applique un `bg-background` opaque
        uniquement quand `scheme === 'dark'` pour cette variante, pour éviter
        des bulles sombres sur un dégradé pastel clair.
      - **`::selection`** rendu thémable (`rgb(var(--muted))`/`rgb(var(--accent))`)
        au passage, même mécanisme.
      - **Écart accepté** : pas de script bloquant anti-flash — un flash
        d'un frame du mauvais thème est possible entre le rendu SSR et
        `onMounted` (résolution de `matchMedia`/`localStorage`, forcément
        côté client). Jugé disproportionné pour un widget de chat (le panneau
        flottant ne monte qu'à l'ouverture, `/chat` est une page secondaire),
        cohérent avec le même compromis déjà accepté par
        `useOnlineStatus`/`useDebugMode`.
      - 5 tests ajoutés (`useColorScheme.test.ts` : repli par défaut,
        détection OS, priorité du choix stocké, persistance du toggle,
        le toggle bloque bien le suivi live de l'OS ensuite) — suite Vitest
        passée de 23 à 28 tests, toujours au vert. Prettier appliqué.
        Vérifié : redémarrage du conteneur Nuxt nécessaire après l'édition de
        `tailwind.config.js` (le HMR Vite ne recompile pas toujours les
        classes de couleur à chaud) puis CSS compilé inspecté directement
        (`curl` sur les bundles `_nuxt/assets/css/main.css` et
        `_nuxt/node_modules/tailwindcss/tailwind.css`) : les deux valeurs
        (claire/sombre) de chaque token présentes, classes utilitaires
        référençant bien `var(--token)` de façon dynamique. Pas de
        vérification visuelle dans un vrai navigateur (extension Chrome
        indisponible pendant la session).
- [x] **Chargement paresseux des données emoji** (`unicode-emoji-json`,
      chargé au montage de `Chatbot.vue`) — au premier clic sur le sélecteur
      plutôt qu'eagerly. `import()` dynamique dans `loadEmojiData()`,
      mémoïsé (un seul chargement même si le sélecteur est rouvert/fermé
      plusieurs fois), état "Chargement…" affiché pendant l'attente plutôt
      que l'état vide "Aucun emoji trouvé".
- [x] **Feedback 👍/👎 câblé côté frontend** — le backend l'exposait déjà
      (`Message.feedback`, `PATCH /conversations/{id}/messages/{messageId}/feedback`
      via `MessageFeedbackController`, agrégé dans le dashboard `/admin/analytics`)
      mais rien côté widget ne l'utilisait : type `Message` sans champ
      `feedback`, aucun bouton. Ajouté `feedback?: 'positive' | 'negative' | null`
      à `Message` (`types/index.ts`), boutons 👍/👎 dans `MessageBubble.vue`
      (à côté des boutons écouter/copier, mise en surbrillance par fond
      `bg-accent/10`/`bg-destructive/10` plutôt que par couleur de texte —
      un glyphe emoji ignore `currentColor`/`text-*`, contrairement aux
      icônes SVG voisines), toggle (recliquer le même choix l'annule,
      envoie `feedback: null`). Nouveau `useChatbot().setFeedback(id,
      feedback)` : mise à jour optimiste immédiate + `PATCH`, rollback sur
      échec. `restoreConversation()` et la construction du message depuis le
      frame SSE `ai_complete` lisent désormais aussi `feedback` (déjà présent
      dans les deux réponses côté backend, juste jamais lu). 4 tests ajoutés
      (`useChatbot.test.ts` : application optimiste, toggle vers `null`,
      rollback sur échec réseau, no-op sans conversation persistée) — suite
      passée de 26 à 30 tests. Vérifié en conditions réelles de bout en bout
      (`curl` à travers le proxy Nuxt : conversation + message créés,
      `PATCH .../feedback` avec `{"feedback":"positive"}`, `GET
      .../messages` confirme la persistance) puis nettoyé.
- [x] **Bouton "régénérer la réponse"** — relance la dernière réponse
      assistant sans retaper le message. Réutilise le mécanisme existant :
      nouveau `useChatbot().regenerateLastReply()`, quasi identique à
      `retryLastMessage()` (déjà là pour le bandeau d'erreur) mais retire
      d'abord la dernière bulle assistant du fil (`messages.pop()`) avant de
      relancer `requestAssistantReply()`, pour que le nouveau message la
      remplace au lieu de s'empiler — `retryLastMessage()` n'avait pas ce
      problème car il ne s'exécute qu'après un échec, donc aucune bulle
      assistant n'existe encore pour ce tour. L'ancienne réponse reste en
      base (nouvelle ligne `Message` créée par le stream), simplement plus
      affichée côté client. Bouton visible uniquement sur la dernière bulle
      assistant (prop `isLast` sur `MessageBubble.vue`, calculée dans
      `Chatbot.vue`). 2 tests ajoutés (no-op si le dernier message n'est pas
      une réponse assistant, remplacement sans doublon) — suite passée de 30
      à 32 tests.
- [x] **En-têtes de sécurité HTTP** (CSP, HSTS) sur le widget public —
      `nuxt.config.ts`, `routeRules['/**'].headers`. Pas de nonce CSP (aurait
      demandé de brancher l'injection par requête dans le SSR de Nuxt) :
      `'unsafe-inline'` sur `script-src`/`style-src`, compromis pragmatique
      pour une app SSR Vue sans cette infrastructure — le payload
      d'hydratation de Nuxt est un `<script>` inline, et les styles scopés
      Vue peuvent atterrir inline aussi. `'unsafe-eval'`/`connect-src ws:`
      uniquement en dev (détecté via `NODE_ENV`, pas `process.dev` qui n'est
      pas défini dans ce contexte d'évaluation top-level du fichier de
      config — piège rencontré, corrigé). Seules ressources externes
      autorisées : Google Fonts (`style-src`/`font-src`), déjà la seule
      dépendance externe réelle du frontend (vérifié : aucune autre URL
      `http(s)://` dans `components/`/`pages/`). Pas de `frame-ancestors`
      trop permissif (`'self'`), `object-src 'none'`. **Portée** : le
      frontend n'a pas de déploiement en prod pour l'instant (voir
      `docs/DEPLOYMENT.md`), donc ce durcissement ne protège encore personne en
      pratique — préparé pour quand ce sera le cas. Vérifié : en-têtes
      confirmés présents (`curl -sI`) sur `/` et `/chat`, avec les
      directives dev (`unsafe-eval`, `ws:`) bien présentes en local ; pas de
      vérification visuelle dans un vrai navigateur (extension Chrome
      indisponible pendant la session) — vérification statique à la place
      (aucune ressource externe au-delà de Google Fonts référencée nulle
      part dans le code).
- [x] **Curseur clignotant pendant le streaming** — visuel indiquant que la
      réponse est en train de se générer, maintenant que le streaming
      token-par-token existe vraiment (voir l'item backend correspondant).
      Réutilise une utilité Tailwind `animate-blink` déjà définie mais
      jamais utilisée (même schéma que `LlmClientInterface::stream()` :
      construit en anticipation d'une fonctionnalité, jamais branché avant
      cette passe). Nouvelle prop `isStreaming` sur `MessageBubble.vue`
      (`isLoading && dernier message && role assistant`, calculée dans
      `Chatbot.vue`), petite barre verticale après le contenu rendu.
      `TypingIndicator` (points rebondissants) et ce curseur sont
      complémentaires et ne se chevauchent jamais : l'un s'affiche avant que
      la bulle assistant existe (aucun delta encore arrivé), l'autre après.
- [x] **Cache HTTP léger sur les endpoints publics en lecture seule**
      (`/api/faqs`, `/api/ai_agents`) — ces deux endpoints sont identiques
      pour tout visiteur (pas de variation par utilisateur, gérés uniquement
      via le backoffice admin, aucune opération d'écriture exposée côté API)
      et sollicités à chaque montage du widget. Simplement poser
      `Cache-Control` côté Symfony (API Platform `cacheHeaders`) n'aurait
      rien changé en pratique : rien dans cette architecture n'honore ce
      header (pas de proxy de cache devant le `php -S` de prod). Le vrai
      point de passage unique de chaque requête visiteur est le proxy Nuxt —
      donc deux routes Nitro dédiées (`server/api/faqs.get.ts`,
      `server/api/ai_agents.get.ts`, plus spécifiques que le catch-all
      générique `[...path].ts` et prioritaires devant lui) utilisant
      `defineCachedEventHandler` (TTL 5 min, clé statique puisque la réponse
      est identique pour tout le monde). Vérifié en conditions réelles :
      premier appel ~0,6-0,7 s (vrai aller-retour backend), appels suivants
      ~5 ms (cache hit, >100x plus rapide) ; endpoints non concernés
      (`quick-send`, `llm-status`) toujours au vert, donc pas d'effet de
      bord sur le catch-all générique.

## Backend (`backend/`)

- [x] **`GET /api/faqs` rendu public (lecture seule)** — `Faq` n'exposait que
      du CRUD réservé `ROLE_ADMIN` sur toute la ressource. `GetCollection`
      et `Get` sont désormais `PUBLIC_ACCESS` (même schéma que `AiAgent` :
      lecture ouverte, écriture retirée de l'API — la gestion reste
      exclusivement via `/admin/faqs`). Nouvelle
      `App\Doctrine\FaqActiveCollectionExtension` (query extension Doctrine
      ORM, sur le modèle de `OwnershipCollectionExtension`) exclut les FAQ
      `isActive = false` de la collection publique ; la grille `/admin/faqs`
      continue de toutes les lister, cette grille passant par le repository
      Sylius et non par API Platform. Suite PHPUnit backend toujours au vert
      (14 tests, `php bin/phpunit`) après ce changement. *Rappel : comme tout
      `/api/*`, le endpoint reste derrière le firewall stateless
      (`access_control: roles: ROLE_USER`) — "public" ici veut dire "pas
      réservé aux comptes `ROLE_ADMIN`", pas "accessible sans authentification
      HTTP Basic". Le proxy Nuxt (`server/api/[...path].ts`) s'authentifie
      déjà en admin sur chaque appel, donc le widget public n'en voit rien.*
- [x] **Streaming compatible tool-calling** — limite précédemment assumée
      (§12.1 de la spec backend) : réponse générée entièrement côté serveur
      puis émise en SSE en un seul bloc. Traité comme son propre chantier
      dédié, comme prévu lors du report initial.
      **Découverte en creusant** : `OllamaLlmClient::stream()` et
      `OpenAiCompatibleLlmClient::stream()` (NDJSON / SSE, parsing des deltas)
      existaient déjà, complets et fonctionnels, sur `LlmClientInterface` —
      jamais appelés nulle part dans le code (confirmé par recherche globale),
      probablement construits en anticipation de cette fonctionnalité puis
      jamais branchés. Le vrai travail restant était donc le câblage, pas
      l'implémentation des clients.
      - **`ChatOrchestrationService::generateReply()`** : nouveau paramètre
        optionnel `?callable $onDelta`. Le choix streaming-vs-bufferisé se
        fait sur la présence de tools : `LlmClientInterface::stream()` est
        "texte brut sans tools" par contrat, donc un agent avec des workflows
        actifs prend toujours le chemin bufferisé existant (`complete()` +
        boucle tool-calling, inchangée). `$onDelta` est appelé au moins une
        fois dans tous les cas — sur le chemin bufferisé, une seule fois avec
        le contenu complet — pour que l'appelant (`ConversationStreamController`)
        ait un contrat uniforme ("zéro ou plusieurs deltas, puis fin") sans
        savoir quel chemin a été pris côté serveur. Logique extraite dans une
        méthode privée `orchestrate()` (accepte le `LlmClientInterface` déjà
        résolu) pour rester testable sans passer par `ProviderSelectionService`
        (`final`, aucun point d'injection pour lui faire résoudre un client
        factice).
      - **Usage estimé sur le chemin streaming** : `stream()` ne renvoie que
        du texte, jamais de compteurs de tokens ni provider/model (contrairement
        à `complete()`) — `TokenEsimator` utilisé en repli, `source: estimated`,
        même convention que les autres endroits où le provider ne renvoie pas
        ces infos.
      - **`ConversationStreamController`** : relaie chaque delta comme frame
        SSE `{type: 'delta', content: '...'}`, `ai_complete` reste inchangé
        (message complet sérialisé, lu côté frontend uniquement pour les
        métadonnées désormais).
      - **Frontend (`useChatbot.ts`)** : les frames `delta` construisent la
        bulle assistant en direct (poussée au premier delta, puis mutée en
        place — via la référence *réactive* obtenue après le `push`, pas
        l'objet brut créé avant, sinon Vue ne détecte pas les mutations
        suivantes) ; `ai_complete` ne fait plus que compléter les métadonnées
        (id réel, sources, tool_calls, feedback, tokenUsage) sur cette même
        bulle plutôt que d'en pousser une nouvelle. Repli défensif conservé
        si aucun delta n'arrive jamais (ne devrait jamais arriver vu que le
        backend en émet toujours au moins un). `TypingIndicator` masqué dès
        que la bulle live apparaît (`messages[messages.length-1]?.role ===
        'assistant'`) plutôt que de rester affiché en double.
      - **Bug trouvé et corrigé pendant le développement** : le premier jet de
        `generateStreamingReply()` oubliait de transmettre `$sources` au
        `ChatReplyResult` — les sources RAG auraient été silencieusement
        perdues sur toute réponse streamée. Repéré par le linter de l'éditeur
        (pas PHPStan), corrigé, couvert par un test dédié.
      - 4 tests ajoutés (`ChatOrchestrationServiceTest`, nouveau fichier —
        double `FakeLlmClient` fait main pour contrôler `stream()`/`complete()`,
        même raison que `ProviderSelectionService` : chemin streaming avec
        deltas incrémentaux, chemin bufferisé sans agent, chemin bufferisé
        avec agent+workflow actif malgré `onDelta` fourni, sources RAG
        préservées sur le chemin streaming) — suite passée de 77 à 81 tests.
        `phpstan.neon` : nouvelle règle `ignoreErrors` pour
        `staticMethod.dynamicCall` sur `tests/*` (faux positif PHPStan sur
        `createStub()`/`createMock()`, déjà 7 fois grandfathered dans la
        baseline avant cette règle — évite de re-baseliner chaque nouveau
        fichier de test utilisant cet idiome PHPUnit standard).
      - Vérifié en conditions réelles de bout en bout (`curl -N` à travers le
        proxy Nuxt) : dizaines de vraies frames `delta` token-par-token sur le
        chemin sans tools (`token_usage.source: estimated`) ; un seul `delta`
        avec le contenu complet sur le chemin avec agent+tools, y compris un
        vrai déclenchement de 2 outils (`enregistrer_identite` +
        `lister_creneaux_disponibles`, vrai appel à l'API Cal.eu) — aucune
        fuite de deltas pendant l'exécution des outils, `token_usage.source:
        provider` comme attendu. `quick-send` et l'endpoint JSON non-streaming
        (`ConversationMessagesController`) ne passent jamais `$onDelta` :
        strictement inchangés, vérifié aussi via `curl`.
- [x] **`quick-send` n'expose pas les `sources`** RAG (contrairement aux
      messages persistés) — **la prémisse s'est révélée fausse en creusant** :
      `QuickSendController` renvoyait déjà `sources` en clair depuis le
      commit `47c0bca`, *avant même* que `318b03a`
      n'introduise le flag `sources_hidden` sur les messages persistés
      (`MessageSerializer` — qui ne retire d'ailleurs jamais `sources`, il se
      contente d'ajouter ce flag). Le vrai écart : `quick-send` était le seul
      endpoint à répondre sans ce flag. Aligné en ajoutant
      `'sources_hidden' => true` à sa réponse JSON, même convention que les
      messages persistés. Vérifié via `curl` direct sur le backend
      (`POST /api/chat/quick-send`) : `sources` + `sources_hidden: true`
      tous deux présents. Suite PHPUnit toujours au vert.
- [x] **Recherche hybride** (BM25 + vecteurs) dans Qdrant plutôt que vecteur
      seul, pour améliorer la pertinence RAG — pas de "vraie" recherche BM25
      côté Qdrant (vecteurs creux/miniCOIL : demanderait un second
      vectorizer dans la stack, disproportionné ici). À la place :
      `VectorSearchService::search()` fusionne le résultat vectoriel Qdrant
      avec une recherche lexicale MariaDB **FULLTEXT** (nouvel index sur
      `document_chunk.content`, appliquée manuellement via `dbal:run-sql` +
      insertion dans `doctrine_migration_versions` — `doctrine:migrations:*`
      est cassé dans cet environnement, limite déjà documentée dans le
      migration baseline), combinées par **Reciprocal Rank Fusion** (k=60). Détails complets,
      limites connues (scoping par collection, filtres category_id
      seulement côté lexical, sémantique du champ `score`) dans
      `docs/backend/SPECIFICATION.md` §6.5. 13 tests ajoutés
      (`fuseResults()`/`lexicalSearch()` via réflexion sur méthodes privées,
      `Doctrine\DBAL\Connection`/`Result` stubbés). Vérifié en conditions
      réelles via `curl -X POST /api/vector/search` contre la vraie base
      Qdrant + MariaDB de dev (résultats vectoriels et lexicaux tous deux
      présents, scores et métadonnées cohérents).
- [ ] **Index FULLTEXT manquant en production** — le squash de migrations
      (`c498ca0`, "squash migrations into a single baseline") a supprimé par
      inadvertance l'`ALTER TABLE document_chunk ADD FULLTEXT INDEX
      document_chunk_content_fulltext (content)` introduit par `ebcc763`
      (l'item hybrid search ci-dessus). Sans lui, `lexicalSearch()` échoue
      silencieusement (log `warning`, dégradation vers vecteur seul) —
      symptôme observé en dev : `Lexical search failed... Can't find
      FULLTEXT index matching the column list`. Corrigé dans
      `backend/migrations/VersionBase.php` (up + down) et appliqué
      manuellement en dev via `dbal:run-sql`. **La base de production n'a pas
      reçu ce correctif** — appliquer `ALTER TABLE document_chunk ADD
      FULLTEXT INDEX document_chunk_content_fulltext (content)` en prod au
      prochain déploiement/migration.
- [x] **Étendre la couverture de tests** — `KnowledgeBase`, `VectorConnector`,
      `AiProvider` n'avaient aucun test. +48 tests (19 → 67, tous verts) :
      `TokenEstimatorTest`, `ProviderSelectionServiceTest` (sélection de
      provider : 0/1/N configs actives, fallback env, config inutilisable
      ignorée), `QdrantClientTest` (`MockHttpClient`), `VectorSearchServiceTest`
      (voir aussi l'item recherche hybride), `DocumentProcessorServiceTest`
      (extraction txt/html/json/docx via vrais fichiers temporaires,
      chunking avec chevauchement — zéro dépendance à mocker, le meilleur
      rapport valeur/effort de cette passe), `CollectionServiceTest`.
      **Limite structurelle rencontrée et documentée** : `QdrantClient`,
      `EmbeddingService`, `DocumentAnalysisService`, `ProviderSelectionService`
      sont tous `final` — PHPUnit ne peut pas les doubler
      (`ClassIsFinalException`). Contournement systématique : construire de
      vraies instances, avec un `MockHttpClient`/`Doctrine\DBAL` fake injecté
      à leur propre seam de constructeur plutôt que de doubler la classe
      elle-même. A empêché un `EmbeddingServiceTest` dédié (aucun seam pour
      intercepter le client HTTP réel que `ProviderSelectionService`
      construit en interne) — abandonné plutôt que forcé. Pas
      d'infrastructure de test avec vraie DB (`KernelTestCase`) dans ce repo
      — a aussi limité `AnalyticsService` (voir Dashboard analytics), testé
      manuellement contre les données réelles à la place.
- [x] **Endpoint de santé agrégé** (DB + Qdrant + Redis + Ollama en un seul
      appel) pour le monitoring/supervision — nouveau `GET /api/health`
      (`App\Controller\HealthController` + `App\ApiResource\HealthAction`,
      même schéma que `LlmStatusController`/`QuickSendController`). 4 checks
      indépendants et best-effort (aucun ne fait planter les autres) :
      `SELECT 1` (DB), nouvelle `QdrantClient::ping()` (`GET /collections`),
      `RedisAdapter::createConnection(REDIS_URL)->ping()` direct (pas via le
      pool de cache applicatif), `ProviderSelectionService::checkLlmStatus()`
      (déjà utilisé par `/api/chat/llm-status`, réutilisé tel quel). `200` si
      tout est `ok`/`running`/`reachable`, `503` sinon. Vérifié via `curl -u
      admin:*** http://symfony.chatbot.localhost/api/health` → `status: ok`
      avec les 4 checks au vert. Suite PHPUnit toujours au vert (aucun test
      dédié ajouté — même absence de couverture que `LlmStatusController`/
      `QuickSendController`, voir l'item couverture de tests ci-dessus).
- [x] **Workflow step `condition`** — seule l'action `set_field` était
      implémentée pour `true_action`/`false_action`. Étendu à `add_field` et
      `remove_field`, en réutilisant le vocabulaire déjà existant côté
      `data_transform` (`set`/`add`/`remove`) plutôt que d'inventer un
      second système : extraction d'une méthode partagée
      `applyFieldOperation()` dans `WorkflowExecutionService`, appelée à la
      fois par `handleDataTransform()` (boucle sur `transformations`) et
      `executeAction()` (l'action unique d'un `true_action`/`false_action`).
      5 tests ajoutés dans `WorkflowExecutionServiceTest` (branches
      true/false, les 3 opérations, type d'action inconnu = no-op) — suite
      complète passée de 14 à 19 tests, toujours au vert.
- [x] **Dashboard analytics** — usage de tokens, feedback 👍👎
      (`Message.feedback`), requêtes vectorielles (`SearchQuery`) : les
      données existaient déjà mais pas de vue agrégée exploitable. Nouveau
      `GET /admin/analytics` (`App\Controller\Admin\AnalyticsController` +
      `App\Chat\AnalyticsService::getDashboardStats()`) — conversations
      (total/actives), messages (total, par rôle), tokens consommés (somme
      de `metadata.token_usage.total_tokens` sur les messages assistant,
      calculée en PHP plutôt qu'en SQL — JSON, même choix que côté recherche
      lexicale), feedback (positif/négatif/sans retour + barre de
      répartition), recherches vectorielles (total, durée moyenne, nombre de
      résultats moyen, 10 plus récentes). Pas une ressource Sylius (rien à
      créer/éditer/supprimer, lecture seule) — contrôleur + template Twig
      directs, mais route nommée `app_admin_analytics_index` pour rester
      compatible avec la mise en évidence de la sidebar et
      `AdminExtension::nav()` telles qu'elles existent déjà. Détails complets
      dans `docs/backend/ADMIN.md`. Vérifié via une vraie session admin
      (`curl` avec cookie `PHPSESSID` obtenu via `POST /admin/login`) contre
      les données réelles de l'instance de dev : 31 conversations, 241
      messages, 286 387 tokens, 233 recherches vectorielles — tout s'affiche
      correctement. Pas de test automatisé dédié (agrégats DQL contre de
      vraies entités, pas d'infra `KernelTestCase`/DB dans ce repo — voir la
      note sur ce même sujet dans l'item couverture de tests ci-dessus).
- [ ] **2FA sur `/admin`** — aujourd'hui mot de passe seul (firewall
      `form_login` classique).
- [ ] **CAPTCHA/Turnstile léger sur `quick-send`** — le seul endpoint pensé
      pour des embedders tiers, donc le plus exposé à un abus anonyme malgré
      le rate-limiting déjà en place.
- [x] **Journal d'audit des actions admin** — qui a créé/modifié/supprimé
      quelle ressource (`AiProviderConfig`, `Faq`, `Workflow`...) et quand.
      Nouvelle table `audit_log` (migration `Version20260823120000`, appliquée
      manuellement via `dbal:run-sql` + insertion dans
      `doctrine_migration_versions` — même limite déjà documentée pour
      `Version20260822220000`, `doctrine:migrations:*` cassé dans cet
      environnement) + `App\EventListener\AuditLogListener`. Plutôt qu'un
      listener par entité/Grid, un seul abonné générique aux événements
      Sylius Resource déjà émis par tout `ResourceController`
      (`app.<resource>.post_create`/`post_update`/`pre_delete` — voir
      `vendor/.../Controller/EventDispatcher.php`), tagué pour les 10
      ressources qui exposent réellement create/update/delete dans
      `config/routes/admin.yaml`. Capture `action`, `resourceType`,
      `resourceId`, un `resourceLabel` best-effort (même repli
      `getName`/`getTitle`/`getQuestion`/`getEmail`/`__toString` que
      `AdminExtension::formatObject()`), et un `actorEmail` (snapshot, pas de
      FK vers `User` — reste lisible même après suppression du compte
      opérateur qui a agi, `User` ayant lui-même le CRUD complet).
      **Piège rencontré** : capturer la suppression sur `post_delete` renvoyait
      systématiquement `resourceId = null` — Doctrine réinitialise l'identifiant
      auto-généré à `null` sur l'entité en mémoire juste après que
      `UnitOfWork::executeDeletions()` a supprimé la ligne. Corrigé en
      écoutant `pre_delete` (avant la suppression réelle) pour cette seule
      action, seul point d'attention : si un futur listener stoppait la
      propagation sur `pre_delete`, une suppression annulée serait quand même
      journalisée (aucun listener de ce type n'existe aujourd'hui). Nouvelle
      page `GET /admin/audit-log` (`App\Controller\Admin\AuditLogController`,
      pas une ressource Sylius — lecture seule, pagination manuelle 50/page,
      même pattern que `AnalyticsController`), ajoutée à la sidebar
      (`AdminExtension::nav()`, nouveau groupe "Administration"). Vérifié en
      conditions réelles de bout en bout (session admin via `curl`) :
      création + modification + suppression d'une FAQ de test, les 3 lignes
      apparaissent dans `audit_log` avec le bon acteur, `resourceId` correct
      y compris sur la suppression après le correctif. Suite PHPUnit toujours
      au vert (77 tests) — pas de test dédié ajouté (même absence
      d'infrastructure `KernelTestCase`/DB que les autres items backend
      touchant des vraies entités, voir couverture de tests ci-dessus).
- [x] **`composer audit`/`npm audit` en CI** — déjà disponibles en local
      (`make audit`), pas encore branchés sur chaque PR. Nouveau workflow
      `.github/workflows/audit.yml` (`on: pull_request` + `workflow_dispatch`),
      deux jobs indépendants. `composer audit` tournait déjà côté backend mais
      uniquement comme étape de `deploy-backend.yml` (`push` sur `master`
      seulement, jamais sur une PR) — `npm audit` n'existait nulle part en CI.
      Les deux jobs auditent directement le fichier de lock (`composer audit
      --locked`, `npm audit --package-lock-only`) sans installer les
      dépendances au préalable — `composer audit`/`npm audit` sans ces flags
      auditent les paquets *installés* (`vendor`/`node_modules`), pas le lock
      file, ce qui aurait demandé un `composer install`/`npm ci` complet avant
      de pouvoir scanner. `npm outdated` inclus en amont, non bloquant
      (`continue-on-error: true`, même logique que le `;` plutôt que `&&`
      dans `make audit` — nouvelles majors, pas des CVE, ne doit pas faire
      échouer la PR). Vérifié : `actionlint` sur le nouveau fichier (aucune
      erreur), et les deux commandes exécutées telles quelles en local
      (`composer audit --locked` / `npm audit --package-lock-only`) — `0`
      vulnérabilité, exit code `0` dans les deux cas.
- [x] **Cache d'embeddings** pour les questions fréquentes/répétées — évite
      un aller-retour Ollama à chaque recherche identique. Nouveau
      `App\VectorConnector\QueryEmbeddingCache`, même schéma que
      `App\Chat\ConversationHistoryCache` (pool Redis dédié,
      `config/packages/cache.yaml`, `Symfony\Contracts\Cache\CacheInterface`)
      mais TTL plus long (7 jours au lieu d'1h) : un embedding pour un texte
      donné ne se périme pas de lui-même comme le fait l'historique de
      conversation à chaque tour — seul un changement de modèle/provider
      d'embedding le rendrait obsolète, accepté comme le même compromis que
      les chunks de documents déjà indexés (pas de ré-indexation automatique
      non plus si le modèle change, voir `POST /documents/{id}/process`).
      Clé de cache = hash (`xxh128`) du texte de la question normalisé
      (`trim` + minuscules), pour absorber les variations triviales de casse/
      espaces sur une question par ailleurs identique. Branché dans
      `VectorSearchService::search()`, seul point d'appel qui embedde une
      requête utilisateur (l'ingestion de documents, `addDocumentChunks()`,
      n'est pas concernée — un seul embedding par chunk, une seule fois).
      **Écart avec `EmbeddingService`/`QdrantClient`** : `QueryEmbeddingCache`
      est délibérément *non* `final` (contrairement à `ConversationHistoryCache`)
      pour rester "stubbable" dans `VectorSearchServiceTest` — même raison que
      `VectorSearchService` lui-même est non `final`. Suite PHPUnit toujours
      au vert (77 tests). Vérifié en conditions réelles contre le vrai Redis
      de dev (`POST /api/vector/search` x3 sur la même question avec casse/
      espaces différents) : une seule clé `query_embedding.*` créée pour les
      3 appels, ~200 ms de moins sur les appels en cache hit par rapport au
      premier (miss).
- [x] **Toolchain de qualité PHP** (PHPStan, PHP-CS-Fixer, Rector) — absente
      jusqu'ici (voir l'item couverture de tests plus haut, et le commentaire
      laissé dans `deploy-backend.yml` : "No PHPStan/static-analysis suite is
      configured yet"). Introduite via le skill Claude Code
      `netresearch/php-modernization-skill` (`AGENTS.md`,
      `vendor/agent-skills/`), installé par la méthode "dépôt GitHub direct"
      du plugin `netresearch/composer-agent-skill-plugin` — le paquet
      Packagist annoncé dans le README du skill n'existe pas
      (`composer require netresearch/php-modernization-skill` échoue, `404`
      confirmé via l'API Packagist).
      - **PHPStan niveau 9** (`phpstan.neon`), extensions Symfony/Doctrine/
        strict-rules/deprecation-rules (auto-activées par
        `phpstan/extension-installer`, un plugin Composer bloqué par défaut,
        autorisé explicitement). Baseline générée (`phpstan-baseline.neon`,
        461 erreurs pré-existantes absorbées) plutôt que de bloquer sur
        l'historique — tout le code *futur* est tenu au niveau 9, l'existant
        est grandfathered. `--memory-limit=1G` nécessaire (défaut 128M,
        crash sur ce volume de code).
      - **PHP-CS-Fixer** (`.php-cs-fixer.dist.php`, généré par la recette
        Symfony puis complété avec `@PER-CS` en plus de `@Symfony`, pour
        satisfaire `ruleset_includes_per_cs` demandé par le skill). Appliqué
        immédiatement sur tout le code existant (81/168 fichiers, purement
        cosmétique — espacement `fn()`, alignement de docblocks, corps de
        constructeur vide `{}` — vérifié avant application), pour que le
        script `cs:check` parte propre plutôt que déjà cassé dès le premier
        run.
      - **Rector** (`rector.php`, `LevelSetList::UP_TO_PHP_84` +
        `CODE_QUALITY`/`DEAD_CODE`/`TYPE_DECLARATION`). Pas de
        `rector/rector-symfony`/`rector/rector-doctrine`/`rector/rector-phpunit` :
        leurs dernières versions publiées exigent encore `rector/rector`
        ^0.x-1.x, incompatible avec le `rector/rector ^2.6` actuel — conflit
        de dépendances confirmé, abandonné plutôt que forcer un
        `--with-all-dependencies` qui aurait rouvert toute la contrainte
        Symfony 8.1. Appliqué sur 134 fichiers après revue du dry-run
        (décision explicite de l'utilisateur, notamment sur
        `SafeDeclareStrictTypesRector` — 60 fichiers, le seul changement du
        lot avec un vrai risque comportemental via la coercition stricte des
        types) : `declare(strict_types=1)` ajouté, classes à propriétés
        `readonly` marquées `readonly class`, syntaxe PHP 8.4 (`new
        Foo()->method()` sans parenthèses), callables de première classe,
        etc. Un second passage `cs:check` a trouvé 7 fichiers à réaligner
        (style introduit par Rector, différent de PHP-CS-Fixer) — corrigé.
      - **Scripts composer** ajoutés : `cs:fix`/`cs:check`, `phpstan`/
        `phpstan:baseline`, `rector`/`rector:check`, `qa` (bundle
        `cs:check` + `phpstan`).
      - **`deploy-backend.yml`** : les deux étapes `PHPStan` et
        `PHP-CS-Fixer` ajoutées à la suite de `composer audit`, à
        l'emplacement exact du commentaire qui les invitait.
      - **Environnement** : `python3`/`python3-venv`/`curl` + `uv` (script
        officiel Astral, `UV_INSTALL_DIR=/usr/local/bin`) ajoutés à
        `backend/Dockerfile` — requis par les scripts Python du skill
        (`introspect.py`/`verify_php_project.py`/`modernize_loop.py`), pas
        une dépendance runtime PHP/Symfony. `composer.skills.lock` (nouveau,
        généré par le plugin) doit être copié dans le Dockerfile **avant**
        le premier `composer install` de l'image — sinon build cassé
        (`composer.skills.lock is missing but extra.ai-agent-skills.sources
        is configured`).
      - **Bug rencontré dans `netresearch/composer-agent-skill-plugin`
        v2.1.1** : `composer skills:trust <pkg>` écrit la clé de confiance
        sous la forme `vendor/repo`, mais l'installateur vérifie en interne
        `direct:vendor/repo/nom-skill` — mismatch qui bloquait la
        matérialisation du skill même après confirmation. Contourné en
        ajoutant manuellement la clé au format attendu dans
        `extra.ai-agent-skill.allow-skills` de `composer.json`, en plus de
        celle écrite par la commande officielle.
      - Vérifié à chaque étape : suite PHPUnit (77 tests) après PHP-CS-Fixer
        *et* après Rector, `lint:container`/`lint:yaml`/`lint:twig`, et un
        test en conditions réelles (`quick-send` + `/api/health`, tous deux
        `200`) après l'ensemble des changements.
- [x] **Durcissement anti-injection de prompt sur le contenu RAG** — le
      contenu des chunks de documents était concaténé brut dans le prompt
      système, sans délimitation ni avertissement. N'importe quel fichier
      uploadé dans la base de connaissances pouvait donc contenir du texte
      conçu pour ressembler à une instruction ("ignore les consignes
      précédentes...") et potentiellement détourner le comportement de
      l'assistant. Nouveau `ChatOrchestrationService::buildDocumentsBlock()` :
      chaque extrait est entouré de balises `<extrait_document id="N">`, et
      le bloc entier est précédé d'une consigne explicite précisant que ce
      contenu vient de fichiers uploadés (pas de l'utilisateur ni de
      l'opérateur) et doit être traité uniquement comme donnée factuelle à
      citer/résumer, jamais comme instruction à suivre. **Limite assumée et
      documentée** (`docs/backend/SPECIFICATION.md` §12.1) : ceci réduit la
      probabilité qu'un modèle se laisse détourner, ça ne l'élimine pas —
      aucun garde-fou côté sortie (pas de modèle de modération séparé) pour
      rattraper ce qui passerait malgré tout. Pas de filtrage par regex de
      motifs "suspects" dans le contenu : ce genre d'heuristique est
      notoirement contournable et donne une fausse impression de sécurité,
      délibérément pas fait. 1 test ajouté (vérifie que le contenu d'un
      chunk contenant une tentative d'injection ressort bien délimité et
      encadré par la consigne dans le message système envoyé au LLM) — suite
      passée de 81 à 82 tests.
- [x] **En-têtes de sécurité HTTP sur le backoffice admin** — le durcissement
      CSP/HSTS précédent ne couvrait que le widget Nuxt public ; `/admin`
      (données métier réelles, plus sensible) n'avait aucun de ces
      en-têtes. Nouveau `App\EventListener\SecurityHeadersListener`
      (`kernel.response`, scopé aux requêtes principales sous `/admin`
      uniquement — jamais `/api`, une API JSON pour le proxy Nuxt/des
      scripts, pas une page de navigateur qui a besoin d'être protégée
      contre du contenu tiers). Pas de séparation dev/prod nécessaire ici
      contrairement au frontend (pas de websocket HMR à autoriser pour une
      app Twig rendue serveur). Tout le backoffice est self-hosté via
      AssetMapper — pas de Google Fonts ni aucune autre origine externe
      côté admin — donc `font-src`/`img-src` restent `'self'` (+ `data:`).
      `'unsafe-inline'` reste nécessaire sur `script-src` (le
      `<script type="importmap">` que rend l'appel `importmap()`) et
      `style-src` (un seul `style="width: …%"` dynamique dans
      `templates/admin/analytics/index.html.twig`, pas jugé pertinent de
      refactorer en variable CSS pour un seul endroit). 3 tests ajoutés
      (`SecurityHeadersListenerTest`, nouveau fichier — en-têtes bien posés
      sur `/admin`, absents sur `/api`, absents sur une sous-requête) —
      suite passée de 82 à 85 tests. Vérifié en conditions réelles : `curl
      -sI` sur `/admin/login` (en-têtes présents) et `/api/ai_agents`
      (absents, comme voulu), session admin réelle sur `/admin/analytics`
      (la page avec le style inline) toujours `200`.
- [x] **Cache du dashboard `/admin/analytics`** — revient sur une décision
      explicite antérieure ("No caching: admin-only, low-traffic page,
      cheap queries", commentaire de classe d'`AnalyticsService`) :
      `tokenUsageStats()` charge en réalité le `metadata` JSON de **tous**
      les messages assistant pour sommer les tokens en PHP (pas un agrégat
      SQL borné, voir cette méthode) — pas un problème au volume actuel de
      l'instance de dev, mais un coût qui grandit avec le nombre de
      messages sans plafond. Nouveau pool Redis dédié `cache.admin_analytics`
      (même schéma que `ConversationHistoryCache`/`QueryEmbeddingCache`,
      TTL 5 min — un dashboard "à jour à quelques minutes près" est un
      compromis acceptable face à recalculer plusieurs agrégats DQL à
      chaque vue de page). Clé de cache statique unique : aucune variation
      par visiteur, chaque admin voit les mêmes chiffres. Vérifié en
      conditions réelles contre le vrai Redis de dev : 177 ms au premier
      appel (calcul + mise en cache), ~45 ms ensuite (cache hit), clé
      `dashboard_stats` confirmée présente dans Redis après coup.
- [x] **Recherche/filtrage des messages dans une conversation admin** —
      `/admin/conversations/{id}` affichait déjà tous les messages, mais sans
      aucun moyen de les parcourir : une conversation longue (42 messages
      testée en conditions réelles) obligeait à tout scroller à l'œil. Nouveau
      contrôleur Stimulus `conversation-filter`
      (`assets/controllers/conversation_filter_controller.js`) : champ
      recherche (substring insensible à la casse) + `<select>` de rôle
      (affiché seulement si la conversation contient plus d'un rôle) — filtre
      purement côté client, la conversation est déjà entièrement dans le DOM,
      pas d'aller-retour serveur pour un filtrage aussi simple. Piège
      rencontré : le filtre `unique` de Twig n'existe pas dans ce projet
      (Twig 3.28 sans l'extension qui l'apporte) — dédoublonnage des rôles
      fait à la main via une boucle + `not in`. Vérifié en conditions réelles
      (vraie session admin) sur deux conversations réelles (42 et 14
      messages) : page 200, les 3 cibles Stimulus (`search`/`role`/`item`)
      bien présentes, `<select>` peuplé de `user`/`assistant`, asset JS servi
      (200) à son URL versionnée par AssetMapper.
- [x] **Annuler une réponse en cours au clavier (Échap)** — le widget de
      chat n'avait aucun raccourci clavier : Entrée pour envoyer marchait
      déjà nativement (`<input>` simple dans un `<form>`), mais rien
      n'interrompait une génération en cours. `useChatbot.ts` gagne un
      `AbortController` (`activeRequest`) passé en `signal` au `fetch` du
      flux SSE, et une fonction `cancelReply()` qui l'annule ; tout ce qui a
      déjà streamé (`liveMessage`) reste affiché tel quel, pas de retour
      arrière sur le contenu partiel. `Chatbot.vue` écoute Échap sur
      `window` et ferme la couche la plus au premier plan dans l'ordre :
      sélecteur d'emoji ouvert → plein écran → sinon annule la génération en
      cours (`isLoading`). Un nouveau test (`useChatbot.test.ts`, fetch stub
      qui ne se résout qu'à l'abandon du signal) vérifie qu'annuler ne pose
      pas d'erreur et laisse le fil dans un état cohérent — suite passée de
      33 à 34 tests.
- [x] **Champ de saisie multi-ligne** — le champ était un `<input type="text">`
      à une seule ligne : impossible de coller un extrait de code ou
      d'écrire un message sur plusieurs lignes sans que le texte défile
      horizontalement. Remplacé par un `<textarea>` auto-agrandissant
      (`resizeTextarea`, jusqu'à 120px puis défilement interne, remis à une
      ligne dès que `inputValue` se vide). Entrée envoie (`onInputKeydown`,
      `preventDefault` + `sendMessage()` — un `<textarea>` ne soumet jamais
      son formulaire sur Entrée nativement, contrairement à l'ancien
      `<input>`), Maj+Entrée insère un saut de ligne. `rounded-full` →
      `rounded-3xl` sur les deux variantes (pilule plein écran, barre du
      widget flottant) : identique en apparence à une ligne (le rayon
      coïncide avec `hauteur/2` à ce stade), mais évite la forme "stade"
      extrême une fois le champ étiré sur plusieurs lignes.
- [x] **Pastille "nouveau message"** — un message qui arrivait pendant que
      le visiteur avait remonté dans l'historique le ramenait brutalement en
      bas (`scrollToBottom()` inconditionnel). `useChatbot.ts` gagne un ref
      `autoScroll` : `Chatbot.vue` le désactive via `onMessagesScroll`
      (`@scroll` sur le conteneur, distance au bas > 48px) et le réactive
      automatiquement si le visiteur reredescend lui-même ; `scrollToBottom()`
      ne fait plus rien tant qu'il est désactivé. Un `watch` profond sur
      `messages` détecte alors qu'une réponse est arrivée pendant ce temps et
      affiche une pastille flottante "Nouveau message" plutôt que de forcer
      le défilement — cliquer dessus (`jumpToLatest`) réactive `autoScroll`
      et descend. Envoyer/réessayer/régénérer réactive aussi `autoScroll`
      d'office (action explicite du visiteur = "ramène-moi au direct"), donc
      la pastille n'apparaît jamais pour les propres messages du visiteur.
      Deux nouveaux tests (`useChatbot.test.ts`) vérifient le gate de
      `scrollToBottom()` et la réactivation par `sendMessage()` — suite
      passée de 34 à 36 tests.
- [x] **Actions de bulle révélées au survol** — les boutons
      écouter/copier/feedback/régénérer d'une bulle assistant étaient
      visibles en permanence, alourdissant visuellement le fil. Masqués par
      défaut à partir de `sm:` (`opacity-0`), révélés par
      `group-hover`/`group-focus-within` sur la bulle (classe `group`) —
      l'opacité (pas `hidden`/`display:none`) garde les boutons dans l'arbre
      d'accessibilité, donc toujours atteignables au clavier même invisibles.
      En dessous de `sm:` (tactile, pas de vrai survol) ils restent visibles
      en permanence, aucune régression mobile. Deux exceptions forcent
      `opacity-100` même hors survol : la confirmation "Copié !" (1.5s) et un
      feedback déjà actif (👍/👎 sélectionné) — un état posé par le visiteur
      ne doit pas disparaître simplement parce que la souris a quitté la
      bulle.
- [x] **Séparateurs de date** — une conversation restaurée après plusieurs
      jours (`restoreConversation`, id persisté en `localStorage`) affichait
      tous les messages à la suite avec juste `HH:mm`, sans repère de jour.
      `Chatbot.vue` calcule un `messageItems` (computed) qui insère un
      séparateur ("Aujourd'hui" / "Hier" / date complète en `fr-FR`, année
      incluse seulement si différente de l'année courante) à chaque
      changement de jour entre deux messages consécutifs.
- [x] **Regroupement des messages consécutifs** — deux messages du même
      rôle envoyés à la suite (ex. l'utilisateur qui complète sa question)
      s'affichaient avec le même espacement qu'entre deux tours de parole
      différents. `messageItems` calcule aussi `isGrouped` (même rôle que le
      message précédent, pas de séparateur de date entre les deux) ;
      `MessageBubble.vue` resserre sa marge (`mb-1` au lieu de `mb-3`) en
      conséquence. Avatar/horodatage/actions restent affichés sur chaque
      bulle malgré tout — seul l'espacement change, pas l'information
      disponible.
- [x] **Skeleton pendant la restauration de l'historique** — `restoreConversation`
      fait un aller-retour réseau avant d'afficher quoi que ce soit ; le
      panneau passait d'un écran vide à la conversation complète d'un coup.
      Nouveau `isRestoringHistory` (`useChatbot.ts`, `true` pendant l'appel
      à `GET /api/conversations/{id}/messages`, remis à `false` dans un
      `finally`) affiche 3 bulles `animate-pulse` (alignées comme de
      vrais messages) à la place. Vérifié en conditions réelles (Chrome
      headless, requête interceptée avec un délai de 4s) : le skeleton
      s'affiche bien pendant l'attente, à la bonne position (bas du
      panneau).
- [x] **Contrôle du son des notifications** — `playMessageSound()` se
      déclenchait à chaque réponse sans qu'il y ait de moyen de le couper
      depuis l'UI. `useNotificationSound.ts` gagne un état `muted`
      persisté en `localStorage` (même schéma que `useColorScheme.ts`) et
      un `toggleMuted()` ; `playMessageSound()` devient un no-op silencieux
      tant que `muted` est actif. Bouton dédié dans l'en-tête (widget et
      plein écran), icône haut-parleur/haut-parleur barré, `aria-pressed`.
      4 nouveaux tests (`useNotificationSound.test.ts`, nouveau fichier :
      valeur par défaut, lecture d'un choix persisté, toggle + persistance,
      pas d'erreur pendant que muet) — suite passée de 36 à 40 tests.
- [x] **Copier un bloc de code** — le seul bouton copier existant (`MessageBubble.vue`)
      copiait tout le message ; copier juste un extrait de code depuis une
      réponse qui contient plusieurs blocs était pénible. `marked` reçoit un
      `Renderer` custom qui surcharge uniquement `code` : appelle le
      renderer par défaut pour garder l'échappement HTML correct, puis
      enveloppe le `<pre><code>` résultant dans un `<div class="code-block-wrapper">`
      avec un bouton "copier" superposé. Pas de gestionnaire Vue possible
      sur un bouton injecté via `v-html` (et DOMPurify retire les
      attributs `on*` de toute façon) : un seul `@click` délégué sur le
      conteneur (`onContentClick`) route vers le bon bouton, lit le texte
      exact depuis le `<code>` voisin (pas de duplication dans un
      data-attribute) et anime l'icône en manipulant le DOM directement
      (imperatif, hors de l'arbre réactif de Vue). Vérifié en conditions
      réelles (Chrome headless, flux SSE simulé avec un bloc `js`) : bouton
      et wrapper bien présents, texte extrait exact (`code.textContent`).
- [x] **Focus automatique + raccourci `/`** — le champ de saisie n'était
      jamais focus automatiquement (ouverture du widget, chargement de
      `/chat`, après un envoi via clic sur le bouton plutôt qu'Entrée), et
      rien ne permettait d'y revenir rapidement depuis ailleurs sur la
      page. `focusInput()` (nouveau, `Chatbot.vue`) est appelé à
      `onMounted`, après `onSubmit`/`onInputKeydown`. Nouveau raccourci
      `/` sur `onKeydown` (même écouteur `window` qu'Échap) : ramène le
      focus dans le champ depuis n'importe où, sauf si le visiteur est
      déjà en train de taper ailleurs (`isTypingTarget` — évite de voler
      un `/` tapé légitimement dans le message ou la recherche d'emoji).
      Vérifié en conditions réelles (Chrome headless) : focus sur le
      `<textarea>` dès le montage, et à nouveau après un blur + `/`.
- [x] **Notification desktop en arrière-plan** — le son (voir plus haut)
      ne signale rien si le visiteur a changé d'onglet et est muet.
      `useChatbot.ts` gagne `ensureNotificationPermission()` (appelée
      depuis `sendMessage()`, donc toujours depuis un geste utilisateur —
      requis par la plupart des navigateurs ; demandée au plus une fois
      par montage, seulement si jamais tranchée) et `notifyIfHidden()`
      (déclenche une `Notification` seulement si `document.hidden` et la
      permission est accordée — jamais si l'onglet est déjà au premier
      plan). Clic sur la notification : `window.focus()` + fermeture.
      3 nouveaux tests (`Notification` global stubbé) — suite passée de
      40 à 43 tests.
- [x] **Aperçu de lien (titre + favicon)** — un lien dans une réponse
      s'affichait comme du texte brut souligné, sans contexte sur la
      destination. Nouvelle route Nitro `GET /api/link-preview`
      (`server/api/link-preview.get.ts`) : récupère `<title>`/favicon
      d'une URL externe **côté serveur** (jamais via le backend Symfony),
      avec des garde-fous SSRF — hôte privé/loopback/link-local rejeté
      (littéral IP ou résolution DNS), redirections suivies manuellement
      avec re-validation à chaque saut (`redirect: 'follow'` aurait laissé
      une URL publique rediriger vers une IP privée après coup), taille et
      délai bornés (200 Ko HTML / 100 Ko favicon, 5s, 3 redirections max).
      Reconnu comme relais de fetch public par nature (n'importe qui peut
      appeler la route avec n'importe quelle URL) — le DNS rebinding reste
      un angle non couvert, compromis assumé à l'échelle du projet, même
      catégorie que la CSP elle-même ("raisonnable mais pas complet").
      Le favicon est inliné en `data:` URI dans la réponse (pas d'URL
      externe directe) : la CSP du widget (`img-src 'self' data:`,
      `nuxt.config.ts`) bloquerait sinon l'image. Mis en cache 24h par URL
      (`defineCachedEventHandler`). Côté `MessageBubble.vue` : jusqu'à 3
      liens extraits par regex du HTML déjà rendu (pas de `DOMParser`, ce
      computed doit pouvoir tourner côté serveur), rien pendant le
      streaming (`isStreaming`). Nouveau `LinkPreviewCard.vue` charge
      l'aperçu au montage, ne s'affiche pas si l'aller-retour échoue ou si
      la page cible n'a pas de titre. Vérifié en conditions réelles :
      wikipedia.org (titre + favicon complets), example.com (favicon
      `data:,` vide géré sans erreur), et les 3 vecteurs SSRF testés en
      direct (localhost, hostname Docker interne `chatbot-symfony`, IP de
      métadonnées cloud `169.254.169.254`) tous bloqués — aucune fuite
      d'information, pas de plantage.
- [x] **Compteur de caractères** — aucun repère sur la longueur d'un
      message en train d'être tapé. `showCharCount` (`Chatbot.vue`)
      affiche `inputValue.length` en petit, discret, sous le champ, mais
      seulement au-delà de 500 caractères — pas de maximum imposé, un
      indicateur purement informatif pour un message qui commence à être
      long. Vérifié en conditions réelles (Chrome headless) : compteur
      absent à 5 caractères, affiche bien "501" à 501 caractères.
- [x] **Respect de `prefers-reduced-motion`** — les animations en boucle
      s'étaient accumulées au fil des features (frappe, curseur de
      streaming, pastilles de statut/écoute, badge de première visite,
      skeletons) sans jamais vérifier qu'elles se coupent pour un visiteur
      qui a demandé moins de mouvement côté OS — seul `animate-aura-drift`
      (fond des pages `/` et `/chat`) l'avait déjà. Audit complet du
      dossier `components/` : chaque `animate-bounce-slow`/`animate-blink`/
      `animate-pulse-dot`/`animate-pulse-ring`/`animate-pulse` gagne
      `motion-reduce:animate-none`. `animate-spin` (spinners de
      chargement) volontairement épargné : information fonctionnelle
      (opération en cours), pas décoration — même exception que la
      plupart des implémentations sérieuses de cette préférence.
      Transitions Vue et `transition-*` déclenchées par interaction non
      concernées non plus (mouvement bref/localisé, pas la catégorie
      visée). Vérifié en conditions réelles (Chrome headless,
      `emulateMediaFeatures('prefers-reduced-motion', 'reduce')`) :
      `animation-name` bien `none` sur le skeleton de restauration
      d'historique avec la préférence active, `aura-drift` bien présent
      sans elle — la bascule fonctionne dans les deux sens.
- [x] **Tableaux markdown défilants** — `prose-table` n'avait qu'une marge
      (`prose-table:my-2`), aucun `overflow-x-auto` : un tableau plus large
      que la bulle (`max-w-[80%]`) débordait franchement au lieu de
      défiler. `MessageBubble.vue` surcharge la méthode `table` du
      `Renderer` `marked` (même technique que le bouton copier des blocs
      de code) pour envelopper le `<table>` déjà rendu dans un
      `<div class="overflow-x-auto">` — `display: block` sur `<table>`
      casserait l'algorithme de mise en page tabulaire, d'où le wrapper
      plutôt qu'une classe directe. Bug réel trouvé en testant : contrairement
      à `code()`, `table()` parse le markdown inline de chaque cellule via
      `this.parser.parseInline()`, et le `Renderer` par défaut instancié à
      part (`defaultRenderer`) n'a jamais son `.parser` positionné par
      `marked` — chaque tableau plantait
      (`Cannot read properties of undefined (reading 'parseInline')`)
      jusqu'à copier `renderer.parser` dessus juste avant l'appel. Vérifié
      en conditions réelles (Chrome headless, tableau 6 colonnes) : rendu
      correct, `overflow-x: auto` bien appliqué au wrapper.
- [x] **Aperçu d'image inline** — extension du système d'aperçu de lien
      (`GET /api/link-preview`) : si l'URL pointe directement sur une
      image, tranché sur le `Content-Type` réel de la réponse (pas
      l'extension de l'URL — plus fiable, une image peut être servie sans
      extension par un CDN), la route lit jusqu'à 3 Mo et renvoie
      `imageDataUri` au lieu de titre/favicon — un seul aller-retour,
      inliné en `data:` URI pour la même raison que le favicon (CSP
      `img-src 'self' data:`). Nécessité de séparer la résolution de
      requête (validation d'hôte + suivi des redirections) de la lecture
      du corps (`resolvePublicResponse`/`readBounded`, refactor de l'ancien
      `fetchPublicResource` monolithique) : impossible de savoir combien
      d'octets lire avant de connaître le vrai `Content-Type`, qui n'arrive
      qu'après la résolution. `LinkPreviewCard.vue` bascule son rendu
      (`isImage`) en conséquence. Vérifié en conditions réelles (Chrome
      headless, logo Wikipedia) : image affichée correctement dans la
      carte.
- [x] ~~Mode compact~~ (bascule de densité d'affichage, `useCompactMode.ts`)
      — implémentée puis retirée à la demande explicite, sans
      remplacement.
- [x] **Séparateur de date collant** — "Aujourd'hui"/"Hier" défilait avec
      le contenu au lieu de rester visible pendant qu'on parcourt
      l'historique. Chaque séparateur passe en `position: sticky` (`top-12`
      en variante `page`, décalé sous la barre de navigation elle-même
      collante ; `top-0` en widget, qui n'en a pas) — façon WhatsApp/
      Telegram, chaque séparateur reste affiché en haut du défilement
      jusqu'à ce que le suivant le pousse. Aucun JS nécessaire, juste du
      CSS (plusieurs éléments `sticky` frères dans un même conteneur
      défilant s'empilent nativement dans l'ordre du DOM). Vérifié en
      conditions réelles (Chrome headless, 8 messages) : `position:
      sticky` bien appliqué sur le wrapper.
- [x] **Bouton "remonter en haut"** — symétrique de la pastille "nouveau
      message" existante. Le même `onMessagesScroll` (`Chatbot.vue`)
      pilote maintenant aussi `showScrollToTop` (au-delà de 400px depuis
      le haut) ; `jumpToTop()` fait un `scrollTo({ top: 0, behavior:
      'smooth' })` sur le conteneur. Vérifié en conditions réelles (Chrome
      headless, 8 messages) : bouton visible une fois scrollé, `scrollTop`
      bien à `0` après clic.

- [x] **Toast "connexion rétablie"** — `useOnlineStatus()` signalait déjà
      le passage hors ligne (bandeau d'erreur), mais rien ne confirmait le
      retour. Nouveau `watch` sur `isOnline` (`Chatbot.vue`) détectant
      spécifiquement la transition `false → true` (jamais au montage,
      `watch` non-`immediate` — se déclencher sur la valeur initiale
      aurait affiché le toast à chaque ouverture du widget) : toast affiché
      3s. Vérifié en conditions réelles (Chrome headless,
      `page.setOfflineMode` + événements `offline`/`online`) : toast bien
      présent après le retour en ligne.

- [x] **Raccourci `Cmd`/`Ctrl+K`** — ouvre/ferme le widget flottant
      (`StickyChatBubble.vue`) depuis n'importe où sur la page, pas
      seulement en cliquant la bulle — distinct du raccourci `/` déjà
      existant dans `Chatbot.vue`, qui ne fait que redonner le focus une
      fois le panneau déjà ouvert (le widget n'est même pas monté quand il
      est fermé). Réutilise directement `onBubbleClick()` (même logique
      que le clic : redirige vers `/chat` si une conversation existe déjà
      plutôt que de rouvrir un second fil). En documentant cette section,
      correction au passage de deux inexactitudes trouvées dans le cahier
      des charges (`docs/frontend/SPECIFICATION.md` §4.1/§5.2) : le
      composant s'appelle `StickyChatBubble.vue` (pas `ChatWidget.vue`,
      qui n'existe pas), et il n'y a pas de composable `useChatWidget()`
      partagé — `isOpen` est un simple `ref` local. Vérifié en conditions
      réelles (Chrome headless, page d'accueil) : `Ctrl+K` ouvre puis
      referme bien le widget.

- [x] **Navigation clavier dans le picker d'emoji** — seuls le clic et la
      recherche texte fonctionnaient. Roving tabindex sur la grille (un
      seul bouton dans l'ordre de tabulation à la fois, `focusedEmojiIndex`)
      avec un vrai déplacement du focus DOM (`.focus()`, pas un simple
      surlignage CSS) — flèches pour naviguer (`ArrowLeft`/`Right` ±1,
      `ArrowUp`/`Down` ±6 colonnes), Entrée/Espace insèrent nativement via
      le `@click` du bouton focus. `ArrowDown`/Entrée depuis le champ de
      recherche entre directement dans la grille au premier résultat.
      Vérifié en conditions réelles (Chrome headless) : focus déplacé du
      champ de recherche vers l'emoji d'index 0 puis 1 après
      `ArrowDown`/`ArrowRight`, anneau de focus visible sur la capture.

- [x] **Commandes slash** (`/effacer`, `/theme`, `/son`, `/compact`, `/ecran`,
      `/exporter`, `/surprise`, `/contact`, `/epingles`) — menu déclenché en
      tapant `/` en tout début de champ (filtré à la frappe, navigable au
      clavier), même style visuel que le sélecteur d'emoji. Toutes des
      actions locales déjà câblées sur des boutons existants, aucun appel
      réseau.
- [x] **Avatar animé** — animation `breathe` (Tailwind, scale 1→1.06→1, 4s,
      `motion-reduce:animate-none`) sur les avatars "M" de l'en-tête et de
      l'écran vide, au lieu de l'icône statique.
- [x] **Effet machine à écrire réel** — les deltas de streaming arrivent
      parfois par rafales (un mot entier d'un coup), rendu saccadé.
      `MessageBubble.vue` fait rattraper `displayedContent` au vrai contenu à
      un rythme fixe (3 caractères/20ms) au lieu de sauter directement au
      texte reçu ; se cale sur le contenu final dès la fin du streaming.
- [x] **Export de conversation** (Markdown) — `useChatbot().exportConversation()`
      génère un `Blob` + `<a download>` à partir de `state.messages` (auteur,
      horodatage, contenu — sans sources ni traces d'outils). Bouton dans les
      deux en-têtes, visible seulement si la conversation n'est pas vide.
- [x] ~~Question surprise~~ (bouton dé dans les barres de saisie, envoyait une
      FAQ aléatoire) — implémentée puis retirée à la demande explicite,
      sans remplacement.
- [x] **Épingler un message** — bouton sur chaque bulle (question ou
      réponse), persistance purement client (`localStorage`, aucun champ
      backend), ré-appliqué à la restauration d'une conversation. Panneau
      accessible depuis l'en-tête (badge avec le nombre d'épingles) listant
      les messages épinglés, clic pour défiler jusqu'à la bulle via son id
      DOM (`msg-{id}`).
- [x] **Aperçu au survol de la bulle flottante fermée** — `useChatbot.ts`
      sauvegarde un extrait (120 caractères) de chaque réponse assistant en
      `localStorage`, lu par `StickyChatBubble.vue` au montage pour remplacer
      le texte générique "Commencer la conversation" dans l'infobulle au
      survol, quand une conversation précédente existe déjà.
- [x] **Barre de progression indéterminée** — segment glissant en boucle
      (style GitHub/YouTube) posé sur le bord supérieur du panneau pendant
      `isLoading`, en plus du curseur clignotant existant. Pas soumis à
      `prefers-reduced-motion` (même exception que les spinners de
      chargement : signal fonctionnel, pas décoratif).
- [x] **Célébration après réservation confirmée** — effet ponctuel (pop +
      anneau de couleur accent qui s'étend et s'efface, une seule fois à
      l'insertion) sur la carte "✅ Entretien confirmé" existante dans
      `MessageBubble.vue`.
- [x] ~~Carte de contact `/contact`~~ (téléchargeait un `.vcf` minimal) —
      implémentée puis retirée à la demande explicite, remplacée
      par `/cv` (voir plus bas) plutôt que par un équivalent direct.
- [x] **Formulaire inline prénom/nom** — quand l'assistant demande l'identité
      du visiteur en texte libre, une petite carte (deux champs + bouton
      "Valider") apparaît sous sa dernière bulle au lieu d'obliger à taper
      une phrase. Détection par heuristique dans `Chatbot.vue` (le modèle
      n'émet un tool-call structuré qu'une fois les deux noms obtenus, voir
      `enregistrer_identite` / `WorkflowExecutionService::handleSetConversation`
      — rien à détecter avant), affichée seulement sur la toute dernière
      bulle assistant, jamais sur l'historique restauré ni pendant le
      streaming. La validation envoie "Je m'appelle {prénom} {nom}." comme un
      message normal — aucun changement backend, le tool-calling existant le
      traite comme du texte libre ordinaire. Le champ de saisie principal
      (les deux variantes) et le bouton d'envoi sont désactivés tant que la
      carte est affichée, pour éviter deux chemins concurrents pour répondre
      à la même question (taper librement *et* remplir la carte).
- [x] **Priorité d'affichage des FAQ** — nouveau champ `Faq.priority`
      (entier, 0 par défaut, migration manuelle `dbal:run-sql` +
      `doctrine_migration_versions` — `doctrine:migrations:*` toujours cassé
      dans cet environnement). `FaqActiveCollectionExtension` trie désormais
      la collection publique par `priority ASC` (avant : ordre non garanti).
      Colonne triable + tri par défaut dans la grille `/admin/faqs`, champ
      numérique dans le formulaire d'édition. Aucun changement frontend
      nécessaire (`useFaqs.ts` faisait déjà confiance à l'ordre renvoyé par
      l'API).
- [x] ~~Partage natif d'une réponse~~ (`navigator.share`) — implémentée puis
      retirée à la demande explicite, sans remplacement.
- [x] ~~Mode mains-libres~~ (lecture automatique de chaque réponse à voix
      haute) — implémentée puis retirée à la demande explicite,
      sans remplacement.
- [x] ~~Temps de réflexion affiché~~ (`duration_ms`, mesuré dans
      `ChatOrchestrationService::generateReply()`, affiché sous chaque bulle
      assistant) — implémentée puis retirée à la demande explicite,
      sans remplacement.
- [x] **États de progression pendant le tool-calling** — le chemin bufferisé
      (LLM → exécution d'un workflow → second appel LLM) n'émettait aucun
      `delta`, donc rien à part le `TypingIndicator` générique pendant cette
      fenêtre, parfois longue (ex. `planifier_entretien` : vrai appel API
      Cal.eu). `ChatOrchestrationService::generateReply()`/`orchestrate()`
      gagnent un `$onToolCall` optionnel, invoqué avec le nom du workflow
      résolu juste avant son exécution ; `ConversationStreamController`
      relaie ça en une frame SSE `type: tool_call`. Frontend :
      `useChatbot.ts` expose `toolCallLabel` (computed) qui traduit le nom
      interne (snake_case) vers une phrase française connue
      (`planifier_entretien`, `lister_creneaux_disponibles`,
      `enregistrer_identite`) avec repli générique ("Traitement en cours…")
      pour tout nom non reconnu — jamais le nom brut affiché, même logique
      que la carte "Entretien confirmé". `TypingIndicator.vue` gagne une prop
      `label` optionnelle affichée à côté des points. 1 test backend ajouté
      (`ChatOrchestrationServiceTest`, refactor du `FakeLlmClient` pour
      supporter des `CompletionResult` séquentiels sur plusieurs appels
      `complete()`) — suite passée de 85 à 86 tests. 2 tests frontend ajoutés
      (lecture d'un tool name connu vs inconnu, via un stub de flux SSE gaté
      sur deux `reader.read()` séparés pour observer l'état intermédiaire
      avant qu'il ne soit effacé par le `delta` suivant).
- [x] **Astuce de découverte des commandes** — ce widget a accumulé plusieurs
      raccourcis puissants (commandes slash, Cmd/Ctrl+K) qu'un visiteur ne
      devine jamais seul. Bandeau discret ("💡 Tape `/` pour les commandes
      rapides…, ou Cmd/Ctrl+K…") affiché une seule fois, tous
      visiteurs/sessions confondus (`localStorage`, `chatbot:hint_seen`),
      après la toute première réponse assistant reçue (`onMessage` de
      `useChatbot`) — le visiteur est déjà engagé à ce moment-là, contrairement
      à l'ouverture du panneau où rien ne s'est encore passé. Se referme
      manuellement, automatiquement après 8s, ou dès que le visiteur découvre
      `/` par lui-même (`watch` sur `showSlashMenu`).
- [x] **Commande `/cv`** — ouvre le vrai CV en ligne de Maxime
      (`https://www.maxime.bzh/cv-...pdf`, trouvé en suivant le lien fourni
      par lui — `maxime.bzh/fr/cv` — jusqu'au PDF réel qu'il sert) dans un
      nouvel onglet. Lien direct plutôt qu'une copie re-hébergée ou générée :
      ne devient jamais obsolète si le PDF change sur son site, et la base de
      connaissances RAG ne contient qu'une extraction `.txt` pensée pour
      l'indexation, pas un fichier présentable à un visiteur. Une première
      version téléchargeait une carte de contact `.vcf` à la place (voir plus
      haut) — retirée le même jour à la demande explicite, `/cv` ne l'a pas
      remplacée par un équivalent direct.
- [x] **Audit de sécurité + performance** (deux passes) — trouvé
      et corrigé : 9 contrôleurs API Platform à contrôleur personnalisé
      (`WorkflowStepsController`, `WorkflowTriggerController`,
      `WorkflowTestController`, `WorkflowSoftDeleteController`,
      `DocumentUploadController`, `DocumentDeleteController`,
      `DocumentProcessController`, `DocumentChunksController`,
      `TestAiProviderConfigController`) puis 2 de plus trouvés en deuxième
      passe (`VectorSearchController`, `VectorStatsController`) n'avaient
      **aucune** vérification d'autorisation propre — le `security:
      "is_granted('ROLE_ADMIN')")` déclaratif d'API Platform ne s'applique
      pas aux opérations à contrôleur personnalisé (même limite déjà connue
      et contournée pour `Conversation`, jamais étendue à ces 11-là).
      Confirmé exploitable en conditions réelles (`GET
      /api/workflows/1/steps` → `200` sans credential, config Cal.eu en
      clair) puis confirmé corrigé avec un compte `ROLE_USER` jetable créé
      puis supprimé après usage (`403` après le fix, `200` avant). Correctif :
      `#[IsGranted('ROLE_ADMIN')]` directement sur chacun des 11 contrôleurs.
      CSRF activé au passage sur le formulaire de login `/admin`
      (`enable_csrf: true` + `_csrf_token` dans `templates/admin/login.html.twig`,
      absent jusque-là alors que les formulaires CRUD Symfony l'ont par
      défaut). Détail complet dans `docs/backend/SPECIFICATION.md` §10 et
      §12.1 (y compris le risque résiduel documenté et délibérément non
      corrigé : énumération d'id de conversation entre visiteurs, via
      `OwnershipVoter` + le proxy Nuxt toujours authentifié en admin).
      **Découverte plus large en vérifiant le fix** : ces 11 correctifs ne
      fermaient pas la vraie faille exploitable, parce que le proxy Nuxt
      public (`frontend/server/api/[...path].ts`) s'authentifie en `ROLE_ADMIN`
      réel sur *chaque* requête entrante, quel que soit le visiteur — `GET
      /api/conversations` (75 conversations, noms + messages complets) et
      `GET /api/workflow_executions` (91 exécutions, emails de recruteurs)
      répondaient `200` sans aucun credential via ce même proxy.
      Correctif réel : allowlist stricte ajoutée en début de handler
      (`ALLOWED_ROUTES`, méthode + regex de chemin) — seule une dizaine de
      routes précises que le widget utilise réellement sont relayées, tout le
      reste reçoit `404` avant même d'appeler le backend. Vérifié : les
      fuites d'origine bien `404` désormais, chemins légitimes du widget
      toujours `200` (création de conversation + lecture de ses messages
      testée en réel, nettoyée après coup), ~10 tentatives de contournement
      (slash final, casse, `../`, verbe invalide, query string) toutes
      bloquées lors de la deuxième passe d'audit. Perf : N+1 probable sur 6
      grilles admin (`setPath('relation.champ')` sans `leftJoin`/`addSelect`)
      identifié mais **non corrigé** — impact jugé faible au volume actuel,
      laissé pour une session dédiée. `composer audit`/`npm audit` : 0
      vulnérabilité aux deux passes.
- [x] **Mode sombre appliqué à toute l'app** (2026-08-25) — le mode sombre
      (voir l'item plus haut) ne changeait en réalité que `Chatbot.vue` :
      `useColorScheme()` y était résolu indépendamment sur son propre
      conteneur racine, donc `pages/index.vue` (le hero, hors de
      `Chatbot.vue` tant que `StickyChatBubble.vue` n'est pas ouvert) ne
      passait jamais en sombre. Corrigé : `app.vue` résout lui aussi
      `useColorScheme()` et pose la classe `dark` sur son propre conteneur
      racine (aux côtés de `text-foreground`, pour que la couleur de texte
      hérite correctement — voir `docs/frontend/SPECIFICATION.md` §3.1/§4.2).
      Comme chaque instance de `useColorScheme()` a son propre état `ref`
      local, un toggle depuis le bouton de `Chatbot.vue` ne suffisait pas à
      lui seul à changer `app.vue` : `useColorScheme.ts` diffuse désormais
      chaque `toggle()` à toutes les instances montées dans le même onglet
      via un `CustomEvent` (`chatbot:color_scheme_change`) en plus de
      l'écriture en `localStorage` (l'event natif `storage` ne notifie que
      les *autres* onglets, jamais celui qui vient d'écrire). Vérifié par
      lecture directe du code (`frontend/` est gitignored, `git diff` ne
      montre rien pour ce dossier — vérification faite sur les fichiers
      réels, pas sur un diff) ; vérification visuelle déjà faite en dehors
      de cette passe de synchronisation documentaire.
- [x] **Avatar photo réel** (2026-08-25) — remplace l'avatar-lettre "M"
      partout où une identité visuelle apparaît : en-tête et écran vide de
      `Chatbot.vue`, `TypingIndicator.vue`, à côté de chaque réponse
      assistant dans `MessageBubble.vue` (nouveau — auparavant réservé à
      l'en-tête), et nouveau portrait du hero sur `pages/index.vue`. Photo
      réelle (`frontend/public/maximejolivet.jpg`), rendue via `<NuxtImg>`
      (nouvelle dépendance **`@nuxt/image`**, ajoutée aux `modules` de
      `nuxt.config.ts`) plutôt qu'un `<img>` brut, pour l'optimisation
      (`format="webp"`) et le dimensionnement déclaratifs. Au passage sur la
      home : le lien de contact `mailto:` a été retiré (et la clé i18n
      `home.contactLink` associée, devenue inutile), et le fond `hero-aura`
      (`tailwind.config.js`, keyframes `aura-drift`) a été intensifié — dérive
      organique à 4 points nettement plus visible plutôt que l'alternance à
      2 points d'origine, à peine perceptible. Vérifié par lecture directe du
      code (même limite `git diff` que ci-dessus).
- [x] **Suppression du mode plein écran par expansion du widget** (2026-08-25)
      — le bouton *plein écran* du widget flottant (`isFullscreen`, classe
      `fixed inset-4 z-[60] sm:inset-8`, icônes agrandir/réduire) est retiré
      entièrement, remplacé par une simple navigation `navigateTo('/chat')` :
      le widget avait déjà une vraie page plein écran dédiée (`pages/chat.vue`,
      `variant="page"`), l'expansion en place faisait doublon. Impacts en
      cascade tous vérifiés dans `components/Chatbot.vue` : la commande slash
      `/ecran` (`chatbot.slashFullscreen`) appelle désormais la même
      navigation au lieu de basculer `isFullscreen` ; la branche Échap qui
      fermait le mode plein écran a disparu de l'ordre des couches (désormais
      : sélecteur d'emoji → menu de commandes slash → panneau des épingles →
      annulation d'une génération en cours) ; clé i18n `chatbot.fullscreenExit`
      supprimée, `chatbot.fullscreenEnter`/`chatbot.slashFullscreen` reformulées
      pour décrire une navigation plutôt qu'une expansion. **Correction au
      passage** : l'entrée "Annuler une réponse en cours au clavier (Échap)"
      plus haut dans ce backlog décrit encore l'ancien ordre
      (`… → plein écran → …`) — description exacte au moment où elle a été
      écrite, obsolète depuis ce changement, volontairement laissée telle
      quelle comme trace historique plutôt que réécrite. `docs/frontend/
      SPECIFICATION.md` a été corrigé pour refléter le comportement actuel.
      Vérifié par lecture directe du code (même limite `git diff` que
      ci-dessus).
- [x] **Panneau des épingles qui restait ouvert et vide** (2026-08-25) — bug
      trouvé et corrigé dans `Chatbot.vue` : désépingler le dernier message
      épinglé *depuis le panneau lui-même* le laissait ouvert sur une liste
      vide, parce que le bouton d'en-tête qui permet normalement de le
      fermer est `v-if="pinnedMessages.length > 0"` — il disparaît au même
      instant que le dernier item, ne laissant plus aucun moyen de fermer le
      panneau autrement qu'Échap. Corrigé par un `watch(pinnedMessages, …)`
      qui referme `showPinnedList` dès que la liste devient vide. Vérifié
      par lecture directe du code (même limite `git diff` que ci-dessus).
- [x] **Passe accessibilité/responsive (audit mobile)** (2026-08-25) —
      plusieurs correctifs issus d'un audit mobile antérieur : cibles
      tactiles agrandies à 44px (`h-11 w-11`) sur la nav collante de la
      variante `page` et sur les boutons emoji/envoi du composeur
      (auparavant `h-8 w-8`/plus petits — sous la recommandation WCAG de
      44×44px) ; contraste renforcé en mode sombre sur les tokens
      `--destructive`/`--muted-foreground` (`assets/css/main.css`) ; les
      champs de saisie passent à `text-base sm:text-sm` pour éviter le
      zoom automatique de Safari iOS sur un `<input>`/`<textarea>` en
      dessous de 16px ; le composeur gagne un padding
      `env(safe-area-inset-bottom)` pour ne pas passer sous la zone
      d'encoche/barre gestuelle des mobiles récents. Sur la même lancée,
      la nav collante de la variante `page` (`/chat`) gagne un fond opaque
      (`bg-background`) sur mobile, transparent à partir de `sm:`
      (`sm:bg-transparent`) — sans ça, les bulles défilaient visuellement à
      travers elle sur petit écran. Vérifié par lecture directe du code
      (même limite `git diff` que ci-dessus) ; pas de vérification visuelle
      dans un vrai navigateur pendant cette passe de synchronisation
      documentaire (cette passe ne touche que `docs/`/`bruno`, jamais le
      code source frontend).

---

*Ce fichier est un backlog vivant : cocher au fur et à mesure, ajouter/retirer
des lignes librement.*
