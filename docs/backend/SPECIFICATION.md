# Cahier des charges — Backend Symfony (`backend/`)

## 1. Présentation générale

`backend` est une API backend pour un **chatbot IA d'entreprise** combinant :

- un **moteur de conversation LLM** (chat completion, mémoire de conversation, streaming SSE) ;
- un système de **RAG** (Retrieval-Augmented Generation) basé sur **Qdrant** (base vectorielle) pour ancrer les réponses du LLM sur une base documentaire interne ;
- un système d'**agents IA** capables d'appeler des **outils** (tool-calling) qui déclenchent des **workflows** métier configurables (appels API, webhooks, transformations de données, conditions, etc.) ;
- un **backoffice** d'administration (CRUD) pour piloter les providers IA, les documents, les agents, les workflows, etc.

Le backend est bâti en **Symfony 8 / API Platform 4 / PHP 8.4**, organisé en 5 domaines métier. Multi-utilisateur (table `app_user`, rôles `ROLE_ADMIN`/`ROLE_USER`) avec cloisonnement par propriétaire sur `Conversation`/`WorkflowExecution` ; chunking de documents et déclenchement de workflow tournent en tâche de fond (Symfony Messenger). Une limite d'architecture reste assumée (pas de streaming token-par-token combiné au tool-calling) — voir [§12](#12-écarts-connus-limites-et-roadmap).

### 1.1 Les 5 domaines métier

| Domaine            | Rôle                                                                                                          | Namespace PHP         |
| ------------------ | ------------------------------------------------------------------------------------------------------------- | --------------------- |
| `ai_providers`     | Abstraction des providers LLM/embedding (Ollama, endpoints OpenAI-compatibles) et sélection du provider actif | `App\AiProvider`      |
| `vector_connector` | Wrapper Qdrant, génération d'embeddings, recherche vectorielle, analyse de documents                          | `App\VectorConnector` |
| `knowledge_base`   | Gestion des documents (upload, extraction de texte, chunking), collections, catégories, FAQ                   | `App\KnowledgeBase`   |
| `workflows`        | Moteur d'exécution de workflows (steps configurables) utilisé comme « outils » par les agents                 | `App\Workflow`        |
| `chat`             | Orchestration de la conversation : agents IA, RAG, tool-calling, historique                                   | `App\Chat`            |

Ordre de dépendance : `ai_providers` ← `vector_connector` ← `knowledge_base` ; `ai_providers` + `workflows` ← `chat` (qui référence aussi `knowledge_base` via les collections).

---

## 2. Stack technique

| Composant             | Technologie                                                                                          | Détail                                                                                                                                                                                                                                                                                                                            |
| --------------------- | ---------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Langage / runtime     | **PHP 8.4**                                                                                          | `php:8.4-cli` (image Docker)                                                                                                                                                                                                                                                                                                      |
| Framework             | **Symfony 8.1**                                                                                      | `framework-bundle`, `security-bundle`, `twig-bundle`, `console`, `dotenv`, `runtime`, `validator`, `serializer`, `form`, `expression-language`, `property-access`/`property-info`                                                                                                                                                 |
| Couche API REST       | **API Platform 4.3** (`api-platform/symfony`, `api-platform/doctrine-orm`)                           | Génère des ressources REST/JSON-LD (Hydra) à partir des entités Doctrine, exposées sous `/api`                                                                                                                                                                                                                                    |
| Documentation API     | **NelmioApiDocBundle 5.11**                                                                          | Miroir OpenAPI 3.0 « pur » (sans Hydra) des ressources API Platform, exposé sous `/doc`                                                                                                                                                                                                                                           |
| ORM / Base de données | **Doctrine ORM 3.6** + **Doctrine Migrations 4.0**                                                   | **MariaDB 11.4** (`mysql://...`)                                                                                                                                                                                                                                                                                                  |
| Backoffice / admin    | **Sylius Resource Bundle 1.14** + **Sylius Grid Bundle 1.16** + **Symfony Form**                     | CRUD générique piloté par configuration, exposé sous `/admin`                                                                                                                                                                                                                                                                     |
| Pagination admin      | **Pagerfanta** (`pagerfanta/doctrine-orm-adapter`, `babdev/pagerfanta-bundle`)                       | Utilisé par les grilles Sylius                                                                                                                                                                                                                                                                                                    |
| Base vectorielle      | **Qdrant** (image `qdrant/qdrant:v1.19.0`)                                                           | Stockage et recherche des embeddings, communication via REST (Symfony HttpClient)                                                                                                                                                                                                                                                 |
| LLM local             | **Ollama** (`OLLAMA_BASE_URL`)                                                                       | Chat + embeddings + analyse de documents, via l'API `/api/chat` d'Ollama (tool-calling natif)                                                                                                                                                                                                                                     |
| LLM distant           | **Endpoint OpenAI-compatible** (ex. OVHcloud AI Endpoints — `gpt-oss-120b`)                          | Format Chat Completions standard (`/v1/chat/completions`)                                                                                                                                                                                                                                                                         |
| Client HTTP           | **Symfony HttpClient**                                                                               | Utilisé pour tous les appels sortants : Ollama, endpoints OpenAI-compatibles, Qdrant, `api_call`/`webhook` des workflows                                                                                                                                                                                                          |
| Extraction de texte   | **smalot/pdfparser 2.12** (PDF), `ZipArchive` natif PHP (DOCX), fonctions natives (TXT/MD/HTML/JSON) | Pipeline d'ingestion documentaire                                                                                                                                                                                                                                                                                                 |
| CORS                  | **NelmioCorsBundle 2.6**                                                                             | Origines autorisées configurables via `CORS_ALLOW_ORIGIN` (regex)                                                                                                                                                                                                                                                                 |
| Style backoffice      | **Tailwind CSS v4** compilé localement (`symfonycasts/tailwind-bundle`)                             | Servi via `symfony/asset-mapper` + `symfony/stimulus-bundle` (`{{ importmap('app') }}`), plus de CDN                                                                                                                                                                                                                              |
| File d'attente        | **Symfony Messenger** + **Redis**                                                                   | Transport `async` (`redis://`, dev) ; chunking/vectorisation de documents et déclenchement de workflow (hors tool-calling) tournent en tâche de fond                                                                                                                                                                             |
| Conteneurisation      | **Docker** (`Dockerfile`, `compose.yaml`, inclus depuis le `compose.yaml` racine)                    | Services : `app` (Symfony), `database` (MariaDB), `qdrant`, `redis`, `worker` (consumer Messenger), `nuxt` (frontend de démo)                                                                                                                                                                                                     |
| Reverse proxy (dev)   | **Traefik**                                                                                          | Routage par domaine (`*.chatbot.localhost`) via provider **fichier** (`traefik/dynamic.yml`), pas par labels Docker — les services rejoignent le réseau externe `chatbot-proxy` mais ne portent aucun label `traefik.*` (le client Docker embarqué dans l'image Traefik échoue à négocier sa version d'API avec ce moteur Docker) |

---

## 3. Architecture applicative

### 3.1 Arborescence `src/`

```
src/
├── AiProvider/            # ai_providers : clients LLM/embedding + sélection du provider
│   ├── Client/
│   │   ├── ApiEndpoint/   # client "OpenAI-compatible" (chat + embeddings)
│   │   └── Ollama/        # client Ollama (chat + embeddings)
│   └── ProviderSelectionService.php
├── VectorConnector/        # vector_connector : Qdrant, embeddings, analyse, recherche
├── KnowledgeBase/          # knowledge_base : documents, chunking, collections
├── Workflow/                # workflows : moteur d'exécution des steps
├── Chat/                    # chat : orchestration LLM + RAG + tool-calling
├── Entity/                  # 16 entités Doctrine (voir §4) + l'interface OwnedResourceInterface
├── Security/Voter/           # OwnershipVoter : cloisonnement par propriétaire (Conversation/WorkflowExecution)
├── Doctrine/                  # OwnershipCollectionExtension : même cloisonnement, pour GetCollection
├── Message/                   # messages Messenger (IndexDocumentMessage, ExecuteWorkflowMessage)
├── MessageHandler/            # handlers correspondants, consommés par le worker Messenger
├── Enum/                    # enums PHP 8.1 pour chaque champ à choix fermé
├── Repository/               # repositories Doctrine (1 par entité)
├── Controller/               # contrôleurs API Platform custom + Admin/DashboardController
├── ApiResource/               # ressources API Platform "virtuelles" (non-entités) : quick-send, recherche/stats vectorielles, statut LLM/embedding, health check agrégé
├── Form/                      # formulaires Symfony pour le backoffice
├── Grid/                      # définitions de grilles Sylius (colonnes/actions des listes admin)
└── Twig/AdminExtension.php    # rendu générique des champs dans les templates admin
```

### 3.2 Flux de dépendance entre domaines

```
ai_providers  ──┐
                ├──> vector_connector ──> knowledge_base ──┐
                │                                          │
                └──────────────────────> chat <────────────┘
                                           ^
                                           │
                                       workflows
```

- `chat.ChatOrchestrationService` appelle `workflows.WorkflowExecutionService` pour exécuter les outils demandés par le LLM.
- `workflows.WorkflowExecution` référence `chat.Conversation` (la conversation ayant déclenché l'exécution).
- `chat.RagContextService` appelle `knowledge_base.CollectionService` (résolution de la collection Qdrant d'un agent), puis `vector_connector.VectorSearchService` (recherche).

---

## 4. Modèle de données

16 entités Doctrine, toutes avec un ID auto-incrémenté. Sauf indication contraire, `createdAt`/`updatedAt` sont gérés automatiquement (`#[ORM\HasLifecycleCallbacks]` + `#[ORM\PreUpdate]`).

### 4.1 `ai_providers`

**`AiProviderConfig`** — une configuration de provider IA, par usage.
| Champ                                       | Type                        | Remarque                                                                                    |
| ------------------------------------------- | --------------------------- | ------------------------------------------------------------------------------------------- |
| `name`                                      | string(200), unique         |                                                                                             |
| `usage`                                     | enum `AiProviderUsage`      | `chat` \| `embedding`                                                                       |
| `provider`                                  | enum `AiProvider`           | `ollama` \| `api_endpoint`                                                                  |
| `apiEndpoint`, `apiKey`, `model`, `baseUrl` | string, nullable            | dépend du provider                                                                          |
| `isActive`                                  | bool                        | pour `embedding`, une seule config active est prise en compte (`getActiveForUsage()`, la plus prioritaire) ; pour `chat`, **toutes** les configs actives sont utilisées comme chaîne de repli ordonnée (voir §5.2, `FallbackLlmClient`) |
| `isDefault`                                 | bool                        |                                                                                             |
| `lastTestStatus`                            | enum `AiProviderTestStatus` | `unknown` \| `success` \| `error`, mis à jour par `POST /api/ai_provider_configs/{id}/test` |
| `lastTestedAt`                              | datetime nullable           |                                                                                             |

### 4.2 `vector_connector`

**`VectorIndex`** — la référence côté base d'une collection Qdrant connue.
| Champ                          | Type                                                        |
| ------------------------------ | ----------------------------------------------------------- |
| `name` (unique), `description` | string                                                      |
| `collectionId`                 | string(100), unique — nom réel de la collection dans Qdrant |
| `dimension`                    | int, défaut `1024` (dimension de `mxbai-embed-large`)       |
| `isActive`                     | bool                                                        |
| `metadata`                     | array (JSON)                                                |

**`SearchQuery`** — log analytique de chaque recherche vectorielle exécutée.
| Champ           | Type                                     |
| --------------- | ---------------------------------------- |
| `query`         | string(500)                              |
| `vectorIndex`   | relation vers `VectorIndex`, obligatoire |
| `resultsCount`  | int                                      |
| `executionTime` | float (secondes)                         |
| `metadata`      | array                                    |

> [!NOTE]
> Non exposée en CRUD direct — consultable uniquement via `GET /vector/stats`. Aucun champ `user` : pas de scoping par utilisateur.

### 4.3 `knowledge_base`

**`DocumentCategory`** — `name` (unique), `description`. CRUD complet.

**`Faq`** — `question` (500), `answer` (text), `category` (nullable), `isActive`, `tags` (array), `priority` (int, défaut `0`, ordre d'affichage croissant), `isHighlighted` (bool, défaut `false` — sérialisé `highlighted` en JSON-LD, API Platform retire le préfixe `is` des getters booléens, comme `isActive` → `active`). `GetCollection(paginationEnabled: false)` + `Get` en `PUBLIC_ACCESS` — mêmes conditions que `AiAgent` — plus un `Post` réservé `ROLE_ADMIN` : une FAQ peut donc être créée via l'API, mais toujours pas modifiée/supprimée (pas de `Put`/`Patch`/`Delete`), édition/suppression restant réservées au backoffice. `App\Doctrine\FaqActiveCollectionExtension` exclut les FAQ `isActive = false` de la collection publique et trie le reste par `priority ASC` (la grille `/admin/faqs` les liste toutes, triées pareil par défaut, via une requête Sylius séparée qui ne passe pas par API Platform). Consommée côté frontend comme questions de conversation suggérées (`frontend/composables/useFaqs.ts`, sur la home et le panneau chat) — l'API renvoie toutes les FAQ actives, mais ce consommateur ne retient que celles avec `highlighted: true` (curation éditoriale distincte de `isActive`) ; toujours pas référencée dans `src/Chat/`, `src/KnowledgeBase/` ou `src/VectorConnector/` : un agent ne voit jamais ces entrées en répondant. Aucun champ `created_by` (pas de scoping par utilisateur).

**`Collection`** — un regroupement logique de documents, optionnellement lié à un agent et/ou un index vectoriel.
| Champ                          | Type                                                                                        |
| ------------------------------ | ------------------------------------------------------------------------------------------- |
| `name` (unique), `description` | string                                                                                      |
| `agent`                        | `OneToOne` vers `AiAgent`, nullable, `onDelete: CASCADE`                                    |
| `vectorIndex`                  | `ManyToOne` vers `VectorIndex`, nullable, `onDelete: SET NULL`                              |
| `isCommon`                     | bool — une seule collection « commune », bootstrappée à la volée (voir `CollectionService`) |
| `getCollectionNameForQdrant()` | dérive le nom réel de la collection Qdrant                                                  |

**`Document`** — un fichier source ingéré.
| Champ                    | Type                                                                           |
| ------------------------ | ------------------------------------------------------------------------------ |
| `title`, `description`   | string / text                                                                  |
| `filePath`               | string nullable — chemin relatif sous `var/uploads/documents/`                 |
| `fileType`               | enum `DocumentFileType` : `pdf` \| `txt` \| `docx` \| `md` \| `html` \| `json` |
| `category`, `collection` | relations nullable, `onDelete: SET NULL`                                       |
| `fileSize`               | int (octets)                                                                   |
| `status`                 | enum `DocumentStatus` : `pending` → `processing` → `indexed` \| `error`        |
| `processingError`        | text                                                                           |
| `metadata`               | array (inclut `embedding_usage` une fois indexé)                               |
| `chunks`                 | `OneToMany` vers `DocumentChunk`, `orphanRemoval: true`                        |

Aucun champ `uploaded_by` (pas de scoping par utilisateur).

**`DocumentChunk`** — un fragment de texte indexé (pas de `#[ApiResource]` propre, exposé via l'action `/documents/{id}/chunks`).
| Champ                                        | Type                                                   |
| -------------------------------------------- | ------------------------------------------------------ |
| `document`                                   | `ManyToOne`, `onDelete: CASCADE`                       |
| `content`                                    | text                                                   |
| `chunkIndex`, `startPosition`, `endPosition` | int                                                    |
| `vectorId`                                   | string(64) nullable — ID du point Qdrant correspondant |
| `metadata`                                   | array                                                  |

Contrainte d'unicité `(document_id, chunk_index)`.

### 4.4 `workflows`

**`Workflow`** — une définition de workflow, utilisable comme « outil » par un agent.
| Champ                          | Type                                                           |
| ------------------------------ | -------------------------------------------------------------- |
| `name` (unique), `description` | string                                                         |
| `triggerType`                  | enum `WorkflowTriggerType` : `manual` \| `api` \| `agent_tool` |
| `triggerConfig`                | array                                                          |
| `parametersSchema`             | array — JSON Schema exposé au LLM comme paramètres de l'outil  |
| `status`                       | enum `WorkflowStatus` : `draft` \| `active` \| `inactive`      |
| `isActive`                     | bool — soft delete                                             |
| `steps`                        | `OneToMany` vers `WorkflowStep`, ordonné par `order`           |
| `agents`                       | `ManyToMany` côté inverse vers `AiAgent`                       |

**`WorkflowStep`** — une étape d'un workflow.
| Champ           | Type                                                                                                                         |
| --------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| `workflow`      | `ManyToOne`, `onDelete: CASCADE`                                                                                             |
| `name`          | string(200)                                                                                                                  |
| `stepType`      | enum `WorkflowStepType` : `api_call` \| `email` \| `notification` \| `data_transform` \| `condition` \| `delay` \| `webhook` |
| `order`         | int — contrainte d'unicité `(workflow_id, order)`                                                                            |
| `configuration` | array (JSON, dépend du type)                                                                                                 |
| `isActive`      | bool                                                                                                                         |

**`WorkflowExecution`** — une trace d'exécution (lecture seule via l'API : `GetCollection`/`Get` uniquement).
| Champ                      | Type                                                                                              |
| -------------------------- | ------------------------------------------------------------------------------------------------- |
| `workflow`                 | `ManyToOne`, `onDelete: CASCADE`                                                                  |
| `conversation`             | `ManyToOne` nullable vers `Conversation`, `onDelete: SET NULL`                                    |
| `inputData`, `outputData`  | array                                                                                             |
| `status`                   | enum `WorkflowExecutionStatus` : `pending` \| `running` \| `completed` \| `failed` \| `cancelled` |
| `startedAt`, `completedAt` | datetime nullable                                                                                 |
| `errorMessage`             | text                                                                                              |
| `executionLog`             | array — détail par étape (statut, sortie, temps d'exécution)                                      |
| `triggeredBy`               | `ManyToOne` nullable vers `User`, `onDelete: SET NULL` — non lisible/écrivable via l'API (voir §4.6) |

Cloisonné par propriétaire : un compte `ROLE_USER` ne voit/modifie que les exécutions dont il est `triggeredBy` (`OwnershipVoter` + `OwnershipCollectionExtension`, voir §10) ; `ROLE_ADMIN` voit tout. Une exécution `triggeredBy = null` (déclenchée avant l'ajout du multi-utilisateur, ou par le tool-calling) n'est visible que par `ROLE_ADMIN`.

### 4.5 `chat`

**`Conversation`** — CRUD complet.
| Champ      | Type                                                                       |
| ---------- | -------------------------------------------------------------------------- |
| `title`    | string(200)                                                                |
| `isActive` | bool                                                                       |
| `visitorFirstName`, `visitorLastName` | string(100) nullable, `#[ApiProperty(writable: false)]` — jamais renseignés directement par un client API ; conçus pour être réglés en interne par l'étape de workflow `set_conversation` (voir §7.2) une fois l'agent en tool-calling capable d'extraire l'identité du visiteur, et réinjectés dans le prompt système par `ChatOrchestrationService::buildMessages()` pour que le modèle ne redemande jamais le nom déjà connu (voir §5.3). Aucun workflow actif n'utilise `set_conversation` actuellement -- `planifier_entretien` capture `attendee_name`/`attendee_email` directement comme arguments d'outil plutôt que via cette voie, donc ces deux champs restent `null` en pratique |
| `messages` | `OneToMany` vers `Message`, ordonné par `createdAt`, `orphanRemoval: true` |
| `user`     | `ManyToOne` nullable vers `User`, `onDelete: SET NULL` — non lisible/écrivable via l'API (voir §4.6) |

Cloisonné par propriétaire : un compte `ROLE_USER` ne voit/modifie (y compris messages/stream) que ses propres conversations (`OwnershipVoter` + `OwnershipCollectionExtension` sur l'item/la collection, `#[IsGranted]` sur les contrôleurs de messages/stream, voir §10) ; `ROLE_ADMIN` voit tout. Une conversation `user = null` (créée avant l'ajout du multi-utilisateur) n'est visible que par `ROLE_ADMIN`.

**`Message`** — pas de `#[ApiResource]` propre (exposé via les actions de `Conversation`).
| Champ          | Type                                                                                          |
| -------------- | ----------------------------------------------------------------------------------------------- |
| `conversation` | `ManyToOne`, `onDelete: CASCADE`                                                                |
| `role`         | enum `MessageRole` : `user` \| `assistant` \| `system` \| `tool`                                |
| `content`      | text                                                                                             |
| `metadata`     | array — contient `token_usage`, `tool_calls` et `sources` (documents RAG utilisés) pour les messages assistant |
| `feedback`     | enum `MessageFeedback` nullable : `positive` \| `negative` — thumbs up/down opérateur, `null` par défaut, réglé via `PATCH /conversations/{id}/messages/{messageId}/feedback` |

**`AiAgent`** — lecture seule via REST (`GetCollection(paginationEnabled: false)` + `Get` uniquement), écriture réservée au backoffice.
| Champ                          | Type                                                                                                              |
| ------------------------------ | ----------------------------------------------------------------------------------------------------------------- |
| `name` (unique), `description` | string                                                                                                            |
| `systemPrompt`                 | text — remplace le prompt système par défaut si non vide                                                          |
| `workflows`                    | `ManyToMany` vers `Workflow` (table `ai_agent_workflow`) — `getActiveWorkflows()` filtre les workflows `isActive` |
| `collection`                   | `OneToOne` côté inverse vers `Collection` — la base de connaissance RAG de l'agent                                |
| `isActive`                     | bool                                                                                                              |

### 4.6 Sécurité (transverse)

**`User`** (table `app_user`) — un compte opérateur, pour les firewalls `/admin` et `/api`. Non exposée via l'API (`#[ApiResource]` absent — porterait le hash de mot de passe), gérée uniquement via `/admin/users` et `bin/console app:user:create`.
| Champ      | Type                                              |
| ---------- | -------------------------------------------------- |
| `email`    | string(180), unique — identifiant                  |
| `password` | string — hash bcrypt                               |
| `roles`    | array (JSON) — `getRoles()` ajoute toujours `ROLE_USER` ; `ROLE_ADMIN` est stocké explicitement |
| `isActive` | bool                                               |

**`AuditLog`** (table `audit_log`) — une ligne par création/modification/suppression sur une ressource gérée par le backoffice. Append-only, non exposée via l'API (pas de `#[ApiResource]`), lecture seule via `/admin/audit-log` (`AuditLogController`, voir `docs/backend/ADMIN.md`). Alimentée par `App\EventListener\AuditLogListener` (événements génériques `app.<resource>.post_create`/`post_update`/`pre_delete` — voir §10).
| Champ           | Type                                                                          |
| ---------------- | ----------------------------------------------------------------------------- |
| `action`         | string(20) — `create` \| `update` \| `delete`                                |
| `resourceType`   | string(60)                                                                    |
| `resourceId`     | string(40) nullable                                                           |
| `resourceLabel`  | string(255) nullable — libellé best-effort (`getName`/`getTitle`/... selon l'entité) |
| `actorEmail`     | string(180) nullable — instantané, pas une FK vers `User`                     |
| `occurredAt`     | datetime immuable                                                             |

`OwnedResourceInterface` (`getOwnerUser(): ?User`, `getOwnerFieldName(): string`) est implémentée par `Conversation` (`user`) et `WorkflowExecution` (`triggeredBy`) — voir §10 pour le mécanisme de cloisonnement.

### 4.7 Enums PHP (backed enums, valeurs string)

| Enum                      | Valeurs                                                                                |
| ------------------------- | -------------------------------------------------------------------------------------- |
| `AiProvider`              | `ollama`, `api_endpoint`                                                               |
| `AiProviderUsage`         | `chat`, `embedding`                                                                    |
| `AiProviderTestStatus`    | `unknown`, `success`, `error`                                                          |
| `DocumentFileType`        | `pdf`, `txt`, `docx`, `md`, `html`, `json`                                             |
| `DocumentStatus`          | `pending`, `processing`, `indexed`, `error`                                            |
| `MessageRole`             | `user`, `assistant`, `system`, `tool`                                                  |
| `MessageFeedback`         | `positive`, `negative`                                                                |
| `WorkflowExecutionStatus` | `pending`, `running`, `completed`, `failed`, `cancelled`                               |
| `WorkflowStatus`          | `draft`, `active`, `inactive`                                                          |
| `WorkflowStepType`        | `api_call`, `email`, `notification`, `data_transform`, `condition`, `delay`, `webhook`, `set_conversation` |
| `WorkflowTriggerType`     | `manual`, `api`, `agent_tool`                                                          |

---

## 5. Fonctionnalités LLM

### 5.1 Abstraction des providers

Deux interfaces (`App\AiProvider\Client\*`) découplent le reste de l'app du provider concret :

- **`LlmClientInterface`** : `complete(messages, tools?, temperature, maxTokens): CompletionResult`, `stream(messages, ...): iterable<string>`, `checkStatus(): array`.
- **`EmbeddingClientInterface`** : `embed(text): EmbeddingResult`, `embedBatch(texts[]): EmbeddingResult[]`, `checkStatus(): array`.

Deux implémentations pour chaque interface :

| Provider                                                                                        | Chat                                                                                              | Embedding                               | Transport          |
| ----------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------- | --------------------------------------- | ------------------ |
| **Ollama** (`OllamaLlmClient`, `OllamaEmbeddingClient`)                                         | `POST {OLLAMA_BASE_URL}/api/chat` (`stream: false`, supporte `tools`)                             | `POST {OLLAMA_BASE_URL}/api/embeddings` | Symfony HttpClient |
| **Endpoint OpenAI-compatible** (`OpenAiCompatibleLlmClient`, `OpenAiCompatibleEmbeddingClient`) | Format *Chat Completions* standard (`/v1/chat/completions`), header `Authorization: Bearer <key>` | Format *Embeddings* standard            | Symfony HttpClient |

Détails d'implémentation notables :
- Le client Ollama utilise **`/api/chat`** (pas `/api/generate`) car c'est le seul endpoint Ollama supportant `tools` et retournant `message.tool_calls`, requis par le vrai tool-calling.
- Normalisation JSON Schema : un tableau PHP vide (`properties: []`) se sérialise en `[]` JSON, alors que les providers attendent `{}` — les deux clients corrigent ça avant l'envoi (`normalizeJsonSchema`).
- Le comptage de tokens utilise l'usage renvoyé par le provider quand disponible (`source: provider`), avec repli sur une estimation locale sinon (`TokenEstimator`, `source: estimated`).
- `OpenAiCompatibleLlmClient`/`EmbeddingClient` lèvent une `InvalidArgumentException` à la construction si aucune clé API n'est configurée — géré en amont par un *repli automatique sur Ollama*.

### 5.2 Sélection du provider actif — `ProviderSelectionService`

Règle de résolution, par usage (`chat` / `embedding`) :

1. Recherche d'une ou plusieurs **`AiProviderConfig`** actives pour cet usage en base (gérées depuis le backoffice, `/admin/ai-provider-configs`) → priorité absolue.
2. Sinon, repli sur les **variables d'environnement** `AI_PROVIDER` (`ollama` ou `api_endpoint`) + `AI_API_*` / `OLLAMA_*`.
3. Si le provider `api_endpoint` choisi n'a pas de clé API valide, **repli silencieux sur Ollama** (avec un log `warning`).

**Chaîne de repli pour `chat`** (`ProviderSelectionService::getLlmClient()`) : contrairement à `embedding` (une seule config active prise en compte, `getActiveForUsage()`), **toutes** les `AiProviderConfig` actives pour l'usage `chat` sont résolues (`getAllActiveForUsage()`, triées `isDefault DESC, updatedAt DESC`) et enveloppées dans un `FallbackLlmClient` si plus d'une est utilisable — chaque config dont la construction échoue (ex. `api_endpoint` sans clé API) est ignorée avec un log `warning`, sans interrompre la résolution des suivantes. `FallbackLlmClient::complete()`/`checkStatus()` essaient chaque client dans l'ordre jusqu'au premier qui réussit (`stream()` idem, mais ne rebascule sur le suivant que si l'échec survient *avant* le premier chunk émis, pour ne jamais dupliquer un flux déjà partiellement renvoyé au client). Avec zéro ou une seule config utilisable, le comportement est inchangé (délégation directe). Permet par ex. un provider `api_endpoint` cloud par défaut avec un Ollama local en secours si le premier est indisponible.

Le provider est **re-résolu à chaque appel** (pas de cache d'instance) : un changement de config admin prend effet immédiatement, sans redémarrage.

> [!CAUTION]
> Le repli « silencieux sur Ollama avec un log `warning` » du point 3 ci-dessus ne vaut que pour `chat` (`getLlmClient()`, protégé par un `try/catch` sur `InvalidArgumentException`). Pour `embedding`, `getEmbeddingClient()` n'a pas ce `try/catch` : c'est un simple test `if ($config->getApiKey())` avant de construire `OpenAiCompatibleEmbeddingClient`. Si l'`AiProviderConfig` active a `provider: api_endpoint` mais une `apiKey` vide, aucune exception n'est levée — le code tombe directement sur `OllamaEmbeddingClient` en réutilisant le champ `model` de *cette même config* (ex. `mistral-embed`) au lieu d'`OLLAMA_EMBEDDING_MODEL`, et Ollama répond `404` (pas de modèle de ce nom). Seul le log `info` « Using AiProviderConfig ... for embedding » apparaît, ce qui induit en erreur puisque ce n'est pas cette config qui est réellement utilisée. Vécu en pratique lors du scénario « Assistant Recruteurs » (voir `docs/backend/bruno/IA & Vecteurs/Providers IA/Create Provider (Embedding - Mistral).bru`) — toujours renseigner une `apiKey` valide avant/en même temps que de passer une config `embedding` en `isActive: true`.

Le modèle d'**analyse de documents** (`getAnalysisLlmClient()`) est un cas particulier : toujours Ollama, piloté uniquement par `OLLAMA_ANALYSIS_MODEL` (pas d'`AiProviderConfig` dédiée).

### 5.3 Orchestration de la conversation — `ChatOrchestrationService`

C'est le cœur du moteur de chat (`App\Chat\ChatOrchestrationService::generateReply()`) :

1. Résout le client LLM actif pour l'usage `chat`.
2. Construit le **contexte RAG** (`RagContextService::buildContext()`, voir §6) à partir du message utilisateur et de l'agent (si fourni).
3. Construit la liste des messages : `system` (prompt de l'agent ou prompt par défaut, enrichi des documents RAG trouvés) + les **6 derniers messages** de l'historique + le message utilisateur courant.
4. Construit les specs d'outils (`ToolSpec[]`) à partir des `Workflow`s actifs pour l'agent.
5. Boucle de tool-calling, **jusqu'à 3 itérations** (`MAX_TOOL_ITERATIONS`) :
   - Demande une completion au LLM avec les `tools` disponibles.
   - Si la réponse ne contient aucun appel d'outil → renvoie le contenu final.
   - Sinon, pour chaque `ToolCall` demandé : résout le `Workflow` correspondant (par nom normalisé), invoque l'`$onToolCall` optionnel (le nom de l'outil, avant exécution — permet à l'appelant de signaler une progression pendant ce chemin bufferisé qui n'émet sinon aucun `delta`, voir §5.5), l'exécute **de façon synchrone** via `WorkflowExecutionService::execute()`, réinjecte le résultat comme message `role: tool`, et redemande une completion.
6. Si le budget d'itérations est épuisé, force une dernière completion **sans outils** pour obtenir une réponse finale.

Chaque appel d'outil est tracé dans `toolTrace` (nom, arguments, statut, sortie) et renvoyé au frontend via `ChatReplyResult::toolCalls`.

**Prompt système par défaut** (`ChatOrchestrationService::DEFAULT_SYSTEM_PROMPT`) :
> *« Tu es un assistant IA utile et bienveillant... Tu réponds en français, de façon claire et concise... Utilise les documents pertinents fournis en contexte... Si tu ne connais pas la réponse, dis-le honnêtement. »*

### 5.4 Points d'entrée de la conversation — `ChatService`

Une façade à deux modes :
- **`sendMessage(Conversation, message, agentId?)`** : persiste le message utilisateur et la réponse assistant en base, utilisé par `/api/conversations/{id}/messages`.
- **`quickSend(message, agentId?)`** : mode anonyme, **rien n'est persisté**, utilisé par `POST /api/chat/quick-send` (ce que consomment les frontends de démo).

### 5.5 Streaming (SSE)

`POST /api/conversations/{id}/stream` (`ConversationStreamController`) émet en *Server-Sent Events* : `user_message` → zéro ou plusieurs `delta` (vrais tokens, au fil de l'eau) → `ai_complete` (message complet sérialisé, lu côté client uniquement pour les métadonnées : id, sources, tool_calls, feedback) → `done`. `ChatOrchestrationService::generateReply()` décide du chemin en fonction de la présence de tools pour l'agent : sans tool disponible, `LlmClientInterface::stream()` (NDJSON pour Ollama, SSE pour l'endpoint OpenAI-compatible) émet un vrai delta par chunk ; avec des tools actifs, le chemin bufferisé historique (`complete()` + boucle tool-calling) reste utilisé — `stream()` est par contrat "texte brut sans tools", et streamer pendant qu'un tool pourrait être appelé fuiterait des détails internes avant que la décision d'appeler l'outil soit connue. Dans ce second cas, un seul `delta` est quand même émis avec le contenu complet une fois la réponse finale obtenue, pour que le frontend ait un contrat uniforme sans avoir à savoir quel chemin a été pris. `token_usage.source` vaut `estimated` sur le chemin streaming (les providers ne renvoient pas de compteurs exacts en streaming), `provider` sur le chemin bufferisé.

Une frame supplémentaire `type: tool_call` (`{type: 'tool_call', tool: '<nom>'}`) est émise juste avant qu'un workflow résolu s'exécute sur le chemin bufferisé — le seul signal de progression disponible pendant cette fenêtre silencieuse (LLM → exécution d'outil → second appel LLM), sans quoi le frontend n'a rien d'autre à montrer qu'un indicateur de frappe générique. Le nom d'outil transmis est la valeur interne (`App\Chat\ChatOrchestrationService::toolName()`, snake_case) ; c'est au frontend de le traduire vers un libellé convivial connu, avec repli générique pour tout nom non reconnu (voir `frontend/composables/useChatbot.ts::toolCallLabel`) — même logique "jamais brut" que le `sources_hidden` de §8.6 et la carte "Entretien confirmé" curatée côté widget.

---

## 6. Fonctionnalités RAG (Retrieval-Augmented Generation)

### 6.1 Vue d'ensemble du pipeline

```
Upload document ──> Extraction de texte ──> Chunking ──> Analyse (LLM) ──> Embeddings ──> Upsert dans Qdrant
                                                                                                  │
Question utilisateur ──> Embedding ──> Recherche Qdrant (top-k) ──> Injection dans le prompt système
```

### 6.2 Ingestion de documents — `knowledge_base`

**`DocumentProcessorService`** — extraction de texte selon le type de fichier :
| Type        | Méthode                                                                                                                       |
| ----------- | ----------------------------------------------------------------------------------------------------------------------------- |
| `pdf`       | `smalot/pdfparser`                                                                                                            |
| `txt`, `md` | lecture brute                                                                                                                 |
| `docx`      | ouvre l'archive ZIP, extrait `word/document.xml`, convertit les balises `<w:p>`/`<w:br/>` en retours à la ligne, `strip_tags` |
| `html`      | `strip_tags`                                                                                                                  |
| `json`      | `json_decode` puis ré-encodage indenté (`JSON_PRETTY_PRINT`)                                                                  |

Nettoyage : normalisation des espaces, suppression des caractères non-alphanumériques hors ponctuation de base (regex Unicode `\p{}`/`\w`).

**Chunking** : découpage en fenêtres de **1000 caractères** avec chevauchement de **200 caractères**, en ajustant le point de coupe au dernier `.` trouvé dans la fenêtre de chevauchement (pour éviter de couper une phrase en plein milieu). Chaque chunk porte des métadonnées (`document_id`, `document_title`, `document_type`, `category_id`, `chunk_size`).

**`DocumentIndexingService`** — l'orchestrateur `chunk → vectorize → delete` :
- `chunkDocument()` : passe le document en `processing`, extrait et persiste les `DocumentChunk`s.
- `vectorize()` : résout la collection Qdrant cible, appelle `VectorSearchService::addDocumentChunks()`, persiste le `vector_id` résultant sur chaque chunk, passe le document en `indexed` (ou `error` avec un message si Qdrant échoue réellement).
- `deleteVectorsAndChunks()` : nettoie Qdrant, puis les chunks en base, avant que le document lui-même soit supprimé.

> [!IMPORTANT]
> **Asynchrone** : `POST /api/documents` et `POST /documents/{id}/process` dispatchent `App\Message\IndexDocumentMessage` sur le transport Messenger `async` (`IndexDocumentMessageHandler` appelle `chunkDocument()`+`vectorize()`) et répondent `202` avec le document en statut `pending` immédiatement — pas d'attente du pipeline complet. Poller `GET /api/documents/{id}` pour le statut final (`indexed`/`error`).

### 6.3 Résolution de collection — `CollectionService`

Chaque document appartient soit à une `Collection` explicite, soit (par défaut) à une **collection commune**, créée *à la volée* au premier besoin (`ensureCommonCollection()`) plutôt que de retomber sur un nom de collection codé en dur qui dépendrait d'une étape de bootstrap au démarrage.

Pour un agent, la collection RAG utilisée est celle liée via `OneToOne` (`Collection.agent`) ; sans collection liée, l'agent n'a aucun RAG contextuel.

### 6.4 Analyse intelligente de documents — `DocumentAnalysisService`

Avant indexation, chaque document est passé à un **modèle Ollama d'analyse dédié** (`OLLAMA_ANALYSIS_MODEL`, `temperature: 0.3`, `maxTokens: 2000`) avec un prompt structuré demandant un payload JSON :

```json
{
  "document_type": "...", "category": "...", "language": "fr",
  "summary": "...", "keywords": [...], "topics": [...],
  "complexity": "intermediate", "target_audience": "...",
  "relevance_score": 1-10, "technical_terms": [...],
  "entities": {"organizations": [], "people": [], "locations": [], "dates": []},
  "sentiment": "neutral", "confidence": 1-10
}
```

Le JSON est extrait (recherche de la première `{` et de la dernière `}`), parsé, puis **validé/borné** (scores bornés 1-10, listes tronquées). En cas d'échec (LLM indisponible, JSON invalide), un objet de métadonnées par défaut est utilisé (`DEFAULT_DOCUMENT_METADATA`) — **un échec d'analyse ne bloque jamais l'indexation**.

Ces métadonnées enrichissent le `payload` de chaque point Qdrant (`category`, `language`, `keywords`, `topics`, `complexity`, `relevance_score`, etc.), utilisables comme futurs filtres de recherche.

### 6.5 Recherche vectorielle — `VectorSearchService` / `QdrantClient`

**`QdrantClient`** — un wrapper REST minimal autour de Qdrant (`http://{QDRANT_HOST}:{QDRANT_PORT}`, ou une URL HTTPS complète pour Qdrant Cloud) :
- `ensureCollection()` : idempotent, crée la collection (`vectors.size: 1024`, `distance: Cosine`) si elle n'existe pas (cache en mémoire par requête).
- `upsert()` : `PUT /collections/{name}/points?wait=true`.
- `search()` : `POST /collections/{name}/points/query`, avec un filtre optionnel (`filter.must`, correspondance exacte sur une clé du payload — ex. `category_id`).
- `delete()` : `POST /collections/{name}/points/delete`.
- `ping()` : `GET /collections` (probe de disponibilité, jamais d'exception — utilisé par `GET /api/health`, voir §8.7).

**`VectorSearchService`** — orchestration RAG, **recherche hybride** (vecteur + lexical) depuis l'ajout de la recherche BM25 :
- `search()` : embed la requête (via `QueryEmbeddingCache`, voir §6.6) → recherche vectorielle dans Qdrant **et** recherche lexicale (FULLTEXT MariaDB) en parallèle logique, **fusionnées par Reciprocal Rank Fusion** (RRF, `k=60`) plutôt qu'un classement vecteur seul → reformate les résultats (`content`, `document_id`, `document_title`, `chunk_index`, `score`, `metadata`) → **journalise** la requête dans `SearchQuery` (si un `VectorIndex` existe pour cette collection). Chaque camp est sur-échantillonné (`limit × 4`, borne basse `limit + 20`) avant fusion et troncature au `limit` demandé.
  - `lexicalSearch()` (privée) : `MATCH(document_chunk.content) AGAINST (:query IN NATURAL LANGUAGE MODE)`, une relevance de type BM25 sur l'index `document_chunk_content_fulltext` (InnoDB/MariaDB ; index créé dans [`backend/migrations/VersionBase.php`](../../backend/migrations/VersionBase.php), la migration baseline squashée le 26/08/2026 — un premier squash (`c498ca0`) avait accidentellement fait sauter cet index, corrigé depuis dans `VersionBase.php` et réappliqué en dev via `dbal:run-sql` ; **la base de production n'a pas encore reçu ce correctif**, voir `docs/BACKLOG.md`). Filtrable par `category_id` (jointure sur `Document.category`) ; `document_type`/`language`/`complexity` du filtre `search()` ne s'appliquent qu'au camp vectoriel (ils vivent dans le payload Qdrant `intelligent_analysis`, pas en colonnes `DocumentChunk`). Échec de la requête SQL (index absent, erreur FULLTEXT) → dégrade silencieusement vers vecteur seul (log `warning`), ne fait jamais échouer `search()`.
  - **Limite connue** : la recherche lexicale interroge `document_chunk` sans filtrer par collection Qdrant (elle n'a pas de notion de "collection" — c'est un concept Qdrant, pas une colonne `DocumentChunk`) ; avec plusieurs collections actives contenant des documents différents, un résultat lexical pourrait techniquement provenir d'une collection distincte de celle demandée. Sans impact connu pour le déploiement actuel (une seule base de connaissances active réellement peuplée).
  - `score` du résultat retourné reste la similarité cosinus Qdrant quand le chunk est aussi un hit vectoriel (comportement inchangé pour les consommateurs existants) ; seul un hit **lexical seul** (absent des résultats vectoriels) expose à la place la relevance FULLTEXT brute — pas sur une échelle 0–1.
- `addDocumentChunks()` : analyse le document, embed tous les chunks en batch, construit les points Qdrant (payload enrichi), upsert.
- ID de point Qdrant **déterministe** : `Uuid::v5(NAMESPACE_DNS, "doc_{id}_chunk_{index}")` — permet de régénérer le même ID plus tard pour la suppression sans avoir besoin de le stocker (bien qu'il soit aussi stocké sur `DocumentChunk.vectorId`), et sert aussi à fabriquer un `id` pour un résultat purement lexical (qui n'a pas d'ID Qdrant).

**`RagContextService`** (dans `chat`) — le point d'entrée utilisé par l'orchestrateur de conversation : résout la collection de l'agent, puis délègue à `VectorSearchService::search()` (top **5** résultats par défaut). Toute exception est absorbée et journalisée — **une erreur RAG ne bloque jamais la génération de réponse**, elle prive seulement le LLM de contexte documentaire.

### 6.6 Modèle d'embedding

- Modèle par défaut : **`mxbai-embed-large`** (Ollama), dimension **1024** — une constante partagée par `QdrantClient::VECTOR_SIZE` et `VectorIndex.dimension`.
- `EmbeddingService` (dans `vector_connector`) est un wrapper léger sur `ProviderSelectionService::getEmbeddingClient()`, qui expose aussi l'usage de tokens du dernier appel (`getLastUsage()`/`getBatchUsage()`), consommé pour enrichir `Document.metadata.embedding_usage`.
- **`QueryEmbeddingCache`** : cache Redis dédié (pool `cache.query_embedding`, `config/packages/cache.yaml`, TTL 7 jours) devant l'appel `EmbeddingService::generateEmbedding()` fait par `VectorSearchService::search()` — une question identique (ou identique après `trim`+minuscules) réutilise le vecteur déjà calculé plutôt que de refaire l'aller-retour Ollama. Même schéma que `App\Chat\ConversationHistoryCache` (§10 n'en parle pas, mais le principe — pool Redis nommé séparé de `app` — est identique). Ne concerne que l'embedding *requête* ; l'embedding des chunks à l'ingestion (`addDocumentChunks()`) n'est pas caché (calculé une seule fois par chunk de toute façon). Pas de notion de modèle/provider dans la clé de cache : changer le modèle d'embedding actif rend le cache obsolète, à vider manuellement (même compromis que l'absence de ré-indexation automatique des chunks déjà vectorisés).

---

## 7. Workflows (outils des agents)

### 7.1 Rôle

Un `Workflow` `active` peut être exposé au LLM comme **outil** si un agent lui est lié (`AiAgent.workflows`). Le LLM décide d'appeler l'outil ; `ChatOrchestrationService` traduit cet appel en exécution du workflow correspondant.

### 7.2 Moteur d'exécution — `WorkflowExecutionService`

`execute(workflowId, inputData, conversation?)` crée une `WorkflowExecution`, puis exécute chaque `WorkflowStep` actif du workflow, **dans l'ordre** (`order` croissant), en propageant la sortie de chaque étape dans l'entrée de la suivante (fusion de tableaux). L'exécution s'arrête à la première étape en échec.

Types d'étapes supportés (`WorkflowStepType`) :

| Type             | Comportement                                                                                                                                                                                            |
| ---------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `api_call`       | Requête HTTP sortante (méthode/URL/headers/body configurables, substitution de placeholders `{{champ}}` dans l'URL et le body)                                                                          |
| `webhook`        | Identique à `api_call` mais envoie toujours `inputData` en JSON                                                                                                                                         |
| `data_transform` | Applique une liste de transformations (`set`, `remove`, `add`) aux données courantes                                                                                                                    |
| `condition`      | Évalue une condition (`equals`, `not_equals`, `contains`, `greater_than`, `less_than`) sur un champ, exécute une `true_action`/`false_action` — `{"type": "set_field"\|"add_field"\|"remove_field", "field": "...", "value"?: ...}`, même vocabulaire `set`/`add`/`remove` que `data_transform` (`WorkflowExecutionService::applyFieldOperation()`, factorisé entre les deux) |
| `delay`          | `sleep()` bloquant pendant le nombre de secondes configuré                                                                                                                                              |
| `email`          | Envoi réel via **Symfony Mailer** (`MAILER_DSN`), expéditeur `MAILER_FROM_ADDRESS`. En dev Docker, pointe vers un catcher **MailHog** local (`http://mailhog.chatbot.localhost`) -- rien ne part réellement tant que `MAILER_DSN` n'est pas configuré vers un vrai provider en prod                    |
| `notification`   | POST vers `webhook_url` (payload `{"text": ..., "channel": ...}`, compatible Slack/Discord/Mattermost) si configuré dans le step ; sinon journalise seulement (`status: logged`)                        |
| `set_conversation` | N'a d'effet que dans le chemin de tool-calling (`ChatOrchestrationService::execute()` passe la `Conversation` courante en contexte) — pas de conversation disponible sur un déclenchement manuel/API (`trigger`/`test`), auquel cas l'étape est un no-op (`status: skipped`). `configuration.fields` mappe un nom de champ `Conversation` (`visitor_first_name`, `visitor_last_name` — seuls champs supportés) vers une clé de `inputData` (les arguments extraits par le LLM lors de l'appel d'outil) ; persiste la ou les valeurs scalaires trouvées sur l'entité et flush. Type de step générique, réutilisable par n'importe quel workflow qui a besoin de mémoriser l'identité du visiteur une fois extraite en tool-calling structuré -- aucun workflow actif ne l'utilise actuellement (voir `docs/BACKLOG.md` pour l'historique) |

### 7.3 Synchronicité

Deux chemins, volontairement différents :
- **`POST /api/workflows/{id}/trigger`** (déclenchement manuel/API) et **`POST /api/workflows/{id}/test`** : `createPendingExecution()` persiste la `WorkflowExecution` (statut `pending`), dispatche `App\Message\ExecuteWorkflowMessage` sur le transport `async`, et répondent `202` immédiatement — `run()` s'exécute dans le worker Messenger. Poller `GET /api/workflow_executions/{id}`.
- **Tool-calling** (`ChatOrchestrationService`) : reste **synchrone**, `execute()` (create + `run()` en ligne, sans passer par le bus) — la boucle de tool-calling a besoin du résultat immédiatement pour poursuivre la conversation avec le LLM ; le rendre asynchrone casserait le tool-calling. Choix délibéré, pas un oubli.

### 7.4 Suppression = soft delete

`DELETE /api/workflows/{id}` ne supprime pas la ligne : elle passe `isActive = false` (`WorkflowSoftDeleteController`).

---

## 8. Référence API complète

Base URL : `http://symfony.chatbot.localhost` (dev, via Traefik). Toutes les ressources API Platform sont sous **`/api`** ; documentation interactive Hydra/JSON-LD native sur `/api`, documentation Swagger/OpenAPI pure sur **`/doc`**.

> [!WARNING]
> **Authentification HTTP Basic requise** sur tous ces endpoints. `Conversation`/`WorkflowExecution` acceptent `ROLE_USER` (cloisonné à ses propres lignes) ; toutes les autres ressources ci-dessous exigent `ROLE_ADMIN` explicitement — voir §10. **Piège API Platform** (§10) : ce `security:` déclaratif ne s'applique pas de façon fiable aux opérations à contrôleur personnalisé (`/{id}/steps`, `/{id}/trigger`, `/{id}/test`, `/{id}/process`, `/{id}/chunks`, upload/delete de `Document`, `/{id}/test` d'`AiProviderConfig`, `/vector/search`, `/vector/stats`) — chacune porte son propre `#[IsGranted('ROLE_ADMIN')]` sur le contrôleur, pas seulement l'attribut de la ressource.

### 8.1 `ai_providers`

| Méthode  | URL                                  | Description                                                                                              |
| -------- | ------------------------------------ | -------------------------------------------------------------------------------------------------------- |
| `GET`    | `/api/ai_provider_configs`           | Liste des configs de providers                                                                           |
| `GET`    | `/api/ai_provider_configs/{id}`      | Détail                                                                                                   |
| `POST`   | `/api/ai_provider_configs`           | Création                                                                                                 |
| `PATCH`  | `/api/ai_provider_configs/{id}`      | Mise à jour partielle                                                                                    |
| `DELETE` | `/api/ai_provider_configs/{id}`      | Suppression                                                                                              |
| `POST`   | `/api/ai_provider_configs/{id}/test` | Test **en live** de la config (appelle réellement le provider), persiste `lastTestStatus`/`lastTestedAt` |

### 8.2 `vector_connector`

| Méthode                       | URL                          | Description                                                                                  |
| ----------------------------- | ---------------------------- | -------------------------------------------------------------------------------------------- |
| `GET`/`POST`/`PATCH`/`DELETE` | `/api/vector_indices[/{id}]` | CRUD des index vectoriels connus                                                             |
| `POST`                        | `/api/vector/search`         | **Recherche vectorielle canonique** — body `{query, collection_name?, category_id?, document_type?, language?, complexity?, limit?}`, les filtres se combinent en ET |
| `GET`                         | `/api/vector/stats`          | Nombre d'index actifs, total des requêtes journalisées, 10 `SearchQuery` les plus récentes   |

### 8.3 `knowledge_base`

| Méthode                       | URL                               | Description                                                                                                                                                                                                       |
| ----------------------------- | --------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `GET`/`POST`/`PATCH`/`DELETE` | `/api/document_categories[/{id}]` | CRUD des catégories                                                                                                                                                                                               |
| `GET`                         | `/api/faqs[/{id}]`                | Liste (FAQ actives uniquement)/détail — public                                                                                                                                                                   |
| `POST`                        | `/api/faqs`                       | Création (`ROLE_ADMIN`) — pas de `PATCH`/`DELETE` côté API, édition/suppression réservées à `/admin/faqs`                                                                                                        |
| `GET`/`POST`/`PATCH`/`DELETE` | `/api/collections[/{id}]`         | CRUD des collections                                                                                                                                                                                              |
| `GET`                         | `/api/documents`                  | Liste des documents                                                                                                                                                                                               |
| `GET`                         | `/api/documents/{id}`             | Détail                                                                                                                                                                                                            |
| `PATCH`                       | `/api/documents/{id}`             | Mise à jour (métadonnées, pas le fichier)                                                                                                                                                                         |
| `POST`                        | `/api/documents`                  | **Upload multipart** (`file`, `title`, `description?`, `category_id?`) — extensions autorisées : `pdf, txt, docx, md, html, json`, taille max **10 Mo**. Répond `202`, statut `pending` ; `chunkDocument()`+`vectorize()` tournent en tâche de fond (Messenger) |
| `DELETE`                      | `/api/documents/{id}`             | Supprime les vecteurs Qdrant + les chunks + le fichier physique + la ligne                                                                                                                                        |
| `POST`                        | `/api/documents/{id}/process`     | Ré-indexation complète (supprime puis recrée les chunks/vecteurs), **asynchrone** — répond `202`                                                                                                                  |
| `GET`                         | `/api/documents/{id}/chunks`      | Liste des chunks du document                                                                                                                                                                                      |

### 8.4 `workflows`

| Méthode              | URL                             | Description                                                                                             |
| -------------------- | ------------------------------- | ------------------------------------------------------------------------------------------------------- |
| `GET`/`POST`/`PATCH` | `/api/workflows[/{id}]`         | CRUD (partiel) des workflows                                                                            |
| `DELETE`             | `/api/workflows/{id}`           | **Soft delete** (`isActive = false`)                                                                    |
| `GET`                | `/api/workflows/{id}/steps`     | Liste des étapes actives, ordonnées                                                                     |
| `POST`               | `/api/workflows/{id}/steps`     | Création d'une étape (`name`, `step_type`, `order`, `configuration?`, `is_active?`)                     |
| `POST`               | `/api/workflows/{id}/trigger`   | Déclenchement (rejette si le workflow n'est pas `active`) — **asynchrone**, répond `202` avec l'exécution `pending` (poller `GET /api/workflow_executions/{id}`) |
| `POST`               | `/api/workflows/{id}/test`      | Exécution de test — **asynchrone**, même principe, aucune vérification de statut                        |
| `GET`                | `/api/workflow_executions`      | Liste des exécutions (lecture seule)                                                                    |
| `GET`                | `/api/workflow_executions/{id}` | Détail d'une exécution                                                                                  |

### 8.5 `chat`

| Méthode                       | URL                                | Description                                                                                                    |
| ----------------------------- | ---------------------------------- | -------------------------------------------------------------------------------------------------------------- |
| `GET`/`POST`/`PATCH`/`DELETE` | `/api/conversations[/{id}]`        | CRUD des conversations                                                                                         |
| `GET`                         | `/api/conversations/{id}/messages` | Historique des messages                                                                                        |
| `POST`                        | `/api/conversations/{id}/messages` | Envoie un message utilisateur, le persiste + renvoie la réponse de l'assistant (body : `{message, agent_id?}`) |
| `POST`                        | `/api/conversations/{id}/stream`   | Idem, réponse en **SSE** (`text/event-stream`)                                                                 |
| `PATCH`                       | `/api/conversations/{id}/messages/{messageId}/feedback` | Thumbs up/down sur un message (body : `{feedback: "positive"\|"negative"\|null}`)             |
| `GET`                         | `/api/conversations/{id}/sources`  | Agrège les sources RAG (`document_id`, `document_title`, `score`) citées par tous les messages assistant de la conversation, extraites de `Message.metadata.sources` (`ConversationSourcesController`) — non consommé par le widget frontend actuel (qui lit `metadata.sources` directement sur chaque message), pas dans l'allowlist du proxy Nuxt |
| `GET`                         | `/api/ai_agents`                   | Liste des agents (lecture seule, pagination désactivée)                                                        |
| `GET`                         | `/api/ai_agents/{id}`              | Détail d'un agent                                                                                              |
| `POST`                        | `/api/chat/quick-send`             | Chat **anonyme, non persisté** (body : `{message, agent_id?}`) — utilisé par les frontends de démo             |
| `GET`                         | `/api/chat/llm-status`             | Statut du provider LLM actif (`reachable`/`running`/`error`/`not_reachable`) — avec plusieurs `AiProviderConfig chat` actives (§5.2), reflète le premier client `reachable` de la chaîne de repli, avec `fallback_checked` (index atteint) en plus |
| `GET`                         | `/api/chat/embedding-status`       | Statut du provider d'embedding actif                                                                           |
| `POST`                        | `/api/chat/follow-up-questions`    | 2-3 questions de relance générées à partir d'un échange (body : `{message, answer}`, stateless — pas de lookup DB) |

### 8.6 Formats de réponse notables

**`POST /api/chat/quick-send`** :
```json
{
  "response": "...",
  "conversation_id": null,
  "status": "success",
  "token_usage": {"prompt_tokens": 0, "completion_tokens": 0, "total_tokens": 0, "source": "provider|estimated", "provider": "ollama|api_endpoint", "model": "..."},
  "tool_calls": [{"tool": "...", "arguments": {...}, "status": "completed|failed", "output": {...}}],
  "sources": [{"document_id": 1, "document_title": "...", "score": 0.56}],
  "sources_hidden": true
}
```
`sources_hidden` : même convention que `App\Chat\MessageSerializer` pour les messages persistés (`§8.5`) — un flag, pas un retrait des données. `quick-send` est le seul point d'entrée pensé pour des **embedders tiers** (pas ce repo, dont le widget ignore déjà les sources par design) ; le flag documente juste la même intention côté n'importe quel consommateur public.

**`POST /api/chat/follow-up-questions`** :
```json
{"questions": ["Quels projets a-t-il menés ?", "Quelle est sa stack technique ?"]}
```
`App\Chat\FollowUpQuestionsService::generate()` — appelle le client LLM d'analyse dédié (`ProviderSelectionService::getAnalysisLlmClient()`, celui de `DocumentAnalysisService`) sur le seul échange `{message, answer}` reçu, lui demande 2-3 questions de relance courtes en JSON. Best-effort : `[]` sur tout échec (parsing JSON, LLM indisponible), jamais d'exception propagée — pensé pour être appelé *après* qu'une vraie réponse a déjà été affichée (voir `frontend/composables/useChatbot.ts::fetchFollowUpQuestions`, jamais sur le chemin critique d'un tour de conversation). Consomme le même rate-limiter que `quick-send`/`messages`/`stream` (`limiter.chat_message`).

**`GET /api/conversations/{id}/sources`** :
```json
{"conversation_id": 12, "sources": [{"document_id": 4, "document_title": "...", "score": 0.71, "message_id": 87, "message_created_at": "2026-08-26T10:00:00+00:00"}], "total": 1}
```

**`POST /api/vector/search`** — recherche hybride (§6.5), classée par fusion RRF ; `score` reste la similarité cosinus Qdrant pour un hit vectoriel, une relevance FULLTEXT (hors 0-1) pour un hit lexical seul :
```json
{"query": "...", "results": [{"id": "...", "score": 0.87, "content": "...", "document_id": 12, "document_title": "...", "chunk_index": 3, "metadata": {...}}], "total": 5}
```

### 8.7 Monitoring (transverse)

| Méthode | URL           | Description                                                                             |
| ------- | ------------- | ----------------------------------------------------------------------------------------- |
| `GET`   | `/api/health` | Agrège 4 checks indépendants (DB, Qdrant, Redis, provider LLM) en un seul appel — `200` si tous passent, `503` sinon |

`App\Controller\HealthController`, même emplacement que `LlmStatusController`/`QuickSendController` (`App\Controller` + une `ApiResource` "virtuelle" dans `App\ApiResource`, pas une entité). Chaque check est indépendant et best-effort (jamais d'exception qui remonte) :
```json
{
  "status": "ok",
  "checks": {
    "database": {"status": "ok"},
    "qdrant": {"status": "ok"},
    "redis": {"status": "ok"},
    "llm": {"status": "running", "model_available": true, "models": [...], "base_url": "...", "model": "..."}
  }
}
```
`checks.llm` réutilise directement `ProviderSelectionService::checkLlmStatus()` (même forme que `GET /api/chat/llm-status`) ; `checks.qdrant` appelle la nouvelle `QdrantClient::ping()` (`GET /collections`, pas la racine, pour distinguer une instance joignable-mais-mal-configurée d'un vrai down) ; `checks.redis` ouvre une connexion `\Redis` via `RedisAdapter::createConnection(REDIS_URL)` et appelle `PING` directement, sans passer par le pool de cache applicatif (`cache.conversation_history`).

---

## 9. Backoffice admin (`/admin`)

Construit avec **Sylius Resource/Grid Bundle** — CRUD générique piloté par config YAML (`config/routes/admin.yaml`) + repository + form + grid, sans thème packagé (templates Twig maison, `templates/admin/crud/*.html.twig`), stylé en Tailwind compilé localement (AssetMapper, voir §2).

| Ressource                              | URL                          | Opérations                                                                                   |
| -------------------------------------- | ---------------------------- | -------------------------------------------------------------------------------------------- |
| *(Analytics)*                          | `/admin/analytics`           | Lecture seule — tableau de bord agrégé, pas un `#[AsGrid]` Sylius (voir note ci-dessous)      |
| *(Journal d'audit)*                    | `/admin/audit-log`           | Lecture seule — même schéma qu'Analytics, voir note ci-dessous                               |
| `AiProviderConfig`                     | `/admin/ai-provider-configs` | CRUD complet                                                                                 |
| `VectorIndex`                          | `/admin/vector-indexes`      | CRUD complet                                                                                 |
| `SearchQuery`                          | `/admin/search-queries`      | Lecture seule (`index`, `show`)                                                              |
| `DocumentCategory`                     | `/admin/document-categories` | CRUD complet                                                                                 |
| `Faq`                                  | `/admin/faqs`                | CRUD complet — **seul moyen de modifier/supprimer une FAQ** (la création est aussi possible via `POST /api/faqs`, `ROLE_ADMIN`) |
| `Collection`                           | `/admin/collections`         | CRUD complet                                                                                 |
| `Document`                             | `/admin/documents`           | `index`, `show`, `update`, `delete` — **pas de création** (réservée à `POST /api/documents`) |
| `Workflow` (+ `WorkflowStep` imbriqué) | `/admin/workflows`           | CRUD complet                                                                                 |
| `WorkflowExecution`                    | `/admin/workflow-executions` | Lecture seule                                                                                |
| `AiAgent`                              | `/admin/ai-agents`           | CRUD complet — **seul moyen de créer/modifier un agent**, l'API REST étant en lecture seule  |
| `Conversation`                         | `/admin/conversations`       | CRUD complet                                                                                 |
| `Message`                              | `/admin/messages`            | Lecture seule                                                                                |
| `User`                                 | `/admin/users`               | CRUD complet — gestion des comptes opérateurs (§10)                                          |

**Analytics n'est pas une ressource Sylius** — pas d'entité/formulaire/grille : `App\Controller\Admin\AnalyticsController` (route `app_admin_analytics_index`, même convention de nom que les routes Sylius pour que la mise en surbrillance de la sidebar dans `admin/layout.html.twig` fonctionne sans changement) rend directement `App\Chat\AnalyticsService::getDashboardStats()` (agrégats `Conversation`/`Message`/`SearchQuery` via DQL — `total_tokens` est sommé en PHP, pas en SQL, car il vit dans `Message.metadata` en JSON, voir §6.5 pour le même choix côté recherche lexicale) dans `templates/admin/analytics/index.html.twig`. Ajouté à `AdminExtension::nav()` avec le même helper `$item()` que les ressources Sylius.

**Journal d'audit, même schéma** : `App\Controller\Admin\AuditLogController` (route `app_admin_audit_log_index`) liste, paginée manuellement (50/page, pas de dépendance de pagination), la table `audit_log` alimentée par `App\EventListener\AuditLogListener` — voir §10 pour le détail du mécanisme de capture.

**Recherche/filtrage des messages sur `/admin/conversations/{id}`** : purement client, pas d'aller-retour serveur — la conversation entière est déjà dans le DOM (`templates/admin/conversation/show.html.twig`). Contrôleur Stimulus `conversation-filter` (`assets/controllers/conversation_filter_controller.js`, auto-enregistré par `symfony/stimulus-bundle`, aucune entrée `controllers.json` nécessaire pour un contrôleur custom du projet) : un champ recherche (substring insensible à la casse sur `textContent`) et, seulement si la conversation contient plus d'un rôle, un `<select>` de filtrage par rôle — masque (`hidden`) les `<li>` ne correspondant pas, affiche un message vide si le filtre ne matche aucun message.

Pour ajouter une 14ᵉ ressource : une entité `implements ResourceInterface`, un repository avec `ResourceRepositoryTrait`, une classe `App\Form\XType`, une classe `App\Grid\XGrid` (`#[AsGrid]`), une entrée dans `config/packages/sylius_resource.yaml` et `config/routes/admin.yaml`. Le rendu des champs est mutualisé via `App\Twig\AdminExtension::fieldValue()` (basé sur `PropertyAccessor`, gère nativement enums/dates/bools/relations/collections).

**CSRF** : activé (protection stateless par défaut de Symfony, `SameOriginCsrfTokenManager`) — chaque formulaire embarque un token, complété côté client par le contrôleur Stimulus `csrf-protection` (servi via AssetMapper) ; sans lui, la validation retombe sur la vérification d'origine (`Sec-Fetch-Site`/`Origin`/`Referer`). Les suppressions utilisent en plus le CSRF classique basé session, indépendant de ce qui précède.

---

## 10. Sécurité

**État actuel : multi-utilisateur (table `app_user`), avec cloisonnement par propriétaire sur `Conversation`/`WorkflowExecution`.**

- `config/packages/security.yaml` définit deux firewalls sur un provider `entity` unique (`App\Entity\User`, identifiant = email) :
  - **`admin`** (`^/admin`) : `form_login` classique (session cookie) — page `/admin/login`, déconnexion via `/admin/logout`, `enable_csrf: true` (token `_csrf_token` rendu par `csrf_token('authenticate')` dans `templates/admin/login.html.twig` — absent jusqu'à un audit de sécurité, le formulaire de login lui-même n'avait pas de protection CSRF alors que les formulaires CRUD Symfony (`FormType`) l'ont par défaut).
  - **`api`** (`^/`, catch-all) : `http_basic`, `stateless: true` — couvre `/api/*` et `/doc`, adapté à un client machine (curl, scripts, proxy serveur d'un frontend).
- `access_control` : `^/admin` exige `ROLE_ADMIN` (seule `^/admin/login` reste `PUBLIC_ACCESS`) ; `^/(api|doc)` exige seulement `ROLE_USER` — le rôle de base que `User::getRoles()` ajoute toujours, donc "authentifié", pas "admin". L'autorisation fine se fait ressource par ressource (voir plus bas).
- Comptes : `bin/console app:user:create <email> <password> [--role=ROLE_USER|ROLE_ADMIN]` (défaut `ROLE_ADMIN`), ou `/admin/users`. Le frontend Nuxt (`frontend/`) s'authentifie de façon transparente pour l'utilisateur final via son proxy serveur (`ADMIN_USERNAME`/`ADMIN_PASSWORD`, un compte `ROLE_ADMIN`, injectés en Basic Auth côté Nitro, voir le cahier des charges frontend).
- **Cloisonnement par propriétaire** (`Conversation.user`, `WorkflowExecution.triggeredBy`, tous deux auto-renseignés au `prePersist` par `App\EventListener\UserStampListener`) :
  - **Item** (`Get`/`Patch`/`Delete`) : `security: "is_granted('OWNER', object)"`, vérifié par `App\Security\Voter\OwnershipVoter` (`ROLE_ADMIN` bypass toujours ; propriétaire `null` = admin uniquement).
  - **Collection** (`GetCollection`) : `App\Doctrine\OwnershipCollectionExtension` filtre la requête DQL (pas d'`object` unique pour un Voter).
  - **Opérations à contrôleur personnalisé** (messages/stream/sources/feedback de `Conversation`) : le `security:` déclaratif d'API Platform ne s'y applique pas de façon fiable (vérifié empiriquement) — `#[IsGranted('OWNER', subject: 'data')]` directement sur `ConversationMessagesController`/`ConversationStreamController`/`ConversationSourcesController`/`MessageFeedbackController` (les deux derniers ajoutés après l'audit de sécurité initial, même mécanisme appliqué dès leur création).
  - **La plupart des autres ressources** (`Document`, `Workflow`, `AiProviderConfig`, `Collection`, `DocumentCategory`, `VectorIndex`) exigent explicitement `ROLE_ADMIN` sur leur propre `#[ApiResource(security: ...)]`, indépendamment de la règle `access_control` globale — un compte `ROLE_USER` ne peut ni les lire ni les modifier. **Exceptions** : `AiAgent` et `Faq` gardent `ROLE_ADMIN` par défaut au niveau ressource mais redéclarent `GetCollection`/`Get` en `PUBLIC_ACCESS` — lecture ouverte à tout compte authentifié (y compris `ROLE_USER`, la couche `access_control` `^/(api|doc)` exigeant de toute façon `ROLE_USER` avant même d'atteindre ce `security:` de ressource). `AiAgent` n'expose aucune autre opération via l'API, écriture uniquement via le backoffice (`/admin/ai-agents`) ; `Faq` expose en plus un `Post` en `ROLE_ADMIN` (création possible via l'API depuis peu), édition/suppression restant réservées au backoffice (`/admin/faqs`).

> [!CAUTION]
> **Faille trouvée et corrigée (audit de sécurité)** : le `security:` déclaratif ci-dessus au niveau ressource **ne s'applique pas** aux opérations à contrôleur personnalisé (`controller: XyzController::class` dans `#[ApiResource(operations: [...])]`) — vérifié empiriquement, même limite déjà connue pour `ConversationMessagesController`/`ConversationStreamController` (cloisonnement par propriétaire ci-dessus), mais jamais étendue aux ressources admin-only. Concrètement, `WorkflowStepsController`, `WorkflowTriggerController`, `WorkflowTestController`, `WorkflowSoftDeleteController`, `DocumentUploadController`, `DocumentDeleteController`, `DocumentProcessController`, `DocumentChunksController`, `TestAiProviderConfigController`, `VectorSearchController` et `VectorStatsController` (les deux derniers sans même de `security:` au niveau ressource) étaient accessibles par **n'importe quel compte authentifié**, `ROLE_USER` compris — et donc, en pratique, par n'importe quel visiteur du widget public, puisque le proxy Nuxt s'authentifie toujours en `ROLE_ADMIN` réel pour *chaque* requête entrante, admin ou pas (voir le cahier des charges frontend, §3.4). Confirmé exploitable via ce proxy (`GET /api/workflows/{id}/steps` → `200` sans aucun credential, config interne en clair) puis confirmé corrigé avec un compte `ROLE_USER` jetable (`403` après le fix). **Correctif** : `#[IsGranted('ROLE_ADMIN')]` directement sur chacun de ces 11 contrôleurs — mécanisme fiable car appliqué par `Symfony\Component\Security\Http\Attribute\IsGrantedAttributeListener` sur `kernel.controller`, indépendant du `security:` d'API Platform. **Piège pour tout nouveau contrôleur personnalisé sur une ressource admin-only : penser à `#[IsGranted('ROLE_ADMIN')]`, ne jamais compter sur le `security:` de la ressource seul.**

> [!CAUTION]
> **Le vrai périmètre "public" de ce backend n'est pas défini ici mais côté frontend.** Le proxy Nuxt (`frontend/server/api/[...path].ts`) s'authentifiant toujours en admin, un `security: "is_granted('ROLE_ADMIN')")` correct sur une ressource ne protège que contre un appel direct au backend avec un compte non-admin — pas contre le widget public, dont le vrai périmètre est l'allowlist explicite du proxy (voir le cahier des charges frontend). Deux ressources en particulier restent structurellement fragiles si cette allowlist changeait un jour sans y repenser : `Conversation::GetCollection` et `WorkflowExecution::GetCollection` n'ont **aucune** `security:` propre, elles comptent uniquement sur `OwnershipCollectionExtension` — qui laisse passer `ROLE_ADMIN` sans filtrage (voir §12.1 pour le risque résiduel documenté et accepté sur l'énumération d'id de conversation).
- **CORS** (`nelmio_cors.yaml`) : origines autorisées via la regex `CORS_ALLOW_ORIGIN` (par défaut `^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$` — dev uniquement), méthodes `GET, OPTIONS, POST, PUT, PATCH, DELETE`, headers `Content-Type, Authorization`.
- `AiProviderConfig.apiKey` est `#[ApiProperty(readable: false)]` : jamais renvoyé par l'API (`GET`/`GetCollection`), uniquement accepté en écriture (`POST`/`PATCH`). `Conversation.getOwnerUser()`/`getOwnerFieldName()` (et l'équivalent sur `WorkflowExecution`) portent la même annotation. Reste visible dans le formulaire d'édition du backoffice, nécessaire pour le modifier — mais cette page est protégée par le firewall `admin`.

> [!CAUTION]
> Sans `#[ApiProperty(readable: false)]` sur `getOwnerUser()`/`getOwnerFieldName()`, ces méthodes ajoutées pour `OwnedResourceInterface` seraient auto-découvertes comme propriétés API et embarqueraient le `User` complet — hash de mot de passe inclus — dans chaque réponse JSON exposant une `Conversation`/`WorkflowExecution`. Bug réel, trouvé et corrigé en cours de développement plutôt qu'en production.

**Journal d'audit** (`audit_log`, §9) : `App\EventListener\AuditLogListener` s'abonne aux événements génériques `app.<resource>.post_create`/`post_update`/`pre_delete` que `Sylius\Bundle\ResourceBundle\Controller\ResourceController` émet déjà pour toute ressource CRUD, plutôt qu'un listener dédié par entité. Un seul point d'attention : la suppression est capturée sur `pre_delete` et non `post_delete`, car Doctrine réinitialise l'identifiant auto-généré à `null` sur l'entité en mémoire juste après l'exécution réelle de la suppression — capturer après coup y perdrait `resourceId`. L'acteur (`actorEmail`) est un instantané, pas une clé étrangère vers `User`, pour rester lisible même si le compte opérateur est ensuite supprimé.

**En-têtes de sécurité HTTP sur `/admin`** : `App\EventListener\SecurityHeadersListener` (`kernel.response`, scopé aux requêtes principales sous `/admin` uniquement — pas `/api`, une API JSON consommée par le proxy Nuxt/des scripts, pas une page de navigateur). CSP, HSTS, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`. Tout ce que charge le backoffice est self-hosté via AssetMapper (pas de CDN, pas de Google Fonts côté admin contrairement au widget public) — `'unsafe-inline'` reste nécessaire sur `script-src` (le `<script type="importmap">` que rend `importmap()`) et `style-src` (un seul `style="width: …%"` dynamique dans `templates/admin/analytics/index.html.twig`).

---

## 11. Installation et mise en place

### 11.1 Prérequis

- **Docker** + Docker Compose (méthode recommandée), **ou** PHP 8.4 + Composer + Symfony CLI en local.
- Un serveur **Ollama** accessible (local ou distant) si `AI_PROVIDER=ollama`, avec les modèles `mxbai-embed-large`, `gpt-oss:20b` (ou équivalents) déjà `pull`és.
- Accès réseau à **MariaDB 11.4** et **Qdrant**.

### 11.2 Avec Docker (recommandé)

```bash
cd backend
cp .env.example .env
# Générer ADMIN_PASSWORD_HASH (voir §10) avant de lancer, sinon impossible de se connecter
docker network inspect chatbot-proxy >/dev/null 2>&1 || docker network create chatbot-proxy
docker compose up -d --build
```

Services démarrés (`compose.yaml`) :

| Service    | Rôle                                                                 | Port hôte                                                      |
| ---------- | -------------------------------------------------------------------- | -------------------------------------------------------------- |
| `app`      | API Symfony (serveur PHP intégré, `php -S 0.0.0.0:8000`)             | aucun (retiré — accès uniquement via Traefik, voir ci-dessous) |
| `database` | MariaDB (`mariadb:${MARIADB_VERSION:-11.4}`)                         | port aléatoire                                                 |
| `qdrant`   | Base vectorielle                                                     | ports aléatoires (REST/gRPC)                                   |
| `nuxt`     | Frontend de démo Nuxt (`frontend/`), branché sur `app` via `API_URL` | `3010`                                                         |

`OLLAMA_BASE_URL` est automatiquement pointé vers `http://host.docker.internal:11434` (Ollama tournant sur la machine hôte, hors conteneur). Un réseau Docker externe `chatbot-proxy` (Traefik) est requis (`networks.proxy.external: true`) ; le service échouera à démarrer sans lui, sauf à retirer ce bloc. Créé automatiquement par `make start` (racine du dépôt) ; à défaut, `docker network create chatbot-proxy` une fois.

Le port hôte fixe `8000:8000` du service `app` a été retiré (pour permettre à d'autres stacks utilisant ce même port de tourner en parallèle) : l'API n'est plus joignable en direct via `localhost:8000`, uniquement via le domaine Traefik.

Accès une fois démarré :
- API : `http://symfony.chatbot.localhost`
- Doc Hydra/JSON-LD (native API Platform) : `http://symfony.chatbot.localhost/api`
- Doc Swagger/OpenAPI pure : `http://symfony.chatbot.localhost/doc`
- Backoffice : `http://symfony.chatbot.localhost/admin`
- Frontend démo : `http://nuxt.chatbot.localhost` (ou `http://localhost:3010`)

### 11.3 En local (sans Docker)

```bash
cd backend
cp .env.example .env
composer install
symfony server:start
```

Il faut alors fournir soi-même une base MariaDB et une instance Qdrant joignables aux URLs configurées dans `.env` (et y générer `ADMIN_PASSWORD_HASH`, voir §10 — requis quel que soit le mode d'installation), et lancer les migrations :

```bash
php bin/console doctrine:migrations:migrate
```

(1 migration dans `migrations/`, couvrant l'ensemble du schéma des 16 entités.)

### 11.4 Variables d'environnement

Fichier de référence : `.env.example`.

| Variable                 | Défaut / exemple                                                                  | Rôle                                                                                       |
| ------------------------ | --------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| `APP_ENV`                | `dev`                                                                             | Environnement Symfony                                                                      |
| `APP_SECRET`             | (généré)                                                                          | Secret applicatif Symfony                                                                  |
| `DATABASE_URL`           | `mysql://app:!ChangeMe!@127.0.0.1:3306/app?serverVersion=11.4.12-MariaDB&charset=utf8mb4` | Connexion Doctrine                                                                         |
| `CORS_ALLOW_ORIGIN`      | `^https?://(localhost\|127\.0\.0\.1)(:[0-9]+)?$`                                  | Regex des origines autorisées                                                              |
| `AI_PROVIDER`            | `ollama`                                                                          | `ollama` \| `api_endpoint` — fallback quand aucune `AiProviderConfig` active n'existe      |
| `OLLAMA_BASE_URL`        | `http://localhost:11434`                                                          | URL du serveur Ollama                                                                      |
| `OLLAMA_EMBEDDING_MODEL` | `mxbai-embed-large`                                                               | Modèle d'embedding (dimension 1024)                                                        |
| `OLLAMA_ANALYSIS_MODEL`  | `gpt-oss:20b`                                                                     | Modèle dédié à l'analyse de documents                                                      |
| `OLLAMA_CHAT_MODEL`      | `gpt-oss:20b`                                                                     | Modèle de chat par défaut (Ollama)                                                         |
| `AI_API_ENDPOINT`        | ex. endpoint OVHcloud AI Endpoints `.../v1/chat/completions`                      | URL de l'endpoint OpenAI-compatible                                                        |
| `AI_API_KEY`             | *(vide)*                                                                          | Clé API de l'endpoint distant                                                              |
| `AI_API_MODEL`           | `gpt-oss-120b`                                                                    | Modèle utilisé sur l'endpoint distant                                                      |
| `AI_API_TIMEOUT`         | `30`                                                                              | Timeout HTTP (secondes)                                                                    |
| `QDRANT_HOST`            | `localhost`                                                                       | Hôte Qdrant                                                                                |
| `QDRANT_PORT`            | `6333`                                                                            | Port REST Qdrant                                                                           |
| `QDRANT_API_KEY`         | *(vide)*                                                                          | Clé API Qdrant (si sécurisé)                                                               |
| `MESSENGER_TRANSPORT_DSN` | `redis://redis:6379/messages` (dev) ; `sync://` en prod tant qu'aucun worker persistant n'existe | Transport Messenger `async` (§2, §6.2, §7.3)                              |
| `REDIS_URL`               | `redis://redis:6379` (dev) ; Redis externe managé (`rediss://`, TLS) en prod | DSN Redis pour les pools de cache dédiés (`config/packages/cache.yaml` : `cache.conversation_history`, `cache.query_embedding`, `cache.admin_analytics`) -- indépendant de `MESSENGER_TRANSPORT_DSN` ci-dessus, même si les deux pointent vers le même serveur Redis en dev |
| `MAILER_DSN`              | `null://null` (défaut) ; `smtp://mailer:1025` en dev Docker (override dans `compose.yaml`, catcher MailHog) | Transport Symfony Mailer utilisé par le step `email` (§7.2) -- à pointer vers un vrai provider en prod |
| `MAILER_FROM_ADDRESS`     | `noreply@chatbot.localhost`                                                       | Expéditeur des emails envoyés par le step `email`, et de la notification "nouvelle conversation" (`OWNER_NOTIFICATION_EMAIL` ci-dessous) |
| `OWNER_NOTIFICATION_EMAIL` | *(vide)*                                                                          | Adresse notifiée par `ChatService` au premier message d'une nouvelle conversation -- vide désactive la notification (silencieux, pas une erreur) |
| `ADMIN_USERNAME`         | `admin`                                                                           | Ne seed que la première ligne `app_user` (migration) ; jamais lu par Symfony au runtime (§10) |
| `ADMIN_PASSWORD_HASH`    | *(vide — à générer)*                                                              | Hash bcrypt (`bin/console security:hash-password`) — idem, seed uniquement                 |
| `ADMIN_PASSWORD`         | *(vide — à générer)*                                                              | Contrepartie en clair, jamais lue par Symfony : uniquement pour le proxy Nuxt (Basic Auth) |
| `CAL_EU_API_KEY`         | *(vide)*                                                                          | Clé API Cal.eu (Cal.com-compatible), résolue via `%env(CAL_EU_API_KEY)%` dans l'en-tête `Authorization` de l'étape `api_call` "Reserver sur Cal.eu" du workflow `planifier_entretien` (`WorkflowExecutionService::resolveEnvHeaders()`, voir §7.2) -- jamais stockée en clair dans la ligne `workflow_step`. Le worker Messenger ne relit `.env` qu'à son démarrage (§7.3) : redémarrer le conteneur worker après toute modification de cette variable |

> `DEFAULT_URI` (génération d'URL en CLI) est aussi présent, non lié à l'IA.

### 11.5 Vérifier que tout fonctionne

Toutes les requêtes ci-dessous nécessitent l'authentification HTTP Basic (`-u admin:motdepasse`, voir §10) sauf mention contraire :

- `GET /api/chat/llm-status` → doit renvoyer `status: running` (Ollama) ou `status: reachable` (endpoint distant).
- `GET /api/chat/embedding-status` → idem pour l'embedding.
- `POST /api/chat/quick-send` avec `{"message": "Bonjour"}` → doit renvoyer une réponse LLM.
- `POST /api/documents` (upload d'un `.txt`) puis `GET /api/documents/{id}/chunks` → doit renvoyer des chunks avec `vector_id` renseigné et `status: indexed`.

---

## 12. Écarts connus, limites et roadmap

### 12.1 Limites d'architecture assumées

| Limite | Détail | Impact |
| --- | --- | --- |
| **Mitigation anti-injection de prompt, pas élimination** | Le contenu RAG est délimité (`<extrait_document>`) et le prompt système précise explicitement de le traiter comme donnée, jamais comme instruction (`ChatOrchestrationService::buildDocumentsBlock()`) | Réduit mais ne garantit pas l'immunité contre un chunk de document conçu pour détourner le comportement du modèle — aucun garde-fou côté sortie (pas de modèle de modération séparé) pour rattraper ce qui passerait malgré tout |
| **Énumération d'id de conversation entre visiteurs** | `App\Security\Voter\OwnershipVoter` fait sauter toute vérification de propriétaire dès que `ROLE_ADMIN` est présent (voulu pour le vrai backoffice) ; le proxy Nuxt public s'authentifie en `ROLE_ADMIN` réel pour *chaque* requête, quel que soit le visiteur. Combiné à des ids `Conversation` entiers séquentiels, un visiteur qui devine/énumère un id peut lire/modifier/supprimer la conversation d'un autre visiteur via `GET/PATCH/DELETE /api/conversations/{id}` — trouvé pendant l'audit de sécurité, **délibérément non corrigé** (décision explicite : nécessiterait soit un compte de service dédié non-admin pour le proxy, soit des ids non séquentiels, deux chantiers à part) | Risque accepté pour l'instant. `conversations/{id}/messages` reste dans l'allowlist du proxy (nécessaire au fonctionnement du widget), donc l'exposition existe tant que ce point n'est pas traité |

Résolues depuis : authentification multi-utilisateur avec cloisonnement par propriétaire (§10), file de messages asynchrone pour le chunking/la vectorisation et le déclenchement de workflow (§6.2, §7.3), CSRF stateless sur les formulaires du backoffice (§9), streaming token-par-token via SSE hors tool-calling (§5.5, voir `docs/BACKLOG.md` pour le détail du chantier).