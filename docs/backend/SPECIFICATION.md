# Cahier des charges — Backend Symfony (`backend/`)

## 1. Présentation générale

`backend` est une API backend pour un **chatbot IA d'entreprise** combinant :

- un **moteur de conversation LLM** (chat completion, mémoire de conversation, streaming SSE) ;
- un système de **RAG** (Retrieval-Augmented Generation) basé sur **Qdrant** (base vectorielle) pour ancrer les réponses du LLM sur une base documentaire interne ;
- un système d'**agents IA** capables d'appeler des **outils** (tool-calling) qui déclenchent des **workflows** métier configurables (appels API, webhooks, transformations de données, conditions, etc.) ;
- un **backoffice** d'administration (CRUD) pour piloter les providers IA, les documents, les agents, les workflows, etc.

Le backend est bâti en **Symfony 8 / API Platform 4 / PHP 8.4**, organisé en 5 domaines métier. Certaines limites d'architecture sont assumées (authentification mono-compte sans scoping multi-utilisateur, absence de file de messages asynchrone) — voir [§12](#12-écarts-connus-limites-et-roadmap).

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
| ORM / Base de données | **Doctrine ORM 3.6** + **Doctrine Migrations 4.0**                                                   | **PostgreSQL 15/16** (`postgresql://...`)                                                                                                                                                                                                                                                                                         |
| Backoffice / admin    | **Sylius Resource Bundle 1.14** + **Sylius Grid Bundle 1.16** + **Symfony Form**                     | CRUD générique piloté par configuration, exposé sous `/admin`                                                                                                                                                                                                                                                                     |
| Pagination admin      | **Pagerfanta** (`pagerfanta/doctrine-orm-adapter`, `babdev/pagerfanta-bundle`)                       | Utilisé par les grilles Sylius                                                                                                                                                                                                                                                                                                    |
| Base vectorielle      | **Qdrant** (image `qdrant/qdrant:v1.19.0`)                                                           | Stockage et recherche des embeddings, communication via REST (Symfony HttpClient)                                                                                                                                                                                                                                                 |
| LLM local             | **Ollama** (`OLLAMA_BASE_URL`)                                                                       | Chat + embeddings + analyse de documents, via l'API `/api/chat` d'Ollama (tool-calling natif)                                                                                                                                                                                                                                     |
| LLM distant           | **Endpoint OpenAI-compatible** (ex. OVHcloud AI Endpoints — `gpt-oss-120b`)                          | Format Chat Completions standard (`/v1/chat/completions`)                                                                                                                                                                                                                                                                         |
| Client HTTP           | **Symfony HttpClient**                                                                               | Utilisé pour tous les appels sortants : Ollama, endpoints OpenAI-compatibles, Qdrant, `api_call`/`webhook` des workflows                                                                                                                                                                                                          |
| Extraction de texte   | **smalot/pdfparser 2.12** (PDF), `ZipArchive` natif PHP (DOCX), fonctions natives (TXT/MD/HTML/JSON) | Pipeline d'ingestion documentaire                                                                                                                                                                                                                                                                                                 |
| CORS                  | **NelmioCorsBundle 2.6**                                                                             | Origines autorisées configurables via `CORS_ALLOW_ORIGIN` (regex)                                                                                                                                                                                                                                                                 |
| Style backoffice      | **Tailwind CSS via CDN**                                                                             | Pas de pipeline d'assets (pas d'AssetMapper/Webpack Encore installé)                                                                                                                                                                                                                                                              |
| Conteneurisation      | **Docker** (`Dockerfile`, `compose.yaml`, inclus depuis le `compose.yaml` racine)                    | Services : `app` (Symfony), `database` (Postgres), `qdrant`, `nuxt` (frontend de démo)                                                                                                                                                                                                                                            |
| Reverse proxy (dev)   | **Traefik**                                                                                          | Routage par domaine (`*.chatbot.localhost`) via provider **fichier** (`traefik/dynamic.yml`), pas par labels Docker — les services rejoignent le réseau externe `chatbot-proxy` mais ne portent aucun label `traefik.*` (le client Docker embarqué dans l'image Traefik échoue à négocier sa version d'API avec ce moteur Docker) |

**Ce qui n'est délibérément pas présent** (voir §12) :
- Pas de système d'authentification **multi-utilisateur** (un seul compte admin partagé, provider `memory`, voir §10) — pas de modèle `User` en base, pas de rôles différenciés, pas de scoping des ressources par utilisateur.
- Pas de file de messages / worker asynchrone (pas de Symfony Messenger, pas de Redis) — tout le pipeline d'ingestion documentaire et d'exécution de workflow tourne **de façon synchrone** dans la requête HTTP.
- Pas de CSRF stateless sur les formulaires du backoffice (nécessiterait un asset pipeline) — seules les actions de suppression utilisent le CSRF classique basé session.

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
├── Entity/                  # 14 entités Doctrine (voir §4)
├── Enum/                    # enums PHP 8.1 pour chaque champ à choix fermé
├── Repository/               # repositories Doctrine (1 par entité)
├── Controller/               # contrôleurs API Platform custom + Admin/DashboardController
├── ApiResource/               # ressources API Platform "virtuelles" (non-entités) : quick-send, recherche/stats vectorielles, statut LLM/embedding
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

14 entités Doctrine, toutes avec un ID auto-incrémenté. Sauf indication contraire, `createdAt`/`updatedAt` sont gérés automatiquement (`#[ORM\HasLifecycleCallbacks]` + `#[ORM\PreUpdate]`).

### 4.1 `ai_providers`

**`AiProviderConfig`** — une configuration de provider IA, par usage.
| Champ                                       | Type                        | Remarque                                                                                    |
| ------------------------------------------- | --------------------------- | ------------------------------------------------------------------------------------------- |
| `name`                                      | string(200), unique         |                                                                                             |
| `usage`                                     | enum `AiProviderUsage`      | `chat` \| `embedding`                                                                       |
| `provider`                                  | enum `AiProvider`           | `ollama` \| `api_endpoint`                                                                  |
| `apiEndpoint`, `apiKey`, `model`, `baseUrl` | string, nullable            | dépend du provider                                                                          |
| `isActive`                                  | bool                        | une seule config active par usage est prise en compte                                       |
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

> Non exposée en CRUD direct — consultable uniquement via `GET /vector/stats`. Aucun champ `user` : pas de scoping par utilisateur.

### 4.3 `knowledge_base`

**`DocumentCategory`** — `name` (unique), `description`. CRUD complet.

**`Faq`** — `question` (500), `answer` (text), `category` (nullable), `isActive`, `tags` (array). CRUD complet. Aucun champ `created_by` (pas de scoping par utilisateur).

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

Aucun champ `triggered_by`.

### 4.5 `chat`

**`Conversation`** — CRUD complet.
| Champ      | Type                                                                       |
| ---------- | -------------------------------------------------------------------------- |
| `title`    | string(200)                                                                |
| `isActive` | bool                                                                       |
| `messages` | `OneToMany` vers `Message`, ordonné par `createdAt`, `orphanRemoval: true` |

**Limite la plus significative** du modèle actuel : aucun champ `user` — les conversations ne sont scopées par aucun utilisateur, quiconque connaît un ID peut le lire/écrire.

**`Message`** — pas de `#[ApiResource]` propre (exposé via les actions de `Conversation`).
| Champ          | Type                                                                       |
| -------------- | -------------------------------------------------------------------------- |
| `conversation` | `ManyToOne`, `onDelete: CASCADE`                                           |
| `role`         | enum `MessageRole` : `user` \| `assistant` \| `system` \| `tool`           |
| `content`      | text                                                                       |
| `metadata`     | array — contient `token_usage` et `tool_calls` pour les messages assistant |

**`AiAgent`** — lecture seule via REST (`GetCollection(paginationEnabled: false)` + `Get` uniquement), écriture réservée au backoffice.
| Champ                          | Type                                                                                                              |
| ------------------------------ | ----------------------------------------------------------------------------------------------------------------- |
| `name` (unique), `description` | string                                                                                                            |
| `systemPrompt`                 | text — remplace le prompt système par défaut si non vide                                                          |
| `workflows`                    | `ManyToMany` vers `Workflow` (table `ai_agent_workflow`) — `getActiveWorkflows()` filtre les workflows `isActive` |
| `collection`                   | `OneToOne` côté inverse vers `Collection` — la base de connaissance RAG de l'agent                                |
| `isActive`                     | bool                                                                                                              |

### 4.6 Enums PHP (backed enums, valeurs string)

| Enum                      | Valeurs                                                                                |
| ------------------------- | -------------------------------------------------------------------------------------- |
| `AiProvider`              | `ollama`, `api_endpoint`                                                               |
| `AiProviderUsage`         | `chat`, `embedding`                                                                    |
| `AiProviderTestStatus`    | `unknown`, `success`, `error`                                                          |
| `DocumentFileType`        | `pdf`, `txt`, `docx`, `md`, `html`, `json`                                             |
| `DocumentStatus`          | `pending`, `processing`, `indexed`, `error`                                            |
| `MessageRole`             | `user`, `assistant`, `system`, `tool`                                                  |
| `WorkflowExecutionStatus` | `pending`, `running`, `completed`, `failed`, `cancelled`                               |
| `WorkflowStatus`          | `draft`, `active`, `inactive`                                                          |
| `WorkflowStepType`        | `api_call`, `email`, `notification`, `data_transform`, `condition`, `delay`, `webhook` |
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

1. Recherche d'une **`AiProviderConfig`** active pour cet usage en base (gérée depuis le backoffice, `/admin/ai-provider-configs`) → priorité absolue.
2. Sinon, repli sur les **variables d'environnement** `AI_PROVIDER` (`ollama` ou `api_endpoint`) + `AI_API_*` / `OLLAMA_*`.
3. Si le provider `api_endpoint` choisi n'a pas de clé API valide, **repli silencieux sur Ollama** (avec un log `warning`).

Le provider est **re-résolu à chaque appel** (pas de cache d'instance) : un changement de config admin prend effet immédiatement, sans redémarrage.

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
   - Sinon, pour chaque `ToolCall` demandé : résout le `Workflow` correspondant (par nom normalisé), l'exécute **de façon synchrone** via `WorkflowExecutionService::execute()`, réinjecte le résultat comme message `role: tool`, et redemande une completion.
6. Si le budget d'itérations est épuisé, force une dernière completion **sans outils** pour obtenir une réponse finale.

Chaque appel d'outil est tracé dans `toolTrace` (nom, arguments, statut, sortie) et renvoyé au frontend via `ChatReplyResult::toolCalls`.

**Prompt système par défaut** (`ChatOrchestrationService::DEFAULT_SYSTEM_PROMPT`) :
> *« Tu es un assistant IA utile et bienveillant... Tu réponds en français, de façon claire et concise... Utilise les documents pertinents fournis en contexte... Si tu ne connais pas la réponse, dis-le honnêtement. »*

### 5.4 Points d'entrée de la conversation — `ChatService`

Une façade à deux modes :
- **`sendMessage(Conversation, message, agentId?)`** : persiste le message utilisateur et la réponse assistant en base, utilisé par `/api/conversations/{id}/messages`.
- **`quickSend(message, agentId?)`** : mode anonyme, **rien n'est persisté**, utilisé par `POST /api/chat/quick-send` (ce que consomment les frontends de démo).

### 5.5 Streaming (SSE)

`POST /api/conversations/{id}/stream` (`ConversationStreamController`) : génère la réponse **entièrement côté serveur** (appel bloquant à `ChatService::sendMessage`), puis l'émet en *Server-Sent Events* (`user_message` → `ai_complete` → `done`). Ce **n'est pas** du streaming token par token — le tool-calling n'est pas compatible avec le streaming LLM natif dans cette architecture (limite assumée).

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

> **Synchrone par nécessité** : sans file de messages, tout le pipeline tourne **dans la requête HTTP d'upload** (`POST /api/documents`) — bloquant. À revoir avec Symfony Messenger si la latence d'upload devient un problème (voir §12).

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

**`VectorSearchService`** — orchestration RAG au sens strict :
- `search()` : embed la requête → recherche dans Qdrant → reformate les résultats (`content`, `document_id`, `document_title`, `chunk_index`, `score`, `metadata`) → **journalise** la requête dans `SearchQuery` (si un `VectorIndex` existe pour cette collection).
- `addDocumentChunks()` : analyse le document, embed tous les chunks en batch, construit les points Qdrant (payload enrichi), upsert.
- ID de point Qdrant **déterministe** : `Uuid::v5(NAMESPACE_DNS, "doc_{id}_chunk_{index}")` — permet de régénérer le même ID plus tard pour la suppression sans avoir besoin de le stocker (bien qu'il soit aussi stocké sur `DocumentChunk.vectorId`).

**`RagContextService`** (dans `chat`) — le point d'entrée utilisé par l'orchestrateur de conversation : résout la collection de l'agent, puis délègue à `VectorSearchService::search()` (top **5** résultats par défaut). Toute exception est absorbée et journalisée — **une erreur RAG ne bloque jamais la génération de réponse**, elle prive seulement le LLM de contexte documentaire.

### 6.6 Modèle d'embedding

- Modèle par défaut : **`mxbai-embed-large`** (Ollama), dimension **1024** — une constante partagée par `QdrantClient::VECTOR_SIZE` et `VectorIndex.dimension`.
- `EmbeddingService` (dans `vector_connector`) est un wrapper léger sur `ProviderSelectionService::getEmbeddingClient()`, qui expose aussi l'usage de tokens du dernier appel (`getLastUsage()`/`getBatchUsage()`), consommé pour enrichir `Document.metadata.embedding_usage`.

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
| `condition`      | Évalue une condition (`equals`, `not_equals`, `contains`, `greater_than`, `less_than`) sur un champ, exécute une `true_action`/`false_action` (seule l'action `set_field` est actuellement implémentée) |
| `delay`          | `sleep()` bloquant pendant le nombre de secondes configuré                                                                                                                                              |
| `email`          | **Stub** : journalise seulement, aucun backend d'envoi réel configuré                                                                                                                                   |
| `notification`   | **Stub** : journalise seulement                                                                                                                                                                         |

### 7.3 Synchronicité

Sans file de messages, `execute()` tourne **toujours de façon synchrone**, que ce soit via `POST /api/workflows/{id}/trigger` (déclenchement manuel/API), `POST /api/workflows/{id}/test` (test), ou via le tool-calling du chat — la réponse HTTP attend la fin réelle de l'exécution.

### 7.4 Suppression = soft delete

`DELETE /api/workflows/{id}` ne supprime pas la ligne : elle passe `isActive = false` (`WorkflowSoftDeleteController`).

---

## 8. Référence API complète

Base URL : `http://symfony.chatbot.localhost` (dev, via Traefik). Toutes les ressources API Platform sont sous **`/api`** ; documentation interactive Hydra/JSON-LD native sur `/api`, documentation Swagger/OpenAPI pure sur **`/doc`**.

> ⚠️ **Authentification HTTP Basic requise** sur tous ces endpoints (compte admin unique, voir §10).

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
| `POST`                        | `/api/vector/search`         | **Recherche vectorielle canonique** — body `{query, collection_name?, category_id?, limit?}` |
| `GET`                         | `/api/vector/stats`          | Nombre d'index actifs, total des requêtes journalisées, 10 `SearchQuery` les plus récentes   |

### 8.3 `knowledge_base`

| Méthode                       | URL                               | Description                                                                                                                                                                                                       |
| ----------------------------- | --------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `GET`/`POST`/`PATCH`/`DELETE` | `/api/document_categories[/{id}]` | CRUD des catégories                                                                                                                                                                                               |
| `GET`/`POST`/`PATCH`/`DELETE` | `/api/faqs[/{id}]`                | CRUD des FAQ                                                                                                                                                                                                      |
| `GET`/`POST`/`PATCH`/`DELETE` | `/api/collections[/{id}]`         | CRUD des collections                                                                                                                                                                                              |
| `GET`                         | `/api/documents`                  | Liste des documents                                                                                                                                                                                               |
| `GET`                         | `/api/documents/{id}`             | Détail                                                                                                                                                                                                            |
| `PATCH`                       | `/api/documents/{id}`             | Mise à jour (métadonnées, pas le fichier)                                                                                                                                                                         |
| `POST`                        | `/api/documents`                  | **Upload multipart** (`file`, `title`, `description?`, `category_id?`) — extensions autorisées : `pdf, txt, docx, md, html, json`, taille max **10 Mo**. Déclenche `chunkDocument()` + `vectorize()` en synchrone |
| `DELETE`                      | `/api/documents/{id}`             | Supprime les vecteurs Qdrant + les chunks + le fichier physique + la ligne                                                                                                                                        |
| `POST`                        | `/api/documents/{id}/process`     | Ré-indexation complète (supprime puis recrée les chunks/vecteurs), **synchrone**                                                                                                                                  |
| `GET`                         | `/api/documents/{id}/chunks`      | Liste des chunks du document                                                                                                                                                                                      |

### 8.4 `workflows`

| Méthode              | URL                             | Description                                                                                             |
| -------------------- | ------------------------------- | ------------------------------------------------------------------------------------------------------- |
| `GET`/`POST`/`PATCH` | `/api/workflows[/{id}]`         | CRUD (partiel) des workflows                                                                            |
| `DELETE`             | `/api/workflows/{id}`           | **Soft delete** (`isActive = false`)                                                                    |
| `GET`                | `/api/workflows/{id}/steps`     | Liste des étapes actives, ordonnées                                                                     |
| `POST`               | `/api/workflows/{id}/steps`     | Création d'une étape (`name`, `step_type`, `order`, `configuration?`, `is_active?`)                     |
| `POST`               | `/api/workflows/{id}/trigger`   | Déclenchement (rejette si le workflow n'est pas `active`) — **synchrone**, renvoie l'exécution terminée |
| `POST`               | `/api/workflows/{id}/test`      | Exécution de test — **synchrone**, aucune vérification de statut                                        |
| `GET`                | `/api/workflow_executions`      | Liste des exécutions (lecture seule)                                                                    |
| `GET`                | `/api/workflow_executions/{id}` | Détail d'une exécution                                                                                  |

### 8.5 `chat`

| Méthode                       | URL                                | Description                                                                                                    |
| ----------------------------- | ---------------------------------- | -------------------------------------------------------------------------------------------------------------- |
| `GET`/`POST`/`PATCH`/`DELETE` | `/api/conversations[/{id}]`        | CRUD des conversations                                                                                         |
| `GET`                         | `/api/conversations/{id}/messages` | Historique des messages                                                                                        |
| `POST`                        | `/api/conversations/{id}/messages` | Envoie un message utilisateur, le persiste + renvoie la réponse de l'assistant (body : `{message, agent_id?}`) |
| `POST`                        | `/api/conversations/{id}/stream`   | Idem, réponse en **SSE** (`text/event-stream`)                                                                 |
| `GET`                         | `/api/ai_agents`                   | Liste des agents (lecture seule, pagination désactivée)                                                        |
| `GET`                         | `/api/ai_agents/{id}`              | Détail d'un agent                                                                                              |
| `POST`                        | `/api/chat/quick-send`             | Chat **anonyme, non persisté** (body : `{message, agent_id?}`) — utilisé par les frontends de démo             |
| `GET`                         | `/api/chat/llm-status`             | Statut du provider LLM actif (`reachable`/`running`/`error`/`not_reachable`)                                   |
| `GET`                         | `/api/chat/embedding-status`       | Statut du provider d'embedding actif                                                                           |

### 8.6 Formats de réponse notables

**`POST /api/chat/quick-send`** :
```json
{
  "response": "...",
  "conversation_id": null,
  "status": "success",
  "token_usage": {"prompt_tokens": 0, "completion_tokens": 0, "total_tokens": 0, "source": "provider|estimated", "provider": "ollama|api_endpoint", "model": "..."},
  "tool_calls": [{"tool": "...", "arguments": {...}, "status": "completed|failed", "output": {...}}]
}
```

**`POST /api/vector/search`** :
```json
{"query": "...", "results": [{"id": "...", "score": 0.87, "content": "...", "document_id": 12, "document_title": "...", "chunk_index": 3, "metadata": {...}}], "total": 5}
```

---

## 9. Backoffice admin (`/admin`)

Construit avec **Sylius Resource/Grid Bundle** — CRUD générique piloté par config YAML (`config/routes/admin.yaml`) + repository + form + grid, sans thème packagé (templates Twig maison, `templates/admin/crud/*.html.twig`), stylé en Tailwind CDN.

| Ressource                              | URL                          | Opérations                                                                                   |
| -------------------------------------- | ---------------------------- | -------------------------------------------------------------------------------------------- |
| `AiProviderConfig`                     | `/admin/ai-provider-configs` | CRUD complet                                                                                 |
| `VectorIndex`                          | `/admin/vector-indexes`      | CRUD complet                                                                                 |
| `SearchQuery`                          | `/admin/search-queries`      | Lecture seule (`index`, `show`)                                                              |
| `DocumentCategory`                     | `/admin/document-categories` | CRUD complet                                                                                 |
| `Faq`                                  | `/admin/faqs`                | CRUD complet                                                                                 |
| `Collection`                           | `/admin/collections`         | CRUD complet                                                                                 |
| `Document`                             | `/admin/documents`           | `index`, `show`, `update`, `delete` — **pas de création** (réservée à `POST /api/documents`) |
| `Workflow` (+ `WorkflowStep` imbriqué) | `/admin/workflows`           | CRUD complet                                                                                 |
| `WorkflowExecution`                    | `/admin/workflow-executions` | Lecture seule                                                                                |
| `AiAgent`                              | `/admin/ai-agents`           | CRUD complet — **seul moyen de créer/modifier un agent**, l'API REST étant en lecture seule  |
| `Conversation`                         | `/admin/conversations`       | CRUD complet                                                                                 |
| `Message`                              | `/admin/messages`            | Lecture seule                                                                                |

Pour ajouter une 14ᵉ ressource : une entité `implements ResourceInterface`, un repository avec `ResourceRepositoryTrait`, une classe `App\Form\XType`, une classe `App\Grid\XGrid` (`#[AsGrid]`), une entrée dans `config/packages/sylius_resource.yaml` et `config/routes/admin.yaml`. Le rendu des champs est mutualisé via `App\Twig\AdminExtension::fieldValue()` (basé sur `PropertyAccessor`, gère nativement enums/dates/bools/relations/collections).

**CSRF** : désactivé sur les formulaires (nécessiterait un asset pipeline non installé) ; les suppressions utilisent le CSRF classique basé session de Symfony.

---

## 10. Sécurité

**État actuel : authentification HTTP requise partout (un seul compte admin), pas d'autorisation multi-utilisateur.**

- `config/packages/security.yaml` définit deux firewalls sur un provider `memory` unique (`ADMIN_USERNAME`/`ADMIN_PASSWORD_HASH`, env) :
  - **`admin`** (`^/admin`) : `form_login` classique (session cookie) — page `/admin/login`, déconnexion via `/admin/logout`.
  - **`api`** (`^/`, catch-all) : `http_basic`, `stateless: true` — couvre `/api/*` et `/doc`, adapté à un client machine (curl, scripts, proxy serveur d'un frontend).
- `access_control` : `ROLE_ADMIN` requis sur `^/admin` et `^/(api|doc)`, seule `^/admin/login` reste `PUBLIC_ACCESS`.
- Conséquence : `/api/*`, `/doc` et `/admin/*` renvoient `401`/redirigent vers le login sans les identifiants du compte admin. Le frontend Nuxt (`frontend/`) s'authentifie de façon transparente pour l'utilisateur final via son proxy serveur (`ADMIN_USERNAME`/`ADMIN_PASSWORD` injectés en Basic Auth côté Nitro, voir le cahier des charges frontend).
- **Limite assumée** : un seul compte partagé, pas de modèle `User` en base, pas de rôles différenciés, pas de scoping par utilisateur (voir §12 — les champs `user`/`uploaded_by`/`created_by`/`triggered_by` restent absents des entités : ce n'est pas un système multi-utilisateur, seulement un verrou d'accès global).
- **CORS** (`nelmio_cors.yaml`) : origines autorisées via la regex `CORS_ALLOW_ORIGIN` (par défaut `^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$` — dev uniquement), méthodes `GET, OPTIONS, POST, PUT, PATCH, DELETE`, headers `Content-Type, Authorization`.
- `AiProviderConfig.apiKey` est `#[ApiProperty(readable: false)]` : jamais renvoyé par l'API (`GET`/`GetCollection`), uniquement accepté en écriture (`POST`/`PATCH`). Reste visible dans le formulaire d'édition du backoffice (`/admin/ai-provider-configs/{id}/edit`), nécessaire pour le modifier — mais cette page est maintenant elle-même protégée par le firewall `admin`.

---

## 11. Installation et mise en place

### 11.1 Prérequis

- **Docker** + Docker Compose (méthode recommandée), **ou** PHP 8.4 + Composer + Symfony CLI en local.
- Un serveur **Ollama** accessible (local ou distant) si `AI_PROVIDER=ollama`, avec les modèles `mxbai-embed-large`, `gpt-oss:20b` (ou équivalents) déjà `pull`és.
- Accès réseau à **PostgreSQL 15/16** et **Qdrant**.

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
| `database` | PostgreSQL (`postgres:${POSTGRES_VERSION:-16}-alpine`)               | port aléatoire                                                 |
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

Il faut alors fournir soi-même une base PostgreSQL et une instance Qdrant joignables aux URLs configurées dans `.env` (et y générer `ADMIN_PASSWORD_HASH`, voir §10 — requis quel que soit le mode d'installation), et lancer les migrations :

```bash
php bin/console doctrine:migrations:migrate
```

(6 migrations présentes dans `migrations/`, couvrant l'ensemble du schéma des 14 entités.)

### 11.4 Variables d'environnement

Fichier de référence : `.env.example`.

| Variable                 | Défaut / exemple                                                                         | Rôle                                                                                       |
| ------------------------ | ---------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| `APP_ENV`                | `dev`                                                                                    | Environnement Symfony                                                                      |
| `APP_SECRET`             | (généré)                                                                                 | Secret applicatif Symfony                                                                  |
| `DATABASE_URL`           | `postgresql://postgres:postgres@127.0.0.1:5432/chatbot_db?serverVersion=15&charset=utf8` | Connexion Doctrine                                                                         |
| `CORS_ALLOW_ORIGIN`      | `^https?://(localhost\|127\.0\.0\.1)(:[0-9]+)?$`                                         | Regex des origines autorisées                                                              |
| `AI_PROVIDER`            | `ollama`                                                                                 | `ollama` \| `api_endpoint` — fallback quand aucune `AiProviderConfig` active n'existe      |
| `OLLAMA_BASE_URL`        | `http://localhost:11434`                                                                 | URL du serveur Ollama                                                                      |
| `OLLAMA_EMBEDDING_MODEL` | `mxbai-embed-large`                                                                      | Modèle d'embedding (dimension 1024)                                                        |
| `OLLAMA_ANALYSIS_MODEL`  | `gpt-oss:20b`                                                                            | Modèle dédié à l'analyse de documents                                                      |
| `OLLAMA_CHAT_MODEL`      | `gpt-oss:20b`                                                                            | Modèle de chat par défaut (Ollama)                                                         |
| `AI_API_ENDPOINT`        | ex. endpoint OVHcloud AI Endpoints `.../v1/chat/completions`                             | URL de l'endpoint OpenAI-compatible                                                        |
| `AI_API_KEY`             | *(vide)*                                                                                 | Clé API de l'endpoint distant                                                              |
| `AI_API_MODEL`           | `gpt-oss-120b`                                                                           | Modèle utilisé sur l'endpoint distant                                                      |
| `AI_API_TIMEOUT`         | `30`                                                                                     | Timeout HTTP (secondes)                                                                    |
| `QDRANT_HOST`            | `localhost`                                                                              | Hôte Qdrant                                                                                |
| `QDRANT_PORT`            | `6333`                                                                                   | Port REST Qdrant                                                                           |
| `QDRANT_API_KEY`         | *(vide)*                                                                                 | Clé API Qdrant (si sécurisé)                                                               |
| `ADMIN_USERNAME`         | `admin`                                                                                  | Identifiant du compte admin unique (§10), utilisé par les deux firewalls                   |
| `ADMIN_PASSWORD_HASH`    | *(vide — à générer)*                                                                     | Hash bcrypt du mot de passe admin (`bin/console security:hash-password`)                   |
| `ADMIN_PASSWORD`         | *(vide — à générer)*                                                                     | Contrepartie en clair, jamais lue par Symfony : uniquement pour le proxy Nuxt (Basic Auth) |

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

| Limite                                    | Détail                                                                                                                                                                                                                                 | Impact                                                                                                                                                                               |
| ----------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Authentification mono-compte**          | `security.yaml` protège `/api` (HTTP Basic) et `/admin` (form login) derrière un seul compte admin (provider `memory`) ; aucun scoping par utilisateur (pas de champ `user`/`uploaded_by`/`created_by`/`triggered_by` sur les entités) | `/api` et `/admin` nécessitent les identifiants admin, mais conversations, executions et documents restent visibles/modifiables par quiconque a ce compte (pas de multi-utilisateur) |
| **Pas de file de messages**               | Pas de Symfony Messenger/Redis                                                                                                                                                                                                         | Chunking/vectorisation de documents et déclenchement de workflows tournent **en synchrone, bloquant** dans la requête HTTP, plutôt qu'en tâche de fond                               |
| **Streaming non combiné au tool-calling** | `ConversationStreamController` génère la réponse complète puis l'émet en SSE                                                                                                                                                           | Pas de vrai streaming token-par-token pendant l'exécution d'outils                                                                                                                   |
| **CSRF stateless désactivé**              | Nécessiterait un asset pipeline (AssetMapper) non installé                                                                                                                                                                             | Formulaires du backoffice sans protection CSRF stateless (seules les suppressions ont du CSRF session-based)                                                                         |