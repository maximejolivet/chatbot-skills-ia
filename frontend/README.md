# Chatbot Nuxt / Vue (Symfony)

Widget de chatbot branché sur le backend Symfony (`backend/`). Composant chatbot réutilisable pour Vue 3 / Nuxt 4, avec TailwindCSS.

## Stack

- Node.js 24
- Nuxt 4.5 (Vite 8.2, Vue 3.5, Nitro)
- `@nuxtjs/tailwindcss` 6.14
- TypeScript 7.0
- Prettier 3.9
- axios pour les appels API

## Installation

```bash
cd frontend
npm install
```

## Développement

```bash
API_URL=http://symfony.chatbot.localhost ADMIN_USERNAME=admin ADMIN_PASSWORD=*** npm run dev
```

Démarre sur **http://localhost:3000**.

> [!IMPORTANT]
> `API_URL` doit pointer vers `backend` réellement joignable depuis la machine qui exécute `npm run dev` — ni la valeur par défaut (`http://chatbot-symfony:8000`, un nom de conteneur qui n'existe que dans le réseau Docker de `backend/compose.yaml`), ni `http://localhost:8000` (le port fixe du service `app` a été retiré : l'API n'est plus joignable que via Traefik ou le réseau Docker interne). En local hors Docker, il faut donc passer par le domaine Traefik `http://symfony.chatbot.localhost`. `ADMIN_USERNAME`/`ADMIN_PASSWORD` (valeurs de `backend/.env`) sont requis depuis que le backend exige une authentification HTTP Basic sur `/api/*` — sans eux, tout appel échoue en `401`.

## Build

```bash
npm run build      # build client + serveur (SSR) + Nitro
npm run generate   # génération statique
npm run preview
npm run format        # Prettier — reformate
npm run format:check  # Prettier — vérifie sans modifier
```

En Docker (`backend/compose.yaml`), le conteneur build puis sert directement le résultat via `node .output/server/index.mjs`, pas via `npm run dev`.

## Utilisation

```vue
<template>
  <Chatbot
    title="Mon Assistant"
    theme="dark"
    api-url="/api/chat"
    placeholder="Tape ton message..."
  />
</template>

<script setup lang="ts">
import { Chatbot } from '~/components/Chatbot';
</script>
```

## Props

| Prop          | Type                | Défaut                     | Description                                                                                      |
| ------------- | ------------------- | -------------------------- | ------------------------------------------------------------------------------------------------ |
| `title`       | `string`            | `'Assistant IA'`           | Titre affiché dans l'en-tête                                                                     |
| `theme`       | `'light' \| 'dark'` | `'light'`                  | Valeur de repli seulement : le visiteur peut basculer via le bouton lune/soleil (persisté), et l'OS (`prefers-color-scheme`) est détecté avant cette prop |
| `apiUrl`      | `string`            | `'/api'`                   | _(non utilisée actuellement — les endpoints sont codés en dur dans `useChatbot`, voir plus bas)_ |
| `placeholder` | `string`            | `'Tape ton message...'`    | Placeholder de l'input                                                                           |
| `className`   | `string`            | `''`                       | Classes CSS supplémentaires                                                                      |

## Fonctionnalités

- Interface responsive, thèmes clair/sombre
- Composition API, Nuxt 4, TypeScript
- Animations, indicateur de frappe, auto-scroll
- Gestion d'erreurs réseau

## API backend

- `POST /api/conversations/{id}/stream` — envoi d'un message, réponse en streaming SSE (conversation réellement persistée côté backend, id gardé en `localStorage`)
- `GET /api/ai_agents` — liste des agents IA disponibles (collection JSON-LD API Platform, `{ member: [...] }` — le composable déballe `.member` et convertit `active` → `is_active`)

L'API Symfony est appelée via la variable d'environnement `API_URL` (définie à `http://chatbot-symfony:8000` dans `backend/compose.yaml`), résolue côté serveur lors du rendu SSR. Le proxy Nitro (`server/api/[...path].ts`) ne relaie que les routes d'une allowlist explicite (`ALLOWED_ROUTES`) — ajoutée le 2026-08-24 suite à un audit de sécurité, voir `docs/frontend/SPECIFICATION.md` §3.4/§7.2 pour le détail et la liste complète des routes autorisées.

Le backend exige une authentification HTTP Basic sur `/api/*` (voir `backend/README.md#sécurité`) : le proxy ajoute automatiquement l'en-tête `Authorization` à partir des variables serveur `ADMIN_USERNAME`/`ADMIN_PASSWORD`, de façon transparente pour les visiteurs du widget.

## Notes de version

- **TypeScript 7** : `@vue/compiler-sfc` échoue à résoudre les props typées (`defineProps<X>()`) sous TS7 (`No fs option provided to compileScript in non-Node environment`). Contourné en passant explicitement le module `fs` de Node via `vite.vue.script.fs` dans `nuxt.config.ts`.
