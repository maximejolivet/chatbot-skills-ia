MAKEFLAGS += --no-print-directory
.PHONY: help start stop purge rebuild logs services-url format audit actionlint check-ollama

help:
	@echo "\033[4mCommandes disponibles\033[0m:"
	@echo "   start                      : Démarrer Traefik + la stack Symfony"
	@echo "   stop                       : Arrêter Traefik + la stack Symfony"
	@echo "   purge                      : Purge complète (arrête, supprime conteneurs, volumes et réseaux)"
	@echo "   rebuild SERVICE=<name>     : Rebuild et redémarre un service spécifique (ex: make rebuild SERVICE=app)"
	@echo "   logs                       : Watch des logs de la stack Symfony"
	@echo "   services-url               : Affiche la liste des urls des différents services actifs"
	@echo "   format                     : Formate le frontend Nuxt (Prettier)"
	@echo "   audit                      : Audit des dépendances (composer audit + npm outdated/audit)"
	@echo "   actionlint                 : Lint des workflows GitHub Actions (.github/workflows/)"
	@echo "   check-ollama               : Vérifie qu'Ollama tourne et expose les modèles requis"

start: check-ollama
	@docker network inspect chatbot-proxy >/dev/null 2>&1 || docker network create chatbot-proxy
	docker compose up -d
	$(MAKE) services-url

stop:
	docker compose down

purge:
	@echo "🗑️  Purge complète des conteneurs déployés..."
	@echo "   - Arrêt des conteneurs, suppression des volumes et des orphelins"
	@docker compose down -v --remove-orphans
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
	@docker compose stop $(SERVICE) 2>/dev/null || true
	@docker compose rm -f $(SERVICE) 2>/dev/null || true
	@docker compose build --no-cache $(SERVICE) || (echo "❌ Erreur lors du rebuild de l'image" && exit 1)
	@docker compose up -d $(SERVICE) || (echo "❌ Erreur lors du démarrage du service" && exit 1)
	@echo "✅ Service $(SERVICE) rebuild et redémarré avec succès !"
	@echo ""
	@docker compose ps $(SERVICE)

logs:
	docker compose logs -f

format:
	@echo "🎨 Formatage du frontend Nuxt (Prettier)..."
	docker exec chatbot-symfony-nuxt npm run format

services-url:
	@bash .github/scripts/show-urls.sh

audit:
	@echo "🔎 Backend Symfony (composer audit)..."
	@docker exec chatbot-symfony composer audit
	@echo ""
	@echo "🔎 Frontend Nuxt (npm outdated + npm audit)..."
	@cd frontend && npm outdated; npm audit

actionlint:
	@command -v actionlint >/dev/null 2>&1 || { \
		echo "❌ actionlint n'est pas installé -- voir https://github.com/rhysd/actionlint (brew install actionlint)"; \
		exit 1; \
	}
	@echo "🔎 Lint des workflows GitHub Actions..."
	@actionlint
	@echo "✅ Aucun problème détecté"

check-ollama:
	@bash .github/scripts/check-ollama.sh
