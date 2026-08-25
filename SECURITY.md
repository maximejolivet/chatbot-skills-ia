# Sécurité

![composer audit](https://img.shields.io/badge/composer%20audit-0%20advisories-brightgreen)
![npm audit](https://img.shields.io/badge/npm%20audit-0%20vulnerabilities-brightgreen)
[![Security Policy](https://img.shields.io/badge/security%20policy-see%20below-informational)](#signalement-dune-vulnérabilité)

## Signalement d'une vulnérabilité

> [!NOTE]
> Ce dépôt est un projet personnel (portfolio/démo), sans processus de disclosure formel. Pour signaler un problème de sécurité, contacter directement le mainteneur plutôt que d'ouvrir une issue publique.

## État des audits de dépendances

### Backend Symfony (`composer audit`)

```
No security vulnerability advisories found.
```

Toutes les dépendances directes sont à jour (`doctrine/orm` 3.6.8, `symfony/messenger`/`symfony/asset-mapper`/`symfony/stimulus-bundle` ajoutés sans avis de sécurité ; `npm outdated` ne signale rien côté frontend).

### Frontend Nuxt (`npm audit`)

```
found 0 vulnerabilities
```

### Images Docker

| Image           | Tag utilisé                      | Remarque                             |
| --------------- | -------------------------------- | ------------------------------------ |
| `mariadb`       | `${MARIADB_VERSION:-11.4}`       | Version majeure épinglée (11.4)      |
| `qdrant/qdrant` | `v1.19.0`                        | Épinglé (était `latest`)             |
| `redis`         | `7-alpine`                       | Version majeure épinglée (7)         |
| `traefik`       | `v3.5`                           | Épinglé                              |
| `node`          | `24-alpine`                      | Version majeure épinglée (24)        |

## Authentification

Le backend (`backend/`) est protégé par Symfony Security, multi-utilisateur : chaque opérateur a son propre compte (table `app_user`, `bin/console app:user:create`), formulaire de login (session) sur `/admin`, HTTP Basic (stateless) sur `/api` et `/doc`. Voir [`backend/README.md`](backend/README.md#sécurité) pour le détail.

`Conversation`/`WorkflowExecution` sont cloisonnées par propriétaire : un compte `ROLE_USER` ne voit/modifie que ses propres lignes (`OwnershipVoter` + `OwnershipCollectionExtension`), `ROLE_ADMIN` voit tout. Toutes les autres ressources (`Document`, `Workflow`, `AiAgent`, etc.) restent réservées à `ROLE_ADMIN`.

`AiProviderConfig.apiKey` (clés des providers IA, ex. OpenRouter) n'est jamais renvoyé par l'API (`#[ApiProperty(readable: false)]`) — accepté en écriture uniquement, via le backoffice désormais authentifié.

## Secrets locaux (collection Bruno)

La collection [Bruno](https://www.usebruno.com/) (`docs/backend/bruno/`) sépare les requêtes
versionnées des identifiants réels : les mots de passe admin ne vivent que dans
`docs/backend/bruno/environments/*.bru` (`production.bru`, `local.bru`), exclus du dépôt via
`.gitignore` (`**/bruno/environments/`).

Le déplacement de la collection de `bruno/` (racine) vers `docs/backend/bruno/` a
révélé que la règle `.gitignore` était alors écrite `bruno/environments/` — une forme ancrée à la
racine du dépôt, qui a cessé de matcher le nouveau chemin plus profond. Corrigée en `**/bruno/environments/`
(matching à toute profondeur) avant tout commit ; vérifié qu'aucun `environments/*.bru` n'a été
versionné à aucun moment (`git log --all --full-history -- '**/bruno/environments/**'` ne remonte
rien).

> [!CAUTION]
> Si la collection Bruno est de nouveau déplacée, vérifier que `git check-ignore -v <nouveau_chemin>/environments/<fichier>.bru` matche bien **avant** tout commit — une règle `.gitignore` ancrée à un chemin fixe cesse silencieusement de protéger les identifiants dès que ce chemin change.