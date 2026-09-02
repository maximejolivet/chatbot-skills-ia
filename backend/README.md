# Chatbot Backend (Symfony)

Backend du chatbot IA, en Symfony. Organisé en 5 domaines métier : `ai_providers`, `vector_connector`, `knowledge_base`, `workflows`, `chat`.

## Stack

- Symfony 8.1 + API Platform 4.3 (`/api`) pour exposer des ressources REST/JSON-LD à partir d'entités Doctrine
- NelmioApiDocBundle (`/doc`) pour une doc OpenAPI 3.0 « pure » (sans Hydra/JSON-LD), miroir automatique des ressources API Platform
- Doctrine ORM + Migrations, MariaDB 11.4
- Qdrant pour le stockage et la recherche vectorielle
- Redis pour le cache applicatif (`config/packages/cache.yaml` — voir §File d'attente async ci-dessous)
- Symfony HttpClient pour parler à Ollama / aux endpoints OpenAI-compatibles / à Qdrant
- smalot/pdfparser (PDF) + ZipArchive (DOCX) pour l'extraction de texte des documents
- Sylius Resource/Grid Bundle + Symfony Form pour le backoffice (`/admin`), Tailwind CSS (CDN) pour le style
- PHP 8.4

## Installation

### Avec Docker (recommandé)

```bash
cd backend
cp .env.example .env
docker network inspect chatbot-proxy >/dev/null 2>&1 || docker network create chatbot-proxy
docker compose up -d --build
```

Le réseau Docker externe `chatbot-proxy` est requis (le service `app` échoue à démarrer sans lui) — normalement créé automatiquement par `make start` depuis la racine du dépôt ; la commande ci-dessus le crée à la main si ce backend est lancé de façon isolée, sans passer par le `Makefile` racine (voir aussi [`../traefik/`](../traefik/)).

Une fois la stack up, créer un compte pour pouvoir se connecter à `/admin` ou `/api` (comptes multi-utilisateurs, table `app_user` — voir §Sécurité) :

```bash
docker exec chatbot-symfony php bin/console app:user:create <email> <mot-de-passe>
```

L'API est servie sur http://symfony.chatbot.localhost (via Traefik ; aucun port fixe n'est publié sur l'hôte), la documentation interactive sur `/api` (API Platform) et `/doc` (Swagger/OpenAPI pur), le backoffice sur `/admin`. `docker compose up` démarre aussi un frontend de démo Nuxt sur http://nuxt.chatbot.localhost (ou http://localhost:3010 ; service `nuxt`, voir [`frontend/README.md`](../frontend/README.md)).

### En local (PHP/Composer requis)

```bash
cd backend
cp .env.example .env
composer install
symfony server:start
```

Créer un compte admin (voir ci-dessus) une fois la base migrée.

## Domaines

| Domaine            | Rôle                                                                    |
| ------------------ | ----------------------------------------------------------------------- |
| `ai_providers`     | Abstraction des providers LLM/embedding et sélection du provider actif  |
| `vector_connector` | Wrapper Qdrant, embeddings, recherche vectorielle, analyse de documents |
| `knowledge_base`   | Documents, chunking, collections, catégories, FAQ                       |
| `workflows`        | Moteur d'exécution de workflows, utilisé comme outils par les agents    |
| `chat`             | Orchestration de la conversation : agents IA, RAG, tool-calling         |

### `ai_providers`

- `App\Entity\AiProviderConfig` — configuration des providers IA par usage (`chat`/`embedding`), exposée via `/api/ai_provider_configs` (CRUD complet)
- `App\AiProvider\Client\*` — abstractions transport (`LlmClientInterface`, `EmbeddingClientInterface`, DTOs `ChatMessage`/`ToolCall`/`ToolSpec`/`CompletionResult`/`EmbeddingResult`), implémentations `Ollama\*` et `ApiEndpoint\*` (OpenAI-compatible), toutes via Symfony HttpClient
- `App\AiProvider\ProviderSelectionService` — sélectionne le client actif par usage (config DB en priorité, sinon fallback sur les variables d'env `AI_*`/`OLLAMA_*`)
- `POST /api/ai_provider_configs/{id}/test` — teste en live une config et persiste `lastTestStatus`/`lastTestedAt`

### `vector_connector`

App feuille (aucune dépendance sur `knowledge_base`/`chat`) qui ne connaît que les noms de collection passés par l'appelant :

- `App\Entity\VectorIndex` — collection Qdrant connue de l'app, exposée via `/api/vector_indices` (CRUD complet)
- `App\Entity\SearchQuery` — log analytics des recherches, non exposé en CRUD direct, seulement visible via `/vector/stats`. Aucun champ `user` : pas de scoping par utilisateur
- `App\VectorConnector\QdrantClient` — wrapper REST (Symfony HttpClient) autour de Qdrant : `ensureCollection`, `upsert`, `search`, `delete`
- `App\VectorConnector\EmbeddingService` / `DocumentAnalysisService` — utilisent `ProviderSelectionService` d'`ai_providers` (embedding et analyse de document via le modèle Ollama dédié)
- `App\VectorConnector\VectorSearchService` — orchestration RAG (`search`, `addDocumentChunks`, `deleteDocumentChunks`), IDs de points déterministes via UUID v5 (`symfony/uid`)
- `POST /api/vector/search` — l'unique endpoint de recherche vectorielle
- `GET /api/vector/stats` — nombre d'index actifs, total de requêtes, 10 dernières requêtes

### `knowledge_base`

- `App\Entity\DocumentCategory` — CRUD complet via `/api/document_categories`. `App\Entity\Faq` — `GetCollection`/`Get` publics (`PUBLIC_ACCESS`) + `POST /api/faqs` (`ROLE_ADMIN`) via `/api/faqs` ; pas de `PATCH`/`DELETE` côté API, édition/suppression réservées au backoffice (`/admin/faqs`). Aucun champ `created_by` (pas de scoping par utilisateur)
- `App\Entity\Collection` — collection de documents optionnellement liée à un agent IA (`AiAgent`, `chat`) et/ou un `VectorIndex`, exposée via `/api/collections`
- `App\Entity\Document` / `DocumentChunk` — upload multipart (`POST /api/documents`), CRUD (`GET`/`PATCH`/`DELETE`), actions `POST /documents/{id}/process` (réindexation) et `GET /documents/{id}/chunks`. Aucun champ `uploaded_by` (même raison)
- `App\KnowledgeBase\DocumentProcessorService` — extraction de texte (PDF/TXT/DOCX/MD/HTML/JSON) + découpage en chunks avec chevauchement (1000/200 caractères)
- `App\KnowledgeBase\CollectionService` — résout/bootstrap la collection Qdrant d'un document : au lieu de retomber sur un nom de collection codé en dur quand aucune « collection commune » n'existe, la collection commune est créée à la volée dès qu'elle est nécessaire
- `App\KnowledgeBase\DocumentIndexingService` — orchestration chunk → vectorize → delete, branchée sur `vector_connector.VectorSearchService`. Appelée depuis `App\MessageHandler\IndexDocumentMessageHandler` (transport Messenger `async`, pas depuis la requête HTTP) — voir "File d'attente async" plus bas

### `workflows`

Les domaines `workflows` et `chat` se référencent mutuellement (`chat.services.ChatOrchestrationService` appelle `workflows.services.WorkflowExecutionService` pour le tool-calling, et `workflows.models.WorkflowExecution.conversation` référence `chat.models.Conversation`).

- `App\Entity\Workflow` / `WorkflowStep` — CRUD via `/api/workflows` (steps via `GET`/`POST /workflows/{id}/steps`, pas de ressource dédiée). Suppression = soft delete (`isActive=false`), pas de suppression réelle de la ligne
- `App\Entity\WorkflowExecution` — lecture seule (`/api/workflow_executions`, `GET`/`GetCollection` seulement), lié à la `Conversation` (`chat`) qui a déclenché l'exécution via tool-calling, le cas échéant. Champ `triggeredBy` (auto-renseigné, voir "Cloisonnement par utilisateur" plus bas) : un compte `ROLE_USER` ne voit que ses propres exécutions, `ROLE_ADMIN` voit tout
- `App\Workflow\WorkflowExecutionService` — le moteur d'exécution des steps (`api_call`/`webhook` via Symfony HttpClient, `data_transform`, `condition`, `delay`, `email` via Symfony Mailer, `notification` via webhook Slack/Discord-compatible si `webhook_url` est configuré, sinon loggé), avec substitution de placeholders `{{champ}}`
- `POST /api/workflows/{id}/trigger` et `POST /api/workflows/{id}/test` — asynchrones (transport Messenger `async`), répondent `202` avec l'exécution `pending` ; voir "File d'attente async" plus bas

### `chat`

Câble les champs différés des domaines précédents (`AiAgent.workflows`, `Collection.agent`, `WorkflowExecution.conversation`).

- `App\Entity\Conversation` / `Message` — CRUD sur `/api/conversations`, messages via `GET`/`POST /conversations/{id}/messages`, thumbs up/down via `PATCH /conversations/{id}/messages/{messageId}/feedback`. Cloisonnée par propriétaire (champ `user`, voir "Cloisonnement par utilisateur" plus bas) : un compte `ROLE_USER` ne voit/modifie que ses propres conversations, `ROLE_ADMIN` voit tout
- `App\Entity\AiAgent` — lecture seule côté REST (`GetCollection`/`Get` sur `/api/ai_agents`, pagination désactivée) ; géré en écriture via le [backoffice](#backoffice-admin) (`/admin/ai-agents`). Voir `getActiveWorkflows()`/`getCollection()`
- `App\Chat\ChatOrchestrationService` — la vraie boucle de tool-calling (jusqu'à 3 itérations) : demande une completion au LLM, si le modèle appelle un outil, exécute le `Workflow` correspondant via `workflows.WorkflowExecutionService`, réinjecte le résultat, redemande, jusqu'à obtenir une réponse finale
- `App\Chat\RagContextService` — résout la collection Qdrant de l'agent (`CollectionService::getQdrantCollectionNameForAgent`) et effectue la recherche vectorielle contextuelle
- `App\Chat\ChatService` — façade `sendMessage` (conversation persistée) / `quickSend` (anonyme, non persisté, ce que consomme le frontend de démo)
- `POST /api/conversations/{id}/messages`, `POST /api/conversations/{id}/stream` (SSE — vrai streaming token-par-token quand l'agent n'a aucun tool actif ; sinon chemin bufferisé, complétion générée puis émise en un seul `delta`, avec une frame `tool_call` en plus juste avant qu'un outil s'exécute pour signaler la progression — voir `docs/backend/SPECIFICATION.md` §5.5), `POST /api/chat/quick-send`, `GET /api/chat/llm-status`, `GET /api/chat/embedding-status`

Testé en réel de bout en bout : `quick-send` simple, mémoire conversationnelle sur plusieurs tours, SSE, **tool-calling réel** (un agent lié à un workflow `data_transform` a correctement déclenché l'outil et formulé sa réponse à partir du résultat), et **RAG réel** (un agent lié à une collection contenant un document indexé a restitué une information inventée présente uniquement dans ce document, prouvant que toute la chaîne agent → collection → Qdrant → recherche → injection dans le prompt fonctionne).

## Backoffice (`/admin`)

Construit avec **Sylius Resource Bundle** (CRUD générique piloté par config : routing, repository, formulaire) et **Sylius Grid Bundle** (définition des colonnes/actions des listes), avec des templates Twig maison (pas de thème Sylius packagé) stylés en **Tailwind CSS** (chargé via CDN, pas d'asset pipeline).

Les 13 ressources du domaine sont gérables : `AiProviderConfig`, `VectorIndex`, `DocumentCategory`, `Faq`, `Collection`, `Workflow` (+ `WorkflowStep` imbriqué), `AiAgent`, `Conversation`, `User` en CRUD complet ; `SearchQuery`, `WorkflowExecution`, `Message` en lecture seule (mêmes restrictions que côté API) ; `Document` en lecture/édition/suppression seulement (la création reste réservée à `POST /api/documents`, qui gère l'upload multipart et le pipeline d'indexation — pas reproduit dans un formulaire générique).

> [!IMPORTANT]
> `AiAgent` : son API REST est volontairement en lecture seule (`POST /api/ai_agents` répond `405`). Le backoffice (`/admin/ai-agents`) est donc le *seul* moyen d'en créer/modifier.

Architecture, pour ajouter une 14e ressource : une entité `implements Sylius\Resource\Model\ResourceInterface`, un repository avec `Sylius\Bundle\ResourceBundle\Doctrine\ORM\ResourceRepositoryTrait`, une classe `App\Form\XType`, une classe `App\Grid\XGrid` (`#[AsGrid]`), une entrée dans `config/packages/sylius_resource.yaml` et `config/routes/admin.yaml`. Les templates (`templates/admin/crud/*.html.twig`) et le rendu des champs (`App\Twig\AdminExtension::fieldValue()`, basé sur `PropertyAccessor` — gère nativement enums/dates/bools/relations/collections) sont partagés par toutes les ressources, sans rien à écrire de plus.

**Authentification requise.** `/admin` est protégé par Symfony Security (firewall `admin`, `config/packages/security.yaml`) : formulaire de login sur `/admin/login`, session cookie. Multi-utilisateur : chaque opérateur a son propre compte (table `app_user`, gérable dans `/admin/users`), voir §Sécurité ci-dessous.

**CSRF activé** (protection stateless par défaut de Symfony, `Symfony\Component\Security\Csrf\SameOriginCsrfTokenManager`) : chaque formulaire admin (créé via `form_start()`/`form_widget()`) embarque un champ `_token` avec `data-controller="csrf-protection"`. Ce contrôleur Stimulus (`assets/controllers/csrf_protection_controller.js`, fourni par `symfony/stimulus-bundle`, servi via AssetMapper) génère un cookie double-submit au moment de la soumission — sans lui, la validation retombe sur la vérification d'origine (`Sec-Fetch-Site`/`Origin`/`Referer`), donc reste protégée même si le JS échoue à charger, juste avec une garantie plus faible. Nécessite un asset pipeline pour servir ce contrôleur : voir §Asset pipeline ci-dessous. Les actions de suppression (gérées directement par Sylius, pas par le composant Form) utilisent en plus le CSRF **session-based classique** de Symfony (`csrf_token(id)` dans le Twig), indépendant de ce qui précède.

## Asset pipeline (AssetMapper + Tailwind local)

`symfony/asset-mapper` + `symfony/stimulus-bundle` + `symfonycasts/tailwind-bundle`, sans Node/npm. `assets/app.js` est l'entrypoint (`{{ importmap('app') }}` dans `templates/admin/layout.html.twig` et `login.html.twig`), qui importe `assets/styles/app.css` (`@import "tailwindcss";` + palette de marque via `@theme`, Tailwind v4 — remplace l'ancien `tailwind.config` inline chargé depuis le CDN). Compiler après toute modif de classes Tailwind utilisées dans les templates :

```bash
docker exec chatbot-symfony php bin/console tailwind:build
```

(`--watch` en développement actif). Les contrôleurs Stimulus dans `assets/controllers/*_controller.js` (dont `csrf_protection_controller.js`, fourni par le bundle) sont auto-découverts, pas besoin de les lister dans `assets/controllers.json`.

## Sécurité

Deux firewalls (`config/packages/security.yaml`), tous deux authentifiés contre la table `app_user` (`App\Entity\User`, provider `entity`, identifiant = email) :

- **`admin`** (`^/admin`) : `form_login` classique, session cookie. Page de connexion sur `/admin/login` (CSRF activé — `enable_csrf: true`, ajouté au passage d'un audit de sécurité, absent avant), déconnexion sur `/admin/logout`.
- **`api`** (`^/`, catch-all) : `http_basic`, `stateless: true`. Couvre `/api/*` et `/doc`. Pensé pour un client machine (curl, scripts, ou le proxy serveur d'un frontend) plutôt que pour un navigateur.

`access_control` exige `ROLE_ADMIN` sur `^/admin` (seule `^/admin/login` reste publique) mais seulement `ROLE_USER` (le rôle de base, tout compte authentifié l'a) sur `^/(api|doc)` — l'accès à `/api` en tant que tel n'est plus réservé aux admins, l'autorisation fine se fait ressource par ressource : `Conversation`/`WorkflowExecution` acceptent `ROLE_USER` mais restreignent chaque compte à ses propres lignes (voir "Cloisonnement par utilisateur" ci-dessous) ; toutes les autres ressources (`Document`, `Workflow`, `AiAgent`, `AiProviderConfig`, etc.) exigent explicitement `ROLE_ADMIN` sur leur propre `#[ApiResource(security: ...)]`, donc restent fermées aux comptes `ROLE_USER`. Créer un compte :

```bash
# Opérateur admin (par défaut) :
docker exec chatbot-symfony php bin/console app:user:create operateur@example.com 'un-mot-de-passe-solide'
# Compte restreint à ses propres conversations/exécutions :
docker exec chatbot-symfony php bin/console app:user:create client@example.com 'un-mot-de-passe-solide' --role=ROLE_USER
```

L'ancien compte admin unique (`ADMIN_USERNAME`/`ADMIN_PASSWORD_HASH` dans `.env`) a été repris tel quel comme premier compte `app_user` lors de la migration (même email `admin`, même hash) : les identifiants existants (dont `ADMIN_PASSWORD`, utilisé par le proxy du frontend Nuxt pour s'authentifier en Basic Auth au nom des visiteurs du widget) continuent de fonctionner sans rien changer. Générer un nouveau hash pour un compte :

```bash
docker exec chatbot-symfony php bin/console security:hash-password
```

`AiProviderConfig.apiKey` est `#[ApiProperty(readable: false)]` : jamais renvoyé par l'API, uniquement accepté en écriture. Même traitement pour `Conversation.user` et `WorkflowExecution.triggeredBy` (`#[ApiProperty(readable: false, writable: false)]`) : `User` n'a pas de groupes de sérialisation, donc les exposer sur l'API embarquerait le hash de mot de passe dans chaque réponse. Ces deux champs sont renseignés automatiquement à la création (`App\EventListener\UserStampListener`, sur `prePersist`) à partir de l'utilisateur authentifié sur la requête ; ils restent visibles dans le backoffice (Twig/`PropertyAccessor`, indépendant du serializer API).

## Cloisonnement par utilisateur

`Conversation`/`WorkflowExecution` implémentent `App\Entity\OwnedResourceInterface`
(`getOwnerUser()`, sur `user`/`triggeredBy` respectivement). Deux mécanismes,
un par type d'opération API Platform :

- **Item** (`Get`/`Patch`/`Delete`) : `security: "is_granted('OWNER', object)"`
  sur l'opération, vérifié par `App\Security\Voter\OwnershipVoter`
  (`ROLE_ADMIN` bypass toujours ; sinon compare `getOwnerUser()` au compte
  authentifié ; propriétaire `null` = admin uniquement, lignes d'avant le
  multi-utilisateur).
- **Collection** (`GetCollection`) : pas d'`object` unique à vérifier pour un
  Voter — `App\Doctrine\OwnershipCollectionExtension` filtre la requête DQL
  elle-même (`WHERE o.user = :current_user` sauf `ROLE_ADMIN`).
- **Opérations à contrôleur personnalisé** (`conversation_messages_*`,
  `conversation_stream`, `read: true` + `controller:`) : le `security:`
  déclaratif d'API Platform ne s'y applique pas de façon fiable (vérifié
  empiriquement) — ces contrôleurs (`ConversationMessagesController`,
  `ConversationStreamController`) utilisent à la place
  `#[IsGranted('OWNER', subject: 'data')]`, toujours appliqué par Symfony.

Toutes les autres ressources (`Document`, `Workflow`, `AiProviderConfig`,
`Collection`, `DocumentCategory`, `VectorIndex`) exigent explicitement
`ROLE_ADMIN` sur leur propre `#[ApiResource(security: ...)]`, indépendamment
de la règle `access_control` globale (voir §Sécurité) — un compte `ROLE_USER`
ne peut ni les lire ni les modifier. **Exceptions** : `AiAgent` et `Faq`
gardent `ROLE_ADMIN` par défaut au niveau ressource mais redéclarent
`GetCollection`/`Get` en `PUBLIC_ACCESS` — lecture ouverte à tout compte
authentifié, y compris `ROLE_USER` (l'`access_control` global `^/(api|doc)`
exige de toute façon `ROLE_USER`, voir §Sécurité). `Faq` expose en plus un
`POST` en `ROLE_ADMIN` (création possible via l'API depuis peu,
édition/suppression restant réservées au backoffice) ; `AiAgent` n'expose
aucune autre opération d'écriture via l'API.

> [!CAUTION]
> Ce `security:` de ressource ne s'applique pas aux opérations à contrôleur
> personnalisé (même limite que ci-dessus) — trouvé et corrigé lors d'un
> audit de sécurité : 11 contrôleurs (`Workflow`
> steps/trigger/test/soft-delete, `Document` upload/delete/process/chunks,
> `AiProviderConfig` test, `/vector/search`, `/vector/stats`) étaient
> accessibles par n'importe quel compte authentifié, `ROLE_USER` compris —
> et donc par n'importe quel visiteur du widget public, le proxy Nuxt
> s'authentifiant toujours en `ROLE_ADMIN` réel. Chacun porte désormais son
> propre `#[IsGranted('ROLE_ADMIN')]`. Détail complet, y compris le risque
> résiduel non corrigé (énumération d'id de conversation) : `docs/backend/SPECIFICATION.md` §10/§12.1.

## File d'attente async (Symfony Messenger + Redis)

Deux opérations tournent désormais en tâche de fond au lieu de bloquer la requête HTTP, via le transport `async` (`config/packages/messenger.yaml`, Redis) :

- **Indexation de documents** (`App\Message\IndexDocumentMessage` /
  `IndexDocumentMessageHandler`) : `POST /api/documents` et `POST
  /documents/{id}/process` répondent immédiatement (`202`, `status:
  "pending"`) au lieu d'attendre la fin du chunking/vectorisation — poller
  `GET /api/documents/{id}` pour le statut final (`indexed`/`error`).
- **Déclenchement de workflow** (`App\Message\ExecuteWorkflowMessage` /
  `ExecuteWorkflowMessageHandler`) : `POST /workflows/{id}/trigger` et
  `.../test` créent la `WorkflowExecution` (statut `pending`) et répondent
  `202` immédiatement — poller `GET /api/workflow_executions/{id}`.

**Exception délibérée** : quand un `Workflow` est déclenché par le LLM
via tool-calling (`App\Chat\ChatOrchestrationService`), l'exécution reste
**synchrone** (`WorkflowExecutionService::execute()`, appelé directement,
sans passer par le bus) — la boucle de tool-calling a besoin du résultat
immédiatement pour poursuivre la conversation ; la rendre asynchrone
casserait le tool-calling. Ce n'est pas un oubli.

En local, un service `worker` dédié (`backend/compose.yaml`, `php
bin/console messenger:consume async`) consomme en continu.

> [!CAUTION]
> Non transposable tel quel en production (pas de process persistant) : il
> faudra soit une tâche cron invoquant `messenger:consume --limit=N
> --time-limit=X` périodiquement, soit un Redis externe managé — voir
> [`docs/DEPLOYMENT.md`](../docs/DEPLOYMENT.md) pour le détail de l'hébergement,
> même logique que Qdrant Cloud pour la base vectorielle. En production,
> `MESSENGER_TRANSPORT_DSN` reste `sync://` (pas de worker persistant côté
> o2switch) même si un Redis externe existe désormais pour le cache
> ci-dessous — les deux usages sont indépendants, rien n'empêche de
> pointer Messenger vers ce même Redis plus tard sans passer par le cache.

## Cache applicatif (Redis)

Trois pools de cache dédiés (`config/packages/cache.yaml`, adapter
`cache.adapter.redis`, DSN commun `REDIS_URL`) — distincts du cache "app"
générique de Symfony (resté sur son adapter filesystem par défaut) pour ne
pas mélanger leurs cycles de vie :

- `cache.conversation_history` — `App\Chat\ConversationHistoryCache`, TTL 1h.
- `cache.query_embedding` — `App\VectorConnector\QueryEmbeddingCache`, TTL 7
  jours (un embedding ne devient obsolète que si le modèle/provider actif
  change, voir le docblock de la classe).
- `cache.admin_analytics` — `App\Chat\AnalyticsService`, TTL 5 min (dashboard
  `/admin/analytics`, évite de recalculer plusieurs agrégats DQL coûteux à
  chaque vue).

`REDIS_URL` est **requis** dès que l'un de ces pools est sollicité (pas de
repli silencieux) : en local, `docker compose` fournit un service `redis`
(`redis://redis:6379`, voir `.env.example`) ; en production, o2switch n'a pas
de Redis local — voir [`docs/DEPLOYMENT.md`](../docs/DEPLOYMENT.md) pour le
Redis externe managé (Upstash) utilisé là-bas, `rediss://` (TLS) obligatoire.
