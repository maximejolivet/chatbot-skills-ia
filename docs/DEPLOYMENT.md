# Déploiement

[![CI (backend)](https://github.com/maximejolivet/symfony-nuxt-ia-rag-chatbot/actions/workflows/ci-backend.yml/badge.svg)](https://github.com/maximejolivet/symfony-nuxt-ia-rag-chatbot/actions/workflows/ci-backend.yml)
[![Deploy chat-ia (backend)](https://github.com/maximejolivet/symfony-nuxt-ia-rag-chatbot/actions/workflows/deploy-backend.yml/badge.svg)](https://github.com/maximejolivet/symfony-nuxt-ia-rag-chatbot/actions/workflows/deploy-backend.yml)
![Hosting](https://img.shields.io/badge/hosting-o2switch-FF6600)
![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)
![Frontend deploy](https://img.shields.io/badge/frontend-vercel-black?logo=vercel)

## Backend (Symfony)

Deux pipelines distincts, le second déclenché par le premier :

1. [`.github/workflows/ci-backend.yml`](../.github/workflows/ci-backend.yml) —
   lint, tests, PHPStan, PHP-CS-Fixer, `composer audit`. Déclenché sur tout
   push/PR touchant `backend/**`. Pas de secrets, pas de SSH.
2. [`.github/workflows/deploy-backend.yml`](../.github/workflows/deploy-backend.yml) —
   build + déploiement SSH. Déclenché par `workflow_run` quand le pipeline CI
   **réussit sur `master`** (jamais directement sur push), ou manuellement
   (`workflow_dispatch`, avec une option `dry_run`).

### Pourquoi cette architecture

- **Hébergement** : o2switch, hébergement mutualisé cPanel (domaine
  `chatbot.jolivetmaxime.fr`). Pas de registre Docker ni de conteneurs côté
  serveur — le déploiement est un `rsync` de fichiers PHP bruts sur SSH.
- **CI et déploiement séparés, mais le déploiement reste un seul job** :
  la porte de qualité (lint/tests/audit/PHPStan/CS-Fixer) vit dans son
  propre workflow (`ci-backend.yml`), avec un retour rapide sur chaque push/PR
  sans toucher aux secrets de déploiement. `deploy-backend.yml` ne se
  déclenche qu'après un succès de ce pipeline sur `master`
  (`on: workflow_run`). En revanche, à l'intérieur de `deploy-backend.yml`,
  build et déploiement restent **un seul job** (pas de split) : chaque job
  GitHub Actions a sa propre VM avec sa propre IP publique, et l'étape de
  whitelisting IP doit obligatoirement tourner dans le même job que le
  SSH/rsync — whitelister dans un job et faire le SSH dans un autre
  whitelisterait la mauvaise IP. C'est cette contrainte-là (pas l'absence de
  CI séparée) qui borne le job unique.
- **Whitelisting IP** : o2switch restreint l'accès SSH par IP (cPanel >
  Sécurité > Accès SSH). Les runners GitHub Actions changent d'IP à chaque
  run, donc le workflow whiteliste l'IP du runner via l'API cPanel avant de
  tenter le SSH (logique dans
  [`.github/scripts/o2switch-whitelist.sh`](../.github/scripts/o2switch-whitelist.sh),
  auth par **token API cPanel** — méthode recommandée par o2switch depuis
  le 2025-05-02, voir
  [leur doc](https://faq.o2switch.fr/cpanel/outils/exception-parefeu/) —
  pas le mot de passe cPanel). Le quota de whitelist est de **5 exceptions
  au total** ; le script retire la plus ancienne IP (in + out) uniquement
  s'il est déjà à quota, avant d'ajouter la nouvelle. Idempotent : si l'IP
  du runner est déjà whitelistée, le script ne fait rien.

### Disposition sur le serveur

> [!WARNING]
> `DEPLOY_PROJECT_PATH` doit être **en dehors** de la racine web (ex.
> `/home/{{user}}/repositories/chatbot-skills-ia/backend`) — seul
> `backend/public/` doit être exposé en HTTP. Pointer le document root du
> sous-domaine `chatbot.jolivetmaxime.fr` (cPanel > Domaines) vers
> `$DEPLOY_PROJECT_PATH/public`, jamais vers `$DEPLOY_PROJECT_PATH` lui-même
> (sinon `src/`, `vendor/`, `.env`, `config/` seraient servis directement).

### Prérequis manuels (non automatisés)

- Sélectionner PHP 8.4 pour le sous-domaine (cPanel > MultiPHP Manager).
- Créer la base MySQL (cPanel > Bases de données MySQL) et la collection
  Qdrant Cloud, et avoir leurs informations de connexion prêtes pour
  `DEPLOY_ENV_FILE` (voir ci-dessous). o2switch fournit en réalité
  **MariaDB 11.4.12** (confirmé via `SELECT version();`), pas MySQL --
  `serverVersion=11.4.12` dans `DATABASE_URL`, pas une version
  MySQL nue. C'est le deuxième moteur de base de données découvert a
  posteriori sur cette instance o2switch (après PostgreSQL 9.6, EOL depuis
  2021).
- Provisionner un **Redis externe managé** (Upstash, même logique que Qdrant
  Cloud ci-dessus) et avoir son URL de connexion prête pour `REDIS_URL` dans
  `DEPLOY_ENV_FILE` — o2switch n'a pas de Redis local. Requis par
  [`config/packages/cache.yaml`](../backend/config/packages/cache.yaml), qui
  déclare trois pools Redis (`cache.conversation_history`,
  `cache.query_embedding`, `cache.admin_analytics`) résolus eagerly au
  compile du conteneur : sans `REDIS_URL`, **toute** requête touchant le
  chat/les embeddings/les analytics admin plante, pas seulement celles qui
  utilisent réellement le cache. Upstash exige TLS : l'URL doit être en
  `rediss://` (double s), pas `redis://`.

> [!TIP]
> Toujours vérifier la version réelle du moteur de base de données fourni par l'hébergeur plutôt que de supposer qu'un défaut générique convient.

### Secrets requis (Settings > Secrets and variables > Actions)

| Secret                   | Description                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| ------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `DEPLOY_SSH_KEY`         | Clé privée SSH dédiée au déploiement (pas une clé personnelle)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| `DEPLOY_SSH_HOST`        | `{{user}}.o2switch.net`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| `DEPLOY_SSH_USER`        | `{{user}}` (identifiant cPanel)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| `DEPLOY_PROJECT_PATH`    | `/home/{{user}}/repositories/chatbot-skills-ia/backend`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| `O2SWITCH_API_LOGIN`     | Identifiant cPanel (même valeur que `DEPLOY_SSH_USER`, mais un secret séparé — utilisé uniquement par l'API de whitelist SSH, sans rapport avec la clé SSH)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| `O2SWITCH_API_TOKEN`     | Token API cPanel (cPanel > Sécurité > Gestionnaire de jetons API > Créer) — pas le mot de passe cPanel                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| `DEPLOY_ENV_FILE`        | Contenu complet du `.env` de production : **`APP_ENV=prod`** (sans ça, un `.env` vide ou incomplet fait retomber Symfony en `dev`, qui référence des bundles dev-only absents du build `--no-dev` déployé -- le workflow force déjà `--env=prod` sur les migrations par défense en profondeur, mais mieux vaut ne pas en dépendre), `DATABASE_URL` (instance MariaDB o2switch, `serverVersion=11.4.12-MariaDB`), `ADMIN_USERNAME`/`ADMIN_PASSWORD_HASH` (`bin/console security:hash-password`), `QDRANT_HOST`/`QDRANT_PORT`/`QDRANT_API_KEY` (Qdrant Cloud), `AI_PROVIDER=api_endpoint` + `AI_API_*` (ou configurer une ligne `AiProviderConfig` après le premier déploiement), `CORS_ALLOW_ORIGIN` pour l'origine réelle du frontend (`https://ia.maxime.bzh`, le frontend Nuxt public sur Vercel -- pas `chatbot.jolivetmaxime.fr`, qui n'est que le domaine de l'API elle-même), `MESSENGER_TRANSPORT_DSN` (`sync://` tant qu'aucun worker persistant n'existe côté o2switch -- voir `backend/README.md` §File d'attente async), `REDIS_URL` (Redis externe managé Upstash, `rediss://` -- voir plus haut ; requis par `config/packages/cache.yaml`, sans lien avec `MESSENGER_TRANSPORT_DSN` ci-dessus), `MAILER_DSN` (`null://null` tant qu'aucun fournisseur transactionnel n'est branché -- le mail est silencieusement jeté plutôt qu'une erreur ; lu par `config/packages/mailer.yaml`), `MAILER_FROM_ADDRESS` (adresse "From", injectée via `#[Autowire(env:)]` à la fois dans `App\Chat\ChatService` et `App\Workflow\WorkflowExecutionService`), `CAL_EU_API_KEY` (jeton `Authorization: Bearer ...` lu au moment de l'exécution par `WorkflowExecutionService::resolveEnvHeaders()` pour l'étape "Réserver sur Cal.eu" du workflow `planifier_entretien`, cible `https://api.cal.eu/v2/bookings`), `OWNER_NOTIFICATION_EMAIL` (email de notification "nouvelle conversation", injecté via `#[Autowire(env:)]` dans `ChatService` -- vide = fonctionnalité désactivée, mais la variable doit exister). Ces cinq variables ont toutes manqué entièrement de `.env.prod` en même temps (pas juste une valeur vide) : `MAILER_FROM_ADDRESS`/`OWNER_NOTIFICATION_EMAIL` sont résolues dès que Symfony instancie `ChatService` (donc dès la première requête `chat`/`conversations`, `ChatService` étant sur le chemin de tous les endpoints de conversation), et `REDIS_URL` dès que n'importe quel des trois pools cache est sollicité (§ci-dessus) -- **aucune de ces variables n'est réellement optionnelle en prod**, même celles qui semblent inertes (`MAILER_DSN=null://null`). |

Définir ces secrets via `gh secret set <NAME>` ou l'UI GitHub (Settings >
Secrets and variables > Actions).

> [!WARNING]
> Ne jamais coller le contenu de ces secrets dans une conversation (chat, ticket, PR) — les préparer localement (fichiers non commités, ou saisie interactive) puis :

```bash
# Clé privée SSH dédiée (jamais votre clé perso) -- lue depuis un fichier.
# Générer la paire avant : ssh-keygen -t ed25519 -f ~/.ssh/deploy_chatbot_ia
# -N "" -C "deploy-chatbot-skills-ia" ; ajouter la clé PUBLIQUE (.pub) dans cPanel >
# Sécurité > Accès SSH > Gestionnaire de clés SSH > Importer une clé, puis
# l'autoriser ("Manage" > Authorize).
gh secret set DEPLOY_SSH_KEY < ~/.ssh/deploy_chatbot_ia

# DEPLOY_SSH_HOST -- cPanel > Informations générales ("Nom d'hôte du
# serveur"), ou l'email de bienvenue o2switch. Format {{user}}.o2switch.net
gh secret set DEPLOY_SSH_HOST -b"{{user}}.o2switch.net"

# DEPLOY_SSH_USER -- identifiant cPanel, affiché en haut à droite de
# l'interface cPanel.
gh secret set DEPLOY_SSH_USER -b"{{user}}"

# DEPLOY_PROJECT_PATH -- chemin absolu HORS de la racine web (cPanel >
# Gestionnaire de fichiers pour repérer /home/{{user}}/).
gh secret set DEPLOY_PROJECT_PATH -b"/home/{{user}}/repositories/chatbot-skills-ia/backend"

# O2SWITCH_API_LOGIN -- identifiant cPanel (même que DEPLOY_SSH_USER).
gh secret set O2SWITCH_API_LOGIN -b"{{user}}"

# O2SWITCH_API_TOKEN -- cPanel > Sécurité > Gestionnaire de jetons API >
# Créer un jeton. Sans -b ni redirection : gh le lit en saisie interactive
# masquée, rien ne traîne dans l'historique du shell.
gh secret set O2SWITCH_API_TOKEN

# DEPLOY_ENV_FILE -- .env de production complet, préparé dans un fichier
# local non commité (voir le détail de son contenu dans le tableau ci-dessus).
gh secret set DEPLOY_ENV_FILE < chemin/vers/.env.prod
```

Vérifier ensuite que les 7 secrets sont bien enregistrés (sans exposer leur
contenu) :

```bash
gh secret list
```

### Lancer un dry-run

```bash
gh workflow run deploy-backend.yml -f dry_run=true
```

Exécute tout le pipeline (build, whitelist, SSH) mais passe `--dry-run` à
rsync et saute l'écriture du `.env` / les migrations — un aperçu sans risque
de ce qu'un déploiement changerait.

## Frontend (Nuxt)

Déployé sur **Vercel**, projet `chatbot-skills-ia`, aliasé sur
`https://ia.maxime.bzh` (le domaine public réel du widget/chat, distinct de
`chatbot.jolivetmaxime.fr` qui reste uniquement l'API o2switch — voir
`CORS_ALLOW_ORIGIN` plus haut). Les variables d'environnement (`API_URL`,
`ADMIN_USERNAME`, `ADMIN_PASSWORD`) sont définies côté **Vercel**
(dashboard ou `vercel env`), pour les environnements Production et Preview —
elles ne vivent pas dans ce dépôt. Il n'existe **pas** de workflow GitHub
Actions pour ce déploiement (contrairement au backend ci-dessus) ; le
déclenchement effectif (auto-deploy Vercel sur push, ou `vercel deploy
--prod` manuel) dépend de la configuration du projet côté Vercel, non
vérifiable depuis ce dépôt.

En local, le frontend tourne uniquement via `docker compose` (service
`nuxt`, voir [`README.md`](../README.md)) ou `npm run dev` (voir
[`frontend/README.md`](../frontend/README.md)).
