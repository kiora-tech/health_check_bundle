.PHONY: help build up down restart shell test phpstan cs-check cs-fix qa install clean logs

help: ## Display this help message
	@echo "Available commands:"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

build: ## Build Docker images
	docker compose build

up: ## Start all services
	docker compose up -d
	@echo "Waiting for services to be ready..."
	@sleep 5
	@echo "Services are ready!"

down: ## Stop all services
	docker compose down

restart: down up ## Restart all services

shell: ## Open a shell in the PHP container
	docker compose exec php sh

install: up ## Install dependencies
	docker compose exec php composer install

test: ## Run PHPUnit tests
	docker compose exec php composer test

phpstan: ## Run PHPStan analysis
	docker compose exec php composer phpstan

cs-check: ## Check code style
	docker compose exec php composer cs-check

cs-fix: ## Fix code style
	docker compose exec php composer cs-fix

qa: ## Run all quality checks (GrumPHP)
	docker compose exec php composer qa

check: phpstan cs-check ## Run PHPStan and code style checks

clean: down ## Clean up containers and volumes
	docker compose down -v
	rm -rf vendor

logs: ## Show logs from all services
	docker compose logs -f

logs-php: ## Show logs from PHP container
	docker compose logs -f php

mysql: ## Connect to MySQL
	docker compose exec mysql mysql -utest -ptest health_check_test

postgres: ## Connect to PostgreSQL
	docker compose exec postgres psql -U test -d health_check_test

redis: ## Connect to Redis CLI
	docker compose exec redis redis-cli
