MAKEFLAGS += --no-print-directory
.PHONY: help install install-backend install-frontend start stop purge rebuild logs services-url format audit actionlint check-ollama db-install

# Doit matcher backend/compose.yaml (mêmes defaults) -- surchargeable via
# l'environnement si jamais ces identifiants changent localement.
MYSQL_USER ?= app
MYSQL_PASSWORD ?= !ChangeMe!
MYSQL_DATABASE ?= app

help:
	@echo "Projet          : $$(grep -m1 '"name"' package.json | sed -E 's/.*: *"([^"]+)".*/\1/')"
	@echo "Version         : $$(grep -m1 '"version"' package.json | sed -E 's/.*: *"([^"]+)".*/\1/')"
	@echo "Node requis     : $$(grep -oE 'node:[0-9]+' backend/compose.yaml | head -1 | cut -d: -f2).x.x"
	@echo "Node actuel     : $$(node -v 2>/dev/null || echo 'non installé')"
	@echo "Branche         : $$(git branch --show-current)"
	@echo "Dernier commit  : $$(git log -1 --pretty=format:'%h %s')"
	@echo ""
	@echo "\033[4mCommandes disponibles\033[0m:"
	@echo "   install                    : Installation complète depuis un clone frais (backend + frontend)"
	@echo "   install-backend            : Installation backend seul (env, stack, composer, base)"
	@echo "   install-frontend           : Installation frontend seul (env check, npm install)"
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
	@echo "   db-install                 : (Ré)installe la base de données depuis zéro (drop + create + migrate)"

# Backend + frontend + les hooks git racine (husky/commitlint, ni l'un
# ni l'autre en propre -- concernent les deux).
install: install-backend install-frontend
	@echo "🪝 Hooks git (husky/commitlint, racine)..."
	@npm install
	@echo "✅ Projet installé, prêt à l'emploi !"

# Crée backend/.env si absent, démarre la stack, installe les dépendances
# PHP dans le conteneur (le bind-mount .:/app écrase le vendor/ construit
# à l'image Docker par un vendor/ vide côté hôte tant que composer
# install n'a pas tourné une fois via le conteneur), puis initialise la
# base de données.
install-backend:
	@echo "📦 Installation du backend..."
	@if [ ! -f backend/.env ]; then \
		cp backend/.env.example backend/.env; \
		echo "   → backend/.env créé depuis .env.example"; \
	fi
	$(MAKE) start
	@echo "📦 Dépendances backend (composer install)..."
	@docker exec chatbot-symfony composer install --no-interaction
	$(MAKE) db-install
	@echo "✅ Backend installé !"

# frontend/.env (API_URL, ADMIN_USERNAME, ADMIN_PASSWORD) n'a pas
# d'exemple versionné -- à créer à la main si absent, rien à générer
# automatiquement. `npm install` ici est pour l'outillage local
# (IDE, `make format`/lint hors Docker) -- le conteneur nuxt fait son
# propre `npm ci` à chaque démarrage (voir compose.yaml), donc ce n'est
# pas requis pour que l'appli tourne.
install-frontend:
	@echo "📦 Installation du frontend..."
	@if [ ! -f frontend/.env ]; then \
		echo "⚠️  frontend/.env manquant -- à créer (API_URL, ADMIN_USERNAME, ADMIN_PASSWORD)"; \
	fi
	@cd frontend && npm install
	@echo "✅ Frontend installé !"

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
		echo "   - database (MariaDB)"; \
		echo "   - qdrant"; \
		echo "   - phpmyadmin"; \
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

# `doctrine:database:create` seul ne suffit pas ici : il applique la
# collation par défaut du serveur MariaDB (utf8mb4_uca1400_ai_ci sur
# MariaDB 11), absente de information_schema.COLLATION_CHARACTER_SET_
# APPLICABILITY sur cette version -- ça fait planter
# doctrine:migrations:migrate (assert($options !== null)) dès le second
# run, une fois que la table de suivi des migrations existe. D'où le
# CREATE DATABASE manuel avec collation explicite entre les deux.
db-install:
	@echo "🗄️  (Ré)installation de la base de données..."
	@docker exec chatbot-symfony php bin/console doctrine:database:drop --force --if-exists
	@docker exec backend_symfony-database-1 mariadb -u $(MYSQL_USER) -p'$(MYSQL_PASSWORD)' \
		-e "CREATE DATABASE $(MYSQL_DATABASE) DEFAULT CHARACTER SET utf8mb4 DEFAULT COLLATE utf8mb4_unicode_ci;"
	@docker exec chatbot-symfony php bin/console doctrine:migrations:migrate --no-interaction
	@echo "✅ Base de données réinstallée !"
