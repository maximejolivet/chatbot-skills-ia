#!/bin/bash

GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m'

echo ""
echo -e "${GREEN}✅ Services démarrés avec succès !${NC}"
echo ""
echo -e "${BLUE} URLs des différents services (via Traefik) :${NC}"
echo "   - Traefik (dashboard):         http://traefik.chatbot.localhost (ou http://localhost:8090)"
echo "   - Symfony (backend):           http://symfony.chatbot.localhost/admin"
echo "   - Nuxt/Vue (frontend):         http://nuxt.chatbot.localhost"
echo "   - Qdrant (dashboard):          http://qdrant.chatbot.localhost/dashboard"
echo "   - phpMyAdmin (dashboard):      http://phpmyadmin.chatbot.localhost"
echo ""
echo "   Hors Traefik :"
echo "   - Ollama (local):              http://localhost:11434"
echo ""
