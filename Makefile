MAKEFLAGS += --no-print-directory
.PHONY: help up down purge rebuild logs services-url format audit

help:
	@echo "\033[4mCommandes disponibles\033[0m:"
	@echo "   up                         : Démarrer Traefik + la stack Symfony"
	@echo "   down                       : Arrêter Traefik + la stack Symfony"
	@echo "   purge                      : Purge complète (arrête, supprime conteneurs, volumes et réseaux)"
	@echo "   rebuild SERVICE=<name>     : Rebuild et redémarre un service spécifique (ex: make rebuild SERVICE=app)"
	@echo "   logs                       : Watch des logs de la stack Symfony"
	@echo "   services-url               : Affiche la liste des urls des différents services actifs"
	@echo "   format                     : Formate le frontend Nuxt (Prettier)"
	@echo "   audit                      : Audit des dépendances (composer audit + npm outdated/audit)"

start:
	@docker network inspect chatbot-proxy >/dev/null 2>&1 || docker network create chatbot-proxy
	docker compose -f traefik/docker-compose.yml up -d
	cd backend && docker compose -p backend_symfony up -d
	$(MAKE) services-url

stop:
	cd backend && docker compose -p backend_symfony down
	docker compose -f traefik/docker-compose.yml down

purge:
	@echo "🗑️  Purge complète des conteneurs déployés..."
	@echo "   - Arrêt des conteneurs"
	@cd backend && docker compose -p backend_symfony down -v --remove-orphans 2>/dev/null || true
	@docker compose -f traefik/docker-compose.yml down -v --remove-orphans 2>/dev/null || true
	@echo "   - Suppression des conteneurs arrêtés"
	@docker ps -a --filter "name=chatbot" --format "{{.ID}}" | xargs -r docker rm -f 2>/dev/null || true
	@echo "   - Suppression des volumes orphelins"
	@docker volume ls --filter "name=backend_symfony" --format "{{.Name}}" | xargs -r docker volume rm 2>/dev/null || true
	@echo "   - Nettoyage des réseaux"
	@docker network prune -f 2>/dev/null || true
	@echo "✅ Purge terminée !"

rebuild:
	@if [ -z "$(SERVICE)" ]; then \
		echo "❌ Erreur: Vous devez spécifier le nom du service avec SERVICE=<name>"; \
		echo "   Exemple: make rebuild SERVICE=app"; \
		echo ""; \
		echo "   Services disponibles:"; \
		echo "   - app (Symfony backend)"; \
		echo "   - nuxt"; \
		echo "   - database (PostgreSQL)"; \
		echo "   - qdrant"; \
		exit 1; \
	fi
	@echo "🔨 Rebuild du service $(SERVICE)..."
	@cd backend && docker compose -p backend_symfony stop $(SERVICE) 2>/dev/null || true
	@cd backend && docker compose -p backend_symfony rm -f $(SERVICE) 2>/dev/null || true
	@cd backend && docker compose -p backend_symfony build --no-cache $(SERVICE) || (echo "❌ Erreur lors du rebuild de l'image" && exit 1)
	@cd backend && docker compose -p backend_symfony up -d $(SERVICE) || (echo "❌ Erreur lors du démarrage du service" && exit 1)
	@echo "✅ Service $(SERVICE) rebuild et redémarré avec succès !"
	@echo ""
	@cd backend && docker compose -p backend_symfony ps $(SERVICE)

logs:
	cd backend && docker compose -p backend_symfony logs -f

format:
	@echo "🎨 Formatage du frontend Nuxt (Prettier)..."
	docker exec chatbot-symfony-nuxt npm run format

services-url:
	@bash scripts/show_urls.sh

audit:
	@echo "🔎 Backend Symfony (composer audit)..."
	@docker exec chatbot-symfony composer audit
	@echo ""
	@echo "🔎 Frontend Nuxt (npm outdated + npm audit)..."
	@cd frontend && npm outdated; npm audit
