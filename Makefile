# PartDB Makefile for Test Environment Management

.PHONY: help deps-install lint format format-check test coverage pre-commit all test-typecheck \
test-setup test-clean test-db-create test-db-migrate test-cache-clear test-fixtures test-run test-reset \
section-dev dev-setup dev-clean dev-db-create dev-db-migrate dev-cache-clear dev-warmup dev-reset

# Default target
help: ## Show this help
	@awk 'BEGIN {FS = ":.*##"}; /^[a-zA-Z0-9][a-zA-Z0-9_-]+:.*##/ {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

# Dependencies
deps-install: ## Install PHP dependencies with unlimited memory
	@echo "📦 Installing PHP dependencies..."
	COMPOSER_MEMORY_LIMIT=-1 composer install
	yarn install
	@echo "✅ Dependencies installed"

# Complete test environment setup
test-setup: test-clean test-db-create test-db-migrate test-fixtures ## Complete test setup (clean, create DB, migrate, fixtures)
	@echo "✅ Test environment setup complete!"

# Clean test environment
test-clean: ## Clean test cache and database files
	@echo "🧹 Cleaning test environment..."
	rm -rf var/cache/test
	rm -f var/app_test.db
	@echo "✅ Test environment cleaned"

# Create test database
test-db-create: ## Create test database (if not exists)
	@echo "🗄️  Creating test database..."
	-php bin/console doctrine:database:create --if-not-exists --env test || echo "⚠️  Database creation failed (expected for SQLite) - continuing..."

# Run database migrations for test environment
test-db-migrate: ## Run database migrations for test environment
	@echo "🔄 Running database migrations..."
	COMPOSER_MEMORY_LIMIT=-1 php bin/console doctrine:migrations:migrate -n --env test

# Clear test cache
test-cache-clear: ## Clear test cache
	@echo "🗑️  Clearing test cache..."
	rm -rf var/cache/test
	@echo "✅ Test cache cleared"

# Load test fixtures
test-fixtures: ## Load test fixtures
	@echo "📦 Loading test fixtures..."
	php bin/console partdb:fixtures:load -n --env test

# Run PHPUnit tests
test-run: ## Run PHPUnit tests
	@echo "🧪 Running tests..."
	php bin/phpunit

# Quick test reset (clean + migrate + fixtures, skip DB creation)
test-reset: test-cache-clear test-db-migrate test-fixtures
	@echo "✅ Test environment reset complete!"

test-typecheck: ## Run static analysis (PHPStan)
	@echo "🧪 Running type checks..."
	COMPOSER_MEMORY_LIMIT=-1 composer phpstan

# Development helpers
dev-setup: dev-clean dev-db-create dev-db-migrate dev-warmup ## Complete development setup (clean, create DB, migrate, warmup)
	@echo "✅ Development environment setup complete!"

dev-clean: ## Clean development cache and database files
	@echo "🧹 Cleaning development environment..."
	rm -rf var/cache/dev
	rm -f var/app_dev.db
	@echo "✅ Development environment cleaned"

dev-db-create: ## Create development database (if not exists)
	@echo "🗄️  Creating development database..."
	-php bin/console doctrine:database:create --if-not-exists --env dev || echo "⚠️  Database creation failed (expected for SQLite) - continuing..."

dev-db-migrate: ## Run database migrations for development environment
	@echo "🔄 Running database migrations..."
	COMPOSER_MEMORY_LIMIT=-1 php bin/console doctrine:migrations:migrate -n --env dev

dev-cache-clear: ## Clear development cache
	@echo "🗑️  Clearing development cache..."
	rm -rf var/cache/dev
	@echo "✅ Development cache cleared"

dev-warmup: ## Warm up development cache
	@echo "🔥 Warming up development cache..."
	COMPOSER_MEMORY_LIMIT=-1 php -d memory_limit=1G bin/console cache:warmup --env dev -n

dev-reset: dev-cache-clear dev-db-migrate ## Quick development reset (cache clear + migrate)
	@echo "✅ Development environment reset complete!" 