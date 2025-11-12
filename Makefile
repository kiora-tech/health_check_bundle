.PHONY: help build up down restart shell install test phpstan cs-fix cs-check qa clean

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

build: ## Build Docker images
	docker-compose build

up: ## Start development environment
	docker-compose up -d
	@echo "Waiting for services to be ready..."
	@sleep 5
	@echo "✓ Services started!"
	@echo "Run 'make install' to install dependencies"

down: ## Stop development environment
	docker-compose down

restart: down up ## Restart development environment

shell: ## Open a shell in the PHP container
	docker-compose exec php sh

install: ## Install Composer dependencies
	docker-compose exec php composer config --no-plugins allow-plugins.phpro/grumphp true
	docker-compose exec php composer install

test: ## Run PHPUnit tests
	docker-compose exec php composer test

phpstan: ## Run PHPStan static analysis
	docker-compose exec php composer phpstan

cs-fix: ## Fix code style issues
	docker-compose exec php composer cs-fix

cs-check: ## Check code style (dry-run)
	docker-compose exec php composer cs-check

qa: ## Run all quality checks (PHPStan, PHP-CS-Fixer, PHPUnit)
	docker-compose exec php composer qa

# Integration tests
test-integration: ## Run integration tests with all services
	docker-compose -f docker-compose.test.yml up --abort-on-container-exit
	docker-compose -f docker-compose.test.yml down -v

test-integration-build: ## Build and run integration tests
	docker-compose -f docker-compose.test.yml build
	docker-compose -f docker-compose.test.yml up --abort-on-container-exit
	docker-compose -f docker-compose.test.yml down -v

# Cleanup
clean: ## Clean up containers, volumes, and vendor
	docker-compose down -v
	rm -rf vendor composer.lock .phpunit.cache coverage

clean-all: clean ## Clean everything including Docker images
	docker-compose down -v --rmi all
