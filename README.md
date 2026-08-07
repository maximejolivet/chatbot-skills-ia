# Chatbot Full-Stack

[![Deploy chat-ia (backend)](https://github.com/maximejolivet/chatbot-skills-ia/actions/workflows/deploy-backend-symfony.yml/badge.svg)](https://github.com/maximejolivet/chatbot-skills-ia/actions/workflows/deploy-backend-symfony.yml)
![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)
![Symfony](https://img.shields.io/badge/Symfony-8.1-000000?logo=symfony&logoColor=white)
![API Platform](https://img.shields.io/badge/API%20Platform-4.3-1F76C8)
![Node.js](https://img.shields.io/badge/Node.js-24-339933?logo=nodedotjs&logoColor=white)
![Nuxt](https://img.shields.io/badge/Nuxt-4.5-00DC82?logo=nuxtdotjs&logoColor=white)
![Vue](https://img.shields.io/badge/Vue-3.5-4FC08D?logo=vuedotjs&logoColor=white)
![TypeScript](https://img.shields.io/badge/TypeScript-7.0-3178C6?logo=typescript&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-4169E1?logo=postgresql&logoColor=white)
![Qdrant](https://img.shields.io/badge/Qdrant-v1.19.0-DC244C)
![Ollama](https://img.shields.io/badge/Ollama-qwen3.6-000000?logo=ollama&logoColor=white)
![Traefik](https://img.shields.io/badge/Traefik-v3.5-24A1C1?logo=traefikproxy&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white)

Solution de chatbot administrable : backend Symfony (API Platform) avec RAG (Retrieval-Augmented Generation) sur Qdrant et **tool-calling LLM réel** (le modèle peut déclencher des workflows métier pendant la conversation), exposé à un frontend Nuxt/Vue qui consomme l'API. Les services sont routés par domaine via Traefik.

## Stack

| Composant          | Techno                 | Version                                       |
| ------------------ | ---------------------- | --------------------------------------------- |
| Backend            | Symfony + API Platform | Symfony 8.1, API Platform 4.3, PHP 8.4        |
| Frontend           | Nuxt / Vue             | Nuxt 4.5, Vue 3.5, TypeScript 7.0, Node.js 24 |
| Base relationnelle | PostgreSQL             | 16                                            |
| Base vectorielle   | Qdrant                 | v1.19.0                                       |
| Modèles IA         | Ollama (local)         | `qwen3.6`, `mxbai-embed-large`                |
| Reverse proxy      | Traefik                | v3.5                                          |

## Démarrage rapide

Premier lancement uniquement — créer le fichier d'environnement du backend et générer le mot de passe admin (voir [`backend/README.md#sécurité`](backend/README.md#sécurité)) :

```bash
cd backend && cp .env.example .env
```

Puis, depuis la racine :

```bash
make start
```

Cette commande démarre Traefik puis la stack Symfony (backend, PostgreSQL, Qdrant, frontend Nuxt) via Docker Compose, et affiche les URLs des services.

Ollama doit tourner sur l'hôte au préalable, avec les modèles `mxbai-embed-large` et `qwen3.6` (ou équivalent) installés.

Pour lister toutes les commandes Make disponibles :

```bash
make
```

### Prérequis

- Docker + Docker Compose (ou un runtime compatible comme Colima)
- [Ollama](https://ollama.ai/download) installé et accessible en local (les modèles tournent sur l'hôte, pas dans un conteneur)

## Services et URLs

Tous les services sont routés par domaine via Traefik (`*.chatbot.localhost`, résolu automatiquement en local sans toucher `/etc/hosts`) :

| Service             | URL                                                         |
| ------------------- | ----------------------------------------------------------- |
| Traefik (dashboard) | http://traefik.chatbot.localhost (ou http://localhost:8090) |
| Symfony (admin)     | http://symfony.chatbot.localhost/admin                      |
| Nuxt/Vue            | http://nuxt.chatbot.localhost                               |
| Qdrant (dashboard)  | http://qdrant-symfony.chatbot.localhost/dashboard           |
| Ollama (sur l'hôte) | http://localhost:11434                                      |

## Architecture

```
chatbot-ia/
├── backend/           # API Symfony + API Platform, backoffice /admin
├── frontend/                   # Composant chatbot Nuxt 4 + TailwindCSS
├── traefik/                   # Reverse proxy : domaines par service (*.chatbot.localhost)
├── docs/                      # Cahiers des charges
└── scripts/                   # Scripts d'installation et d'affichage des URLs
```

Détail de l'architecture backend (entités, services, domaines métier) dans [`backend/README.md`](backend/README.md).

## Tool-calling

Un `Workflow` associé à un `AiAgent` devient un outil que le LLM peut appeler pendant la conversation : le modèle décide d'invoquer l'outil, le workflow s'exécute (synchrone), son résultat est réinjecté dans la conversation, et le modèle formule sa réponse finale en tenant compte du résultat.

## Configuration

Les variables d'environnement du backend sont définies dans `backend/.env` (généré depuis `.env.example`), ignoré par Git.

Le choix du provider IA (Ollama local ou endpoint API externe compatible OpenAI) se configure via l'admin Symfony (`/admin/ai-provider-configs`) plutôt que par variables d'environnement.

## API Symfony

Toutes les routes sont préfixées par `/api/` (API Platform, JSON-LD/Hydra) et documentées de façon interactive sur `/api` et `/doc` (OpenAPI pur). Détail complet dans [`backend/README.md`](backend/README.md).

## Sécurité

Voir [`SECURITY.md`](SECURITY.md) — politique de signalement et état des audits de dépendances (`composer audit`, `npm audit`).

## Documentation

- [`backend/README.md`](backend/README.md) — installation et référence API du backend
- [`docs/backend/SPECIFICATION.md`](docs/backend/SPECIFICATION.md)
- [`docs/frontend/SPECIFICATION.md`](docs/frontend/SPECIFICATION.md)
- [`DEPLOYMENT.md`](DEPLOYMENT.md) — déploiement du backend (CI/CD, secrets, prérequis serveur)
