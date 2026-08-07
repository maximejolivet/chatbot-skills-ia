# Sécurité

## Signalement d'une vulnérabilité

Ce dépôt est un projet personnel (portfolio/démo), sans processus de disclosure formel. Pour signaler un problème de sécurité, contacter directement le mainteneur plutôt que d'ouvrir une issue publique.

## État des audits de dépendances

Dernier audit : **2026-08-07**

### Backend Symfony (`composer audit`)

```
No security vulnerability advisories found.
```

Toutes les dépendances directes sont à jour (`doctrine/orm` 3.6.8, `symfony/messenger`/`symfony/asset-mapper`/`symfony/stimulus-bundle` ajoutés le 2026-08-07 sans avis de sécurité ; `npm outdated` ne signale rien côté frontend).

### Frontend Nuxt (`npm audit`)

```
found 0 vulnerabilities
```

### Images Docker

| Image           | Tag utilisé                      | Remarque                             |
| --------------- | -------------------------------- | ------------------------------------ |
| `mariadb`       | `${MARIADB_VERSION:-11.4}`       | Version majeure épinglée (11.4)      |
| `qdrant/qdrant` | `v1.19.0`                        | Épinglé (2026-08-06, était `latest`) |
| `redis`         | `7-alpine`                       | Version majeure épinglée (7)         |
| `traefik`       | `v3.5`                           | Épinglé                              |
| `node`          | `24-alpine`                      | Version majeure épinglée (24)        |

## Authentification

Le backend (`backend/`) est protégé par Symfony Security depuis le 2026-08-06, multi-utilisateur depuis le 2026-08-07 : chaque opérateur a son propre compte (table `app_user`, `bin/console app:user:create`), formulaire de login (session) sur `/admin`, HTTP Basic (stateless) sur `/api` et `/doc`. Voir [`backend/README.md`](backend/README.md#sécurité) pour le détail.

`Conversation`/`WorkflowExecution` sont cloisonnées par propriétaire : un compte `ROLE_USER` ne voit/modifie que ses propres lignes (`OwnershipVoter` + `OwnershipCollectionExtension`), `ROLE_ADMIN` voit tout. Toutes les autres ressources (`Document`, `Workflow`, `AiAgent`, etc.) restent réservées à `ROLE_ADMIN`.

`AiProviderConfig.apiKey` (clés des providers IA, ex. OpenRouter) n'est jamais renvoyé par l'API (`#[ApiProperty(readable: false)]`) — accepté en écriture uniquement, via le backoffice désormais authentifié.