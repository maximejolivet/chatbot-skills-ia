# Guide d'onboarding

Point d'entrée rapide pour un nouveau contributeur. Pour le détail exhaustif, voir [`README.md`](../README.md) (vue d'ensemble), [`backend/README.md`](../backend/README.md) + [`docs/backend/SPECIFICATION.md`](backend/SPECIFICATION.md) (backend), [`docs/frontend/SPECIFICATION.md`](frontend/SPECIFICATION.md) (frontend), [`docs/DEPLOYMENT.md`](../docs/DEPLOYMENT.md) (déploiement), [`docs/BACKLOG.md`](BACKLOG.md) (historique des chantiers).

## 1. Stack technique et versions

| Composant            | Techno                                            | Version                                       |
| -------------------- | ------------------------------------------------- | --------------------------------------------- |
| Backend              | Symfony + API Platform                            | Symfony 8.1, API Platform 4.3, PHP 8.4        |
| ORM                  | Doctrine ORM + Migrations                         | 3.6.8                                         |
| Backoffice `/admin`  | Sylius Resource Bundle + Sylius Grid Bundle       | ^1.14 / ^1.16                                 |
| Assets admin         | AssetMapper + Stimulus + Tailwind (bundle local)  | —                                             |
| Frontend             | Nuxt / Vue                                        | Nuxt 4.5, Vue 3.5, TypeScript 7.0, Node.js 24 |
| Style frontend       | `@nuxtjs/tailwindcss`                             | 6.14                                          |
| Base relationnelle   | MariaDB                                           | 11.4                                          |
| Base vectorielle     | Qdrant                                            | v1.19.0                                       |
| File d'attente async | Symfony Messenger + Redis                         | 7-alpine                                      |
| Modèles IA (local)   | Ollama — non conteneurisé, tourne sur l'hôte      | `qwen3.6`, `mxbai-embed-large`                |
| Reverse proxy        | Traefik                                           | v3.5                                          |
| Qualité PHP          | PHPStan (niveau 9), PHP-CS-Fixer, Rector, PHPUnit | 2.2 / 3.95 / 2.6 / 13.3                       |
| Qualité frontend     | Prettier, Vitest, `@nuxt/test-utils`              | 3.9 / 4.1                                     |

Tout tourne en Docker Compose, routé par domaine via Traefik (`*.chatbot.localhost`). Ollama est la seule dépendance non conteneurisée — doit tourner sur l'hôte.

## 2. Architecture du projet

```
chatbot-skills-ia/
├── backend/     API Symfony + API Platform, backoffice /admin
├── frontend/    Widget chatbot Nuxt 4 (composant + proxy serveur)
├── traefik/     Reverse proxy, domaines par service
├── docs/        Cahiers des charges, backlog, collection Bruno
└── .github/     CI (lint/test/phpstan/audit), déploiement, scripts d'install
```

### Backend — organisation par domaine métier, pas par couche technique

`backend/src/` est structuré en **5 domaines** (dossiers dédiés), plus des dossiers transverses classiques Symfony :

| Domaine (dossier)  | Rôle                                                                                                        |
| ------------------ | ----------------------------------------------------------------------------------------------------------- |
| `AiProvider/`      | Abstraction des providers LLM/embedding, sélection du provider actif                                        |
| `VectorConnector/` | Wrapper Qdrant, embeddings, recherche vectorielle — app feuille (aucune dépendance sur les autres domaines) |
| `KnowledgeBase/`   | Documents, chunking, collections, catégories, FAQ                                                           |
| `Workflow/`        | Moteur d'exécution de workflows, utilisés comme outils par les agents                                       |
| `Chat/`            | Orchestration de la conversation : agents IA, RAG, tool-calling                                             |

`Workflow/` et `Chat/` se référencent mutuellement (tool-calling) ; tous les autres domaines dépendent en cascade de `VectorConnector/` (feuille), jamais l'inverse.

Dossiers transverses : `Controller/` (24, dont les contrôleurs API Platform personnalisés), `Entity/` (19, Doctrine), `Repository/` (17), `ApiResource/` (10, endpoints stateless type `HealthAction`/`QuickSendAction`), `Doctrine/` (query extensions type `OwnershipCollectionExtension`), `Security/` (voters), `EventListener/`, `Message/`+`MessageHandler/` (Messenger async), `Form/`+`Grid/` (backoffice Sylius), `Twig/` (extensions), `Enum/` (12, backed enums), `Command/`.

### Frontend — composants dumb, composables smart

```
frontend/
├── components/    Composants Vue présentationnels (Chatbot, MessageBubble, ...)
├── composables/   Logique métier (use*.ts) — un composable = une responsabilité
├── pages/         Routing fichier Nuxt (index, chat)
├── server/api/    Routes Nitro : proxy vers le backend (allowlist), endpoints propres au frontend (link-preview)
├── i18n/locales/  Chaînes traduites (une seule locale aujourd'hui : fr)
└── types/         Types TypeScript partagés
```

### Flux de bout en bout

```
Visiteur → Nuxt (SSR)
         → server/api/[...path].ts (proxy Nitro, allowlist de routes, injecte Basic Auth)
         → Symfony /api/* (API Platform)
              → MariaDB (Doctrine)
              → Qdrant (recherche vectorielle, via VectorConnector)
              → Ollama (hôte) ou endpoint OpenAI-compatible (LLM chat/embedding/analyse)
              → Redis + Messenger (indexation document, déclenchement workflow — async)
```

Le proxy Nuxt s'authentifie **toujours** en `ROLE_ADMIN` réel auprès du backend, quel que soit le visiteur — voir §5.

## 3. Commandes essentielles

Toutes les commandes `make` sont documentées par `make` (sans argument, liste tout depuis `Makefile`).

### Installation / démarrage

```bash
cp backend/.env.example backend/.env   # une fois, avant tout — voir backend/README.md#sécurité pour ADMIN_PASSWORD_HASH
make install        # backend + frontend + hooks git racine, depuis un clone frais
make start           # démarre Traefik + la stack (nécessite Ollama déjà lancé sur l'hôte)
make stop / purge    # arrêt / purge complète (conteneurs + volumes)
make services-url    # liste les URLs actives
make db-install-backend   # (ré)installe la base depuis zéro
```

### Dev server

```bash
make start                                        # backend (Docker, auto) — http://symfony.chatbot.localhost
cd frontend && npm run dev                         # frontend en dev hors Docker — http://localhost:3000
                                                    # (nécessite API_URL/ADMIN_USERNAME/ADMIN_PASSWORD, voir frontend/README.md)
```

### Tests

```bash
docker exec chatbot-symfony php bin/phpunit         # backend
cd frontend && npm run test                          # frontend (Vitest, composables uniquement)
```

### Lint / qualité / static analysis

```bash
# Backend
docker exec chatbot-symfony composer cs:check        # PHP-CS-Fixer, dry-run (composer cs:fix pour corriger)
docker exec chatbot-symfony composer phpstan          # PHPStan niveau 9 — voir la skill dédiée .claude/skills/phpstan/
docker exec chatbot-symfony composer rector:check      # Rector, dry-run — pas branché en CI, manuel seulement
docker exec chatbot-symfony php bin/console lint:yaml config --parse-tags
docker exec chatbot-symfony php bin/console lint:twig templates
docker exec chatbot-symfony php bin/console lint:container

# Frontend
cd frontend && npm run format:check                    # Prettier — aucun ESLint configuré

# Repo
make audit           # composer audit + npm outdated/audit
make actionlint       # lint des workflows GitHub Actions
```

Ces commandes sont exactement ce que `ci-backend.yml`/`audit.yml` exécutent en CI — un `composer cs:check && composer phpstan && php bin/phpunit` local reproduit le gate avant de pousser.

### Autres

```bash
docker exec chatbot-symfony php bin/console tailwind:build   # recompiler le CSS admin après modif de classes Tailwind
docker exec chatbot-symfony php bin/console app:user:create <email> <password> [--role=ROLE_USER]
make rebuild SERVICE=<name>    # rebuild un seul service Docker (app, nuxt, database, qdrant, phpmyadmin)
```

## 4. Conventions de code et patterns utilisés

- **Commits** : format sémantique obligatoire, scope obligatoire, **sans attribution IA** — voir la skill `.claude/skills/semantic-commit-messages/SKILL.md`. Pas de scope libre : `type(scope): emoji description`.
- **Organisation par domaine métier**, pas par couche technique (`src/Chat/`, pas `src/Service/ChatService.php`) — voir §2.
- **Repositories Doctrine + Sylius** : `class XRepository extends ServiceEntityRepository implements SyliusRepositoryInterface`, avec toujours les deux docblocks génériques `@extends ServiceEntityRepository<X>` **et** `@implements SyliusRepositoryInterface<X>` (l'omission du second casse PHPStan — conflit de générique, voir la skill phpstan). `use ResourceRepositoryTrait;` pour l'intégration Sylius.
- **Entités backoffice** : `implements Sylius\Resource\Model\ResourceInterface` — condition pour qu'une entité soit gérable dans `/admin` sans code de CRUD dédié (voir `backend/README.md#backoffice-admin` pour ajouter une ressource).
- **Sécurité par ressource, pas globale** : chaque `#[ApiResource(security: ...)]` porte sa propre règle. Le `security:` déclaratif d'API Platform **ne s'applique pas de façon fiable aux contrôleurs personnalisés** (vérifié empiriquement, cause d'un vrai incident — voir §5) : ceux-ci portent systématiquement leur propre `#[IsGranted(...)]` explicite.
- **Enums PHP backed** (valeurs string) plutôt que colonnes ENUM SQL — `Enum/` (12 fichiers).
- **DTOs immuables** pour le transport (`ChatMessage`, `ToolCall`, `ToolSpec`, `CompletionResult`, `EmbeddingResult` dans `AiProvider/Client/`) plutôt que des tableaux associatifs entre couches.
- **Async par défaut pour les opérations lentes** (indexation de document, déclenchement de workflow via `/trigger`), **sauf le tool-calling LLM** qui reste délibérément synchrone (la boucle a besoin du résultat immédiatement) — ne pas "corriger" ça en asynchrone.
- **Types stricts, jamais affaiblis pour satisfaire l'outillage** : un `?int` réellement non-null à un point donné se narrow via `?? throw new \LogicException(...)`, pas en changeant la signature ni en castant du `mixed` à l'aveugle. Détail complet et patterns récurrents : `.claude/skills/phpstan/SKILL.md`.
- **Classes de service `final`** (ex. `QdrantClient`, `ProviderSelectionService`) : impossible à doubler avec PHPUnit (`ClassIsFinalException`) — les tests injectent un vrai `MockHttpClient`/DBAL fake à la construction plutôt que de mocker la classe elle-même.
- **Frontend — logique dans les composables, présentation dans les composants** : `use*.ts` (ex. `useChatbot.ts`, cœur fonctionnel) porte toute la logique métier/état, les `.vue` restent présentationnels.
- **Le frontend ne parle jamais directement au backend depuis le navigateur** : tout passe par le proxy Nitro (`server/api/`), qui applique une **allowlist explicite** de routes (`ALLOWED_ROUTES`) et injecte l'auth Basic — ajouter un nouvel appel API côté widget veut dire l'ajouter à cette allowlist.
- **Thème clair/sombre par tokens CSS**, pas de hex figés (`assets/css/main.css`, pattern shadcn/ui — variables RGB consommées via `rgb(var(--x) / <alpha-value>)` dans Tailwind).
- **i18n** : toute chaîne visible vit dans `i18n/locales/fr.json`, jamais en dur dans un composant, même s'il n'existe qu'une seule locale aujourd'hui.

## 5. Points d'attention

**Migrations Doctrine cassées après le premier run.** `doctrine:migrations:migrate` plante dès la 2ᵉ exécution sur cet environnement (collation MariaDB 11 absente d'`information_schema` sur ce serveur). `make db-install-backend` contourne en créant la base à la main avec une collation explicite avant de migrer. Deux migrations récentes ont dû être appliquées manuellement (`dbal:run-sql` + insertion manuelle dans `doctrine_migration_versions`) — toute nouvelle migration future suivra probablement le même chemin ; voir le commentaire dans `Makefile` et `docs/BACKLOG.md` avant d'y toucher.

**Risque de sécurité connu, accepté, non corrigé.** Le proxy Nuxt s'authentifie toujours en `ROLE_ADMIN` réel, et `OwnershipVoter` laisse passer tout `ROLE_ADMIN` sans vérifier le propriétaire. Combiné à des ids `Conversation` entiers séquentiels, un visiteur qui devine un id peut lire/modifier/supprimer la conversation d'un autre visiteur (`GET/PATCH/DELETE /api/conversations/{id}`). Détail et rationale de la décision : `docs/backend/SPECIFICATION.md` §12.1.

**Mitigation anti-injection de prompt, pas élimination.** Le contenu RAG est délimité et le prompt système précise de le traiter comme donnée — réduit mais ne garantit pas l'immunité, aucun garde-fou côté sortie.

**Pas de 2FA sur `/admin`, pas de CAPTCHA sur `quick-send`** — items encore ouverts du backlog (`docs/BACKLOG.md`), le seul endpoint public anonyme reste protégé par rate-limiting seul.

**Pas d'infra de test avec vraie base de données** (pas de `KernelTestCase`) — le code DQL-lourd (`AnalyticsService`, agrégats admin) n'a pas de test automatisé, vérifié manuellement contre des données réelles. Les tests backend sont uniquement unitaires.

**Baseline PHPStan (871 lignes)** : ne contient plus de bugs de type/nullable/paramètre (nettoyé — voir `.claude/skills/phpstan/SKILL.md`), uniquement des préférences de style `phpstan-strict-rules` (casts, ternaires, comparaisons booléennes strictes). Ne pas y ajouter d'erreur nouvelle sans investiguer d'abord.

**Frontend** : aucun ESLint configuré (Prettier seul) ; tests unitaires composables uniquement, aucun test de composant `.vue` ni e2e navigateur ; **pas de déploiement en production** pour l'instant (seul le backend a un pipeline de déploiement, voir `docs/DEPLOYMENT.md`) — le durcissement CSP existant ne protège donc encore personne en pratique.

**Rector configuré mais pas branché en CI** — `composer rector:check` est un outil manuel, jamais exécuté automatiquement ; les modernisations qu'il proposerait ne sont pas garanties appliquées.

**Ollama tourne sur l'hôte, pas en conteneur** — `make check-ollama` vérifie sa présence avant `make start`, mais rien ne le démarre automatiquement.

**Secrets** : `backend/.env`/`frontend/.env` sont ignorés par Git et doivent être créés manuellement (seul `backend/.env.example` existe). Les identifiants de la collection Bruno (`docs/backend/bruno/environments/*.bru`) sont exclus via une règle `.gitignore` qui a déjà cessé de matcher une fois après un déplacement de dossier — vérifier `git check-ignore -v` avant tout déplacement futur de cette collection (voir `SECURITY.md`).

**Champs volontairement non lisibles par l'API** : `AiProviderConfig.apiKey`, `Conversation.user`, `WorkflowExecution.triggeredBy` (`#[ApiProperty(readable: false)]`) — ne jamais leur ajouter de groupe de sérialisation qui les exposerait.
