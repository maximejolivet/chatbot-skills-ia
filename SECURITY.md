# Sécurité

## Signalement d'une vulnérabilité

Ce dépôt est un projet personnel (portfolio/démo), sans processus de disclosure formel. Pour signaler un problème de sécurité, contacter directement le mainteneur plutôt que d'ouvrir une issue publique.

## État des audits de dépendances

Dernier audit : **2026-08-06**

### Backend Symfony (`composer audit`)

```
No security vulnerability advisories found.
```

Toutes les dépendances directes sont à jour (`doctrine/orm` mis à jour en 3.6.8 le 2026-08-06 ; `npm outdated` ne signale rien côté frontend).

### Frontend Nuxt (`npm audit`)

```
found 0 vulnerabilities
```

### Images Docker

| Image           | Tag utilisé                      | Remarque                             |
| --------------- | -------------------------------- | ------------------------------------ |
| `mysql`         | `${MYSQL_VERSION:-8.0}`          | Version majeure épinglée (8.0)       |
| `qdrant/qdrant` | `v1.19.0`                        | Épinglé (2026-08-06, était `latest`) |
| `traefik`       | `v3.5`                           | Épinglé                              |
| `node`          | `24-alpine`                      | Version majeure épinglée (24)        |

## Authentification

Le backend (`backend/`) est protégé par Symfony Security depuis le 2026-08-06 : un compte admin unique (`ADMIN_USERNAME`/`ADMIN_PASSWORD_HASH` dans `backend/.env`, jamais commité), formulaire de login (session) sur `/admin`, HTTP Basic (stateless) sur `/api` et `/doc`. Voir [`backend/README.md`](backend/README.md#sécurité) pour le détail.

**Limite assumée** : un seul compte partagé, pas de scoping des ressources par utilisateur (`Conversation`, `WorkflowExecution`, etc. restent visibles/modifiables par quiconque a les identifiants admin). Pas un problème pour un usage personnel/démo, à revoir avant tout usage multi-utilisateur.

`AiProviderConfig.apiKey` (clés des providers IA, ex. OpenRouter) n'est jamais renvoyé par l'API (`#[ApiProperty(readable: false)]`) — accepté en écriture uniquement, via le backoffice désormais authentifié.