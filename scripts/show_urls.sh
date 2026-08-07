#!/bin/bash

GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m'

echo ""
echo -e "${GREEN}✅ Services démarrés avec succès !${NC}"
echo ""
echo -e "${BLUE} URLs des différents services (via Traefik) :${NC}"
echo "   - Traefik (dashboard):         http://traefik.chatbot.localhost (ou http://localhost:8090)"
echo "   - Symfony (admin):             http://symfony.chatbot.localhost/admin"
echo "   - Nuxt/Vue:                    http://nuxt.chatbot.localhost"
echo "   - Qdrant (dashboard):          http://qdrant-symfony.chatbot.localhost/dashboard"
echo ""
echo "   Hors Traefik :"
echo "   - Ollama (local):              http://localhost:11434"
echo "   - PostgreSQL:                  voir 'docker compose ps' pour le port"
echo ""
