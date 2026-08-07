# Déploiement

## Backend (Symfony)

CI/CD : [`.github/workflows/deploy-backend-symfony.yml`](.github/workflows/deploy-backend-symfony.yml),
déclenché sur push vers `master` touchant `backend/**`, ou manuellement
(`workflow_dispatch`, avec une option `dry_run`).

### Pourquoi cette architecture

- **Hébergement** : o2switch, hébergement mutualisé cPanel (domaine
  `chat-ia.jolivetmaxime.fr`). Pas de registre Docker ni de conteneurs côté
  serveur — le déploiement est un `rsync` de fichiers PHP bruts sur SSH.
- **Un seul job** (pas de split build/deploy) : contrairement à un frontend
  statique, il n'y a pas de besoin "build once, ship to multiple targets"
  ici, et l'étape de whitelisting IP doit obligatoirement tourner dans le
  même job que le SSH/rsync — chaque job GitHub Actions a sa propre VM avec
  sa propre IP publique ; whitelister dans un job et faire le SSH dans un
  autre whitelisterait la mauvaise IP.
- **Whitelisting IP** : o2switch restreint l'accès SSH par IP (cPanel >
  Sécurité > Accès SSH). Les runners GitHub Actions changent d'IP à chaque
  run, donc le workflow whiteliste l'IP du runner via l'API cPanel avant de
  tenter le SSH (logique dans
  [`.github/scripts/o2switch-whitelist-backend-symfony.sh`](.github/scripts/o2switch-whitelist-backend-symfony.sh)).
  Le quota de whitelist étant limité, le script retire les 2 IP les plus
  récemment ajoutées avant d'ajouter la nouvelle.
- **Lint + audit comme porte de déploiement** : pas de workflow CI séparé —
  un échec de lint/audit bloque le job avant que whitelist/rsync/SSH ne
  s'exécutent, ce qui donne le même effet de garde-fou qu'un job "build"
  distinct sans fichier de workflow supplémentaire à maintenir.

### Disposition sur le serveur

`DEPLOY_PROJECT_PATH` doit être **en dehors** de la racine web (ex.
`/home/{{user}}/repositories/chatbot-skills-ia/backend`) — seul
`backend/public/` doit être exposé en HTTP. Pointer le document root du
sous-domaine `chat-ia.jolivetmaxime.fr` (cPanel > Domaines) vers
`$DEPLOY_PROJECT_PATH/public`, jamais vers `$DEPLOY_PROJECT_PATH` lui-même
(sinon `src/`, `vendor/`, `.env`, `config/` seraient servis directement).

### Prérequis manuels (non automatisés)

- Sélectionner PHP 8.4 pour le sous-domaine (cPanel > MultiPHP Manager).
- Créer la base MySQL (cPanel > Bases de données MySQL) et la collection
  Qdrant Cloud, et avoir leurs informations de connexion prêtes pour
  `DEPLOY_ENV_FILE` (voir ci-dessous). o2switch fournit en réalité
  **MariaDB 11.4.12** (confirmé via `SELECT version();`), pas MySQL --
  `serverVersion=11.4.12` dans `DATABASE_URL`, pas une version
  MySQL nue. C'est le deuxième moteur de base de données découvert a
  posteriori sur cette instance o2switch (après PostgreSQL 9.6, EOL depuis
  2021) : toujours vérifier la version réelle plutôt que de supposer qu'un
  défaut générique convient.

### Secrets requis (Settings > Secrets and variables > Actions)

| Secret                   | Description                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| ------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `DEPLOY_SSH_KEY`         | Clé privée SSH dédiée au déploiement (pas une clé personnelle)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                |
| `DEPLOY_SSH_HOST`        | `{{user}}.o2switch.net`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| `DEPLOY_SSH_USER`        | `{{user}}` (identifiant cPanel)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| `DEPLOY_PROJECT_PATH`    | `/home/{{user}}/repositories/chatbot-skills-ia/backend`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| `DEPLOY_CPANEL_PASSWORD` | Mot de passe cPanel — utilisé **uniquement** pour l'API de whitelist SSH (port 2083), sans rapport avec la clé SSH ci-dessus                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| `DEPLOY_ENV_FILE`        | Contenu complet du `.env` de production : **`APP_ENV=prod`** (sans ça, un `.env` vide ou incomplet fait retomber Symfony en `dev`, qui référence des bundles dev-only absents du build `--no-dev` déployé -- le workflow force déjà `--env=prod` sur les migrations par défense en profondeur, mais mieux vaut ne pas en dépendre), `DATABASE_URL` (instance MariaDB o2switch, `serverVersion=11.4.12-MariaDB`), `ADMIN_USERNAME`/`ADMIN_PASSWORD_HASH` (`bin/console security:hash-password`), `QDRANT_HOST`/`QDRANT_PORT`/`QDRANT_API_KEY` (Qdrant Cloud), `AI_PROVIDER=api_endpoint` + `AI_API_*` (ou configurer une ligne `AiProviderConfig` après le premier déploiement), `CORS_ALLOW_ORIGIN` pour l'origine réelle du frontend, `MESSENGER_TRANSPORT_DSN` (`sync://` tant qu'aucun Redis externe n'existe côté o2switch -- voir `backend/README.md` §File d'attente async) |

Définir ces secrets via `gh secret set <NAME>` ou l'UI GitHub (Settings >
Secrets and variables > Actions). **Ne jamais coller leur contenu dans une
conversation** — les préparer localement (fichiers non commités, ou saisie
interactive) puis :

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

# DEPLOY_CPANEL_PASSWORD -- mot de passe cPanel, utilisé UNIQUEMENT pour
# l'API de whitelist SSH (port 2083). Sans -b ni redirection : gh le lit en
# saisie interactive masquée, rien ne traîne dans l'historique du shell.
gh secret set DEPLOY_CPANEL_PASSWORD

# DEPLOY_ENV_FILE -- .env de production complet, préparé dans un fichier
# local non commité (voir le détail de son contenu dans le tableau ci-dessus).
gh secret set DEPLOY_ENV_FILE < chemin/vers/.env.prod
```

Vérifier ensuite que les 6 secrets sont bien enregistrés (sans exposer leur
contenu) :

```bash
gh secret list
```

Vérifier ensuite que les 6 secrets sont bien enregistrés (sans exposer leur
contenu) :

```bash
gh secret list
```

### Lancer un dry-run

```bash
gh workflow run deploy-backend-symfony.yml -f dry_run=true
```

Exécute tout le pipeline (build, whitelist, SSH) mais passe `--dry-run` à
rsync et saute l'écriture du `.env` / les migrations — un aperçu sans risque
de ce qu'un déploiement changerait.

## Frontend (Nuxt)

Pas de déploiement automatisé pour l'instant — le frontend tourne uniquement
via `docker compose` en local (service `nuxt`, voir [`README.md`](README.md)).
