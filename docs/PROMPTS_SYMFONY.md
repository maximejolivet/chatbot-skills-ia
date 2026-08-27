# Prompts Claude Code — équipe Symfony

Liste des prompts/skills Claude Code pour équipes Symfony, recensés dans l'article
[« Skills Claude Code pour une équipe Symfony »](https://www.itefficience.com/article/skills-claude-code-equipe-symfony)
(source des skills : [claude-skills-php](https://github.com/efficience-it/claude-skills-php)).

## /review — Revue de code standardisée

Passe le diff staged au crible pour repérer violations d'architecture, failles de sécurité et trous de couverture avant même le commit.

> Analyse le diff git staged. Identifie :
> - Les violations d'architecture (logique métier dans un contrôleur, dépendance du Domain vers l'Infrastructure)
> - Les problèmes de sécurité (injection SQL, données non validées)
> - Les tests manquants pour les cas limites
> - Les violations des conventions PSR-12
>
> Classe les remarques par sévérité : bloquant, important, suggestion.

## /test — Générer des tests PHPUnit ciblés

Fait rédiger un squelette de test qui colle aux conventions du projet, sans avoir à les rappeler à chaque fois.

> Génère un test PHPUnit pour le fichier courant. Utilise les conventions du projet : tests dans
> tests/Unit/ ou tests/Functional/. Couvre le cas nominal, un cas limite et un cas d'erreur. Utilise
> des data providers quand c'est pertinent. Ne mocke jamais la base de données dans les tests
> fonctionnels.

## /migration — Sécuriser les migrations Doctrine

Passe la dernière migration au crible avant qu'elle ne parte en prod : perte de données, rollback fiable, downtime.

> Analyse la migration Doctrine la plus récente dans migrations/. Vérifie :
> - Pas de perte de données (DROP COLUMN sans migration de données préalable)
> - Présence d'une méthode down() cohérente
> - Pas de requête longue sur une table volumineuse sans index
> - Compatibilité avec un déploiement sans downtime
>
> Suggère les corrections nécessaires.

## /security — Audit de sécurité rapide

Un balayage rapide des fichiers modifiés pour attraper les vulnérabilités classiques avant qu'elles ne partent en review.

> Analyse les fichiers modifiés (git diff). Cherche :
> - Injections SQL (requêtes DQL/SQL sans paramètres bindés)
> - Failles XSS (données non échappées dans les templates Twig)
> - Exposition de données sensibles (tokens, mots de passe en clair)
> - Permissions manquantes (contrôleurs sans #[IsGranted])
> - Dépendances avec des CVE connues
>
> Réfère-toi aux recommandations OWASP Top 10.

## /refactor — Refactoring guidé par l'architecture

Garde le refactoring dans les rails de l'architecture hexagonale plutôt que de laisser Claude improviser.

> Refactore le fichier ou la classe indiquée en respectant :
> - Séparation stricte Domain / Application / Infrastructure
> - Le Domain ne dépend de rien d'autre
> - Les use cases sont dans Application/
> - Les contrôleurs restent des adaptateurs minces
>
> Explique chaque déplacement de code.

## /messenger — Vérifier la configuration asynchrone

Traque les messages sans handler, les transports mal routés et les handlers qui portent trop de logique.

> Analyse la configuration Messenger du projet (messenger.yaml, handlers, messages). Vérifie :
> - Chaque message a un handler enregistré
> - Les transports sont correctement routés
> - Les retry policies sont définies pour les transports async
> - Les handlers ne contiennent pas de logique métier lourde
>
> Liste les incohérences trouvées.

## /fixtures — Générer des données de test réalistes

Évite d'écrire les fixtures à la main : jeux de données Alice réalistes et cohérents, relations comprises.

> Génère des fixtures Alice pour l'entité indiquée. Utilise des données réalistes (noms français,
> emails valides, dates cohérentes). Crée au moins 5 entrées avec des variations significatives.
> Respecte les contraintes de validation de l'entité. Gère les relations (ManyToOne, OneToMany)
> avec des références.

## /api-doc — Documenter les endpoints

Complète la doc OpenAPI d'un contrôleur au lieu de la laisser traîner à moitié écrite.

> Analyse le contrôleur API indiqué. Génère les attributs OpenAPI (OA\) pour chaque endpoint :
> - Description, summary
> - Parameters (path, query)
> - Request body avec schema
> - Responses (200, 400, 401, 404, 422)
> - Tags et groupes
>
> Respecte les conventions REST décrites dans le projet.

## /phpstan — Corriger les erreurs d'analyse statique

Fait le ménage dans les erreurs PHPStan une par une, sans jamais tricher avec une exclusion.

> Lance PHPStan et corrige les erreurs détectées. Priorité : les erreurs de type, les appels de
> méthodes sur des types nullable, les paramètres manquants. Ne jamais utiliser @phpstan-ignore
> pour masquer une erreur. Ne jamais réduire le niveau de PHPStan. Explique chaque correction.

## /onboard — Accélérer l'intégration des nouveaux

Un guide d'onboarding généré à partir de l'état réel du projet, pas d'un template générique.

> Analyse le projet et génère un guide d'onboarding :
> - Stack technique et versions
> - Architecture du projet (structure des dossiers, couches)
> - Commandes essentielles (install, test, lint, dev server)
> - Conventions de code et patterns utilisés
> - Points d'attention (dette technique connue, zones sensibles)
>
> Sois factuel et concis.
