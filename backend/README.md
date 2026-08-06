# Chatbot Backend (Symfony)

Backend du chatbot IA, en Symfony. Organisé en 5 domaines métier : `ai_providers`, `vector_connector`, `knowledge_base`, `workflows`, `chat`.

## Stack

- Symfony 8.1 + API Platform 4.3 (`/api`) pour exposer des ressources REST/JSON-LD à partir d'entités Doctrine
- NelmioApiDocBundle (`/doc`) pour une doc OpenAPI 3.0 « pure » (sans Hydra/JSON-LD), miroir automatique des ressources API Platform
- Doctrine ORM + Migrations, PostgreSQL 16
- Qdrant pour le stockage et la recherche vectorielle
- Symfony HttpClient pour parler à Ollama / aux endpoints OpenAI-compatibles / à Qdrant
- smalot/pdfparser (PDF) + ZipArchive (DOCX) pour l'extraction de texte des documents
- Sylius Resource/Grid Bundle + Symfony Form pour le backoffice (`/admin`), Tailwind CSS (CDN) pour le style
- PHP 8.4

## Installation

### Avec Docker (recommandé)

```bash
cd backend
cp .env.example .env
# Générer ADMIN_PASSWORD_HASH (voir §Sécurité) avant de lancer, sinon impossible de se connecter à /admin ou /api
docker network inspect chatbot-proxy >/dev/null 2>&1 || docker network create chatbot-proxy
docker compose up -d --build
```

Le réseau Docker externe `chatbot-proxy` est requis (le service `app` échoue à démarrer sans lui) — normalement créé automatiquement par `make up` depuis la racine du dépôt ; la commande ci-dessus le crée à la main si ce backend est lancé de façon isolée, sans passer par le `Makefile` racine (voir aussi [`../traefik/`](../traefik/)).

L'API est servie sur http://symfony.chatbot.localhost (via Traefik ; aucun port fixe n'est publié sur l'hôte), la documentation interactive sur `/api` (API Platform) et `/doc` (Swagger/OpenAPI pur), le backoffice sur `/admin`. `docker compose up` démarre aussi un frontend de démo Nuxt sur http://nuxt-symfony.chatbot.localhost (ou http://localhost:3010 ; service `nuxt`, voir [`frontend/README.md`](../frontend/README.md)).

### En local (PHP/Composer requis)

```bash
cd backend
cp .env.example .env
composer install
symfony server:start
```

## Domaines

| Domaine            | Rôle                                                              |
| ------------------ | ------------------------------------------------------------------ |
| `ai_providers`     | Abstraction des providers LLM/embedding et sélection du provider actif |
| `vector_connector` | Wrapper Qdrant, embeddings, recherche vectorielle, analyse de documents |
| `knowledge_base`   | Documents, chunking, collections, catégories, FAQ                  |
| `workflows`        | Moteur d'exécution de workflows, utilisé comme outils par les agents |
| `chat`             | Orchestration de la conversation : agents IA, RAG, tool-calling    |

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

- `App\Entity\DocumentCategory`, `Faq` — CRUD complet via `/api/document_categories`, `/api/faqs`. Aucun champ `created_by` (pas de scoping par utilisateur)
- `App\Entity\Collection` — collection de documents optionnellement liée à un agent IA (`AiAgent`, `chat`) et/ou un `VectorIndex`, exposée via `/api/collections`
- `App\Entity\Document` / `DocumentChunk` — upload multipart (`POST /api/documents`), CRUD (`GET`/`PATCH`/`DELETE`), actions `POST /documents/{id}/process` (réindexation) et `GET /documents/{id}/chunks`. Aucun champ `uploaded_by` (même raison)
- `App\KnowledgeBase\DocumentProcessorService` — extraction de texte (PDF/TXT/DOCX/MD/HTML/JSON) + découpage en chunks avec chevauchement (1000/200 caractères)
- `App\KnowledgeBase\CollectionService` — résout/bootstrap la collection Qdrant d'un document : au lieu de retomber sur un nom de collection codé en dur quand aucune « collection commune » n'existe, la collection commune est créée à la volée dès qu'elle est nécessaire
- `App\KnowledgeBase\DocumentIndexingService` — orchestration chunk → vectorize → delete, branchée sur `vector_connector.VectorSearchService`. **Limite connue (infra manquante)** : ce backend n'a pas de message queue (Redis/Messenger), donc tout le chunking/la vectorisation tourne en synchrone dans la requête — bloquant. À revoir si la latence d'upload devient un problème

### `workflows`

Les domaines `workflows` et `chat` se référencent mutuellement (`chat.services.ChatOrchestrationService` appelle `workflows.services.WorkflowExecutionService` pour le tool-calling, et `workflows.models.WorkflowExecution.conversation` référence `chat.models.Conversation`).

- `App\Entity\Workflow` / `WorkflowStep` — CRUD via `/api/workflows` (steps via `GET`/`POST /workflows/{id}/steps`, pas de ressource dédiée). Suppression = soft delete (`isActive=false`), pas de suppression réelle de la ligne
- `App\Entity\WorkflowExecution` — lecture seule (`/api/workflow_executions`, `GET`/`GetCollection` seulement), lié à la `Conversation` (`chat`) qui a déclenché l'exécution via tool-calling, le cas échéant. Aucun champ `triggered_by` (pas de scoping par utilisateur, donc pas de « l'utilisateur ne voit que ses executions »)
- `App\Workflow\WorkflowExecutionService` — le moteur d'exécution des steps (`api_call`/`webhook` via Symfony HttpClient, `data_transform`, `condition`, `delay`, `email`/`notification` en stub loggé uniquement), avec substitution de placeholders `{{champ}}`
- `POST /api/workflows/{id}/trigger` et `POST /api/workflows/{id}/test` — **Limite connue (infra manquante)** : sans message queue, les deux tournent en synchrone et renvoient l'exécution déjà terminée

### `chat`

Câble les champs différés des domaines précédents (`AiAgent.workflows`, `Collection.agent`, `WorkflowExecution.conversation`).

- `App\Entity\Conversation` / `Message` — CRUD sur `/api/conversations`, messages via `GET`/`POST /conversations/{id}/messages`. **Limite la plus significative** : aucun champ `user` — les conversations ne sont scopées par aucun utilisateur ; quiconque a les identifiants admin et connaît un id peut le lire/écrire
- `App\Entity\AiAgent` — lecture seule côté REST (`GetCollection`/`Get` sur `/api/ai_agents`, pagination désactivée) ; géré en écriture via le [backoffice](#backoffice-admin) (`/admin/ai-agents`). Voir `getActiveWorkflows()`/`getCollection()`
- `App\Chat\ChatOrchestrationService` — la vraie boucle de tool-calling (jusqu'à 3 itérations) : demande une completion au LLM, si le modèle appelle un outil, exécute le `Workflow` correspondant via `workflows.WorkflowExecutionService`, réinjecte le résultat, redemande, jusqu'à obtenir une réponse finale
- `App\Chat\RagContextService` — résout la collection Qdrant de l'agent (`CollectionService::getQdrantCollectionNameForAgent`) et effectue la recherche vectorielle contextuelle
- `App\Chat\ChatService` — façade `sendMessage` (conversation persistée) / `quickSend` (anonyme, non persisté, ce que consomme le frontend de démo)
- `POST /api/conversations/{id}/messages`, `POST /api/conversations/{id}/stream` (SSE — génère la réponse complète côté serveur puis l'émet en évènements, pas de streaming token-par-token combiné au tool-calling), `POST /api/chat/quick-send`, `GET /api/chat/llm-status`, `GET /api/chat/embedding-status`

Testé en réel de bout en bout : `quick-send` simple, mémoire conversationnelle sur plusieurs tours, SSE, **tool-calling réel** (un agent lié à un workflow `data_transform` a correctement déclenché l'outil et formulé sa réponse à partir du résultat), et **RAG réel** (un agent lié à une collection contenant un document indexé a restitué une information inventée présente uniquement dans ce document, prouvant que toute la chaîne agent → collection → Qdrant → recherche → injection dans le prompt fonctionne).

## Backoffice (`/admin`)

Construit avec **Sylius Resource Bundle** (CRUD générique piloté par config : routing, repository, formulaire) et **Sylius Grid Bundle** (définition des colonnes/actions des listes), avec des templates Twig maison (pas de thème Sylius packagé) stylés en **Tailwind CSS** (chargé via CDN, pas d'asset pipeline).

Les 13 entités du domaine sont gérables : `AiProviderConfig`, `VectorIndex`, `DocumentCategory`, `Faq`, `Collection`, `Workflow` (+ `WorkflowStep` imbriqué), `AiAgent`, `Conversation` en CRUD complet ; `SearchQuery`, `WorkflowExecution`, `Message` en lecture seule (mêmes restrictions que côté API) ; `Document` en lecture/édition/suppression seulement (la création reste réservée à `POST /api/documents`, qui gère l'upload multipart et le pipeline d'indexation — pas reproduit dans un formulaire générique).

**`AiAgent` mérite une mention particulière** : son API REST est volontairement en lecture seule. Le backoffice est donc le *seul* moyen d'en créer/modifier.

Architecture, pour ajouter une 14e ressource : une entité `implements Sylius\Resource\Model\ResourceInterface`, un repository avec `Sylius\Bundle\ResourceBundle\Doctrine\ORM\ResourceRepositoryTrait`, une classe `App\Form\XType`, une classe `App\Grid\XGrid` (`#[AsGrid]`), une entrée dans `config/packages/sylius_resource.yaml` et `config/routes/admin.yaml`. Les templates (`templates/admin/crud/*.html.twig`) et le rendu des champs (`App\Twig\AdminExtension::fieldValue()`, basé sur `PropertyAccessor` — gère nativement enums/dates/bools/relations/collections) sont partagés par toutes les ressources, sans rien à écrire de plus.

**Authentification requise.** `/admin` est protégé par Symfony Security (firewall `admin`, `config/packages/security.yaml`) : formulaire de login sur `/admin/login`, session cookie. Un seul compte admin (identifiants dans `.env`, voir §Sécurité ci-dessous) — pas de multi-utilisateur.

**CSRF désactivé délibérément** (`config/packages/csrf.yaml`) : la protection CSRF « stateless » activée par défaut par Symfony Flex nécessite le contrôleur Stimulus `csrf-protection`, qui nécessite un asset pipeline (Symfony UX/AssetMapper) non installé ici. Plutôt que de livrer des formulaires avec un token qui ne serait jamais rempli, la protection CSRF des formulaires est désactivée. Seule exception : les actions de suppression (gérées directement par Sylius, pas par le composant Form) utilisent le CSRF **session-based classique** de Symfony (`csrf_token(id)` dans le Twig), qui lui fonctionne sans JS.

## Sécurité

Deux firewalls (`config/packages/security.yaml`), un seul compte admin (`ADMIN_USERNAME`/`ADMIN_PASSWORD_HASH` dans `.env`, provider `memory` — pas de table `User`) :

- **`admin`** (`^/admin`) : `form_login` classique, session cookie. Page de connexion sur `/admin/login`, déconnexion sur `/admin/logout`.
- **`api`** (`^/`, catch-all) : `http_basic`, `stateless: true`. Couvre `/api/*` et `/doc`. Pensé pour un client machine (curl, scripts, ou le proxy serveur d'un frontend) plutôt que pour un navigateur.

`access_control` exige `ROLE_ADMIN` sur `^/admin` et `^/(api|doc)` ; seule `^/admin/login` reste publique. Générer/changer le mot de passe :

```bash
docker exec chatbot-symfony php bin/console security:hash-password
```

Puis mettre à jour `ADMIN_PASSWORD_HASH` (et `ADMIN_PASSWORD`, la contrepartie en clair utilisée par le proxy du frontend Nuxt pour s'authentifier en Basic Auth au nom des visiteurs du widget) dans `.env`.

`AiProviderConfig.apiKey` est `#[ApiProperty(readable: false)]` : jamais renvoyé par l'API, uniquement accepté en écriture.

## Limites connues

Tous documentées inline dans le code (recherchez `NOTE`/`Limite` dans les entités et services concernés) :

- **Pas de multi-utilisateur** : un seul compte admin partagé, aucun champ `user`/`uploaded_by`/`created_by`/`triggered_by` sur les entités. Conséquence : les conversations, executions et le backoffice `/admin` sont protégés par authentification, mais pas scopés par utilisateur (tout est visible/modifiable par quiconque a les identifiants admin)
- **Pas de message queue (Redis/Symfony Messenger)** : le chunking/vectorisation de documents (`knowledge_base`) et le déclenchement de workflows (`workflows`) tournent en synchrone dans la requête plutôt qu'en tâche de fond — bloquant

## Prochaines étapes possibles

- Authentification multi-utilisateur (entité `User`, rôles) pour remplacer le compte admin unique et introduire le scoping par utilisateur sur `Conversation`/`WorkflowExecution`
- File d'attente async (Symfony Messenger + Redis) pour le chunking/la vectorisation/le déclenchement de workflows
- Asset pipeline (AssetMapper) pour réactiver le CSRF stateless et remplacer le Tailwind CDN par un build local
