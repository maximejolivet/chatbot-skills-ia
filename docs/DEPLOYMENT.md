# Déploiement

[![CI (backend)](https://github.com/maximejolivet/symfony-nuxt-ia-rag-chatbot/actions/workflows/ci-backend.yml/badge.svg)](https://github.com/maximejolivet/symfony-nuxt-ia-rag-chatbot/actions/workflows/ci-backend.yml)
[![Deploy chat-ia (backend)](https://github.com/maximejolivet/symfony-nuxt-ia-rag-chatbot/actions/workflows/deploy-backend.yml/badge.svg)](https://github.com/maximejolivet/symfony-nuxt-ia-rag-chatbot/actions/workflows/deploy-backend.yml)
![Hosting](https://img.shields.io/badge/hosting-o2switch-FF6600)
![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)
![Frontend deploy](https://img.shields.io/badge/frontend%20deploy-not%20automated-lightgrey)

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
  GitHub Actions a sa propre VM avec sa propre IP publique, et cette IP doit
  être whitelistée à la main sur o2switch avant que le SSH/rsync ne
  fonctionne (voir ci-dessous) — la scinder en deux jobs ferait qu'on
  whiteliste l'IP d'un job pendant que le SSH tente de se connecter depuis
  un autre.
- **Whitelisting IP** : o2switch restreint l'accès SSH par IP (cPanel >
  Sécurité > Accès SSH). Les runners GitHub Actions changent d'IP à chaque
  run, donc **il n'y a pas d'automatisation** : le step "Get runner public
  IP" affiche l'IP du runner en tout début de job (annotation `::notice::`
  visible immédiatement dans l'onglet Actions), et il faut l'ajouter à la
  main dans cPanel avant que le déploiement n'atteigne l'étape SSH — le
  step "Wait for SSH port to open" patiente jusqu'à 2 minutes le temps que
  ce soit fait. Un ancien script whitelistait automatiquement l'IP via
  l'API cPanel, mais a été retiré : le quota de whitelist d'o2switch est
  limité (3 IP maximum) et rendait ce mécanisme plus fragile qu'utile au
  quotidien.

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

> [!TIP]
> Toujours vérifier la version réelle du moteur de base de données fourni par l'hébergeur plutôt que de supposer qu'un défaut générique convient.

### Secrets requis (Settings > Secrets and variables > Actions)

| Secret                   | Description                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| ------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `DEPLOY_SSH_KEY`         | Clé privée SSH dédiée au déploiement (pas une clé personnelle)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| `DEPLOY_SSH_HOST`        | `{{user}}.o2switch.net`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| `DEPLOY_SSH_USER`        | `{{user}}` (identifiant cPanel)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| `DEPLOY_PROJECT_PATH`    | `/home/{{user}}/repositories/chatbot-skills-ia/backend`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| `DEPLOY_ENV_FILE`        | Contenu complet du `.env` de production : **`APP_ENV=prod`** (sans ça, un `.env` vide ou incomplet fait retomber Symfony en `dev`, qui référence des bundles dev-only absents du build `--no-dev` déployé -- le workflow force déjà `--env=prod` sur les migrations par défense en profondeur, mais mieux vaut ne pas en dépendre), `DATABASE_URL` (instance MariaDB o2switch, `serverVersion=11.4.12-MariaDB`), `ADMIN_USERNAME`/`ADMIN_PASSWORD_HASH` (`bin/console security:hash-password`), `QDRANT_HOST`/`QDRANT_PORT`/`QDRANT_API_KEY` (Qdrant Cloud), `AI_PROVIDER=api_endpoint` + `AI_API_*` (ou configurer une ligne `AiProviderConfig` après le premier déploiement), `CORS_ALLOW_ORIGIN` pour l'origine réelle du frontend, `MESSENGER_TRANSPORT_DSN` (`sync://` tant qu'aucun Redis externe n'existe côté o2switch -- voir `backend/README.md` §File d'attente async) |

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

# DEPLOY_ENV_FILE -- .env de production complet, préparé dans un fichier
# local non commité (voir le détail de son contenu dans le tableau ci-dessus).
gh secret set DEPLOY_ENV_FILE < chemin/vers/.env.prod
```

Vérifier ensuite que les 5 secrets sont bien enregistrés (sans exposer leur
contenu) :

```bash
gh secret list
```

### Lancer un dry-run

```bash
gh workflow run deploy-backend.yml -f dry_run=true
```

Exécute tout le pipeline (build, SSH) mais passe `--dry-run` à
rsync et saute l'écriture du `.env` / les migrations — un aperçu sans risque
de ce qu'un déploiement changerait. Nécessite quand même que l'IP du runner
soit déjà whitelistée sur o2switch (voir plus haut).

## Frontend (Nuxt)

Pas de déploiement automatisé pour l'instant — le frontend tourne uniquement
via `docker compose` en local (service `nuxt`, voir [`README.md`](../README.md)).
