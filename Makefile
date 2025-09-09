# PartDB Makefile for Test Environment Management

.PHONY: help test-setup test-clean test-db-create test-db-migrate test-cache-clear test-fixtures test-run dev-setup dev-clean dev-db-create dev-db-migrate dev-cache-clear dev-warmup dev-reset

# Default target
help:
	@echo "PartDB Test Environment Management"
	@echo "=================================="
	@echo ""
	@echo "Available targets:"
	@echo "  test-setup      - Complete test environment setup (clean, create DB, migrate, load fixtures)"
	@echo "  test-clean      - Clean test cache and database files"
	@echo "  test-db-create  - Create test database (if not exists)"
	@echo "  test-db-migrate - Run database migrations for test environment"
	@echo "  test-cache-clear- Clear test cache"
	@echo "  test-fixtures   - Load test fixtures"
	@echo "  test-run        - Run PHPUnit tests"
	@echo ""
	@echo "Development Environment:"
	@echo "  dev-setup       - Complete development environment setup (clean, create DB, migrate, warmup)"
	@echo "  dev-clean       - Clean development cache and database files"
	@echo "  dev-db-create   - Create development database (if not exists)"
	@echo "  dev-db-migrate  - Run database migrations for development environment"
	@echo "  dev-cache-clear - Clear development cache"
	@echo "  dev-warmup      - Warm up development cache"
	@echo "  dev-reset       - Quick development reset (clean + migrate)"
	@echo ""
	@echo "  help           - Show this help message"

# Complete test environment setup
test-setup: test-clean test-db-create test-db-migrate test-fixtures
	@echo "✅ Test environment setup complete!"

# Clean test environment
test-clean:
	@echo "🧹 Cleaning test environment..."
	rm -rf var/cache/test
	rm -f var/app_test.db
	@echo "✅ Test environment cleaned"

# Create test database
test-db-create:
	@echo "🗄️  Creating test database..."
	-php bin/console doctrine:database:create --if-not-exists --env test || echo "⚠️  Database creation failed (expected for SQLite) - continuing..."

# Run database migrations for test environment
test-db-migrate:
	@echo "🔄 Running database migrations..."
	COMPOSER_MEMORY_LIMIT=-1 php bin/console doctrine:migrations:migrate -n --env test

# Clear test cache
test-cache-clear:
	@echo "🗑️  Clearing test cache..."
	rm -rf var/cache/test
	@echo "✅ Test cache cleared"

# Load test fixtures
test-fixtures:
	@echo "📦 Loading test fixtures..."
	php bin/console partdb:fixtures:load -n --env test

# Run PHPUnit tests
test-run:
	@echo "🧪 Running tests..."
	php bin/phpunit

# Quick test reset (clean + migrate + fixtures, skip DB creation)
test-reset: test-cache-clear test-db-migrate test-fixtures
	@echo "✅ Test environment reset complete!"

# Development helpers
dev-setup: dev-clean dev-db-create dev-db-migrate dev-warmup
	@echo "✅ Development environment setup complete!"

dev-clean:
	@echo "🧹 Cleaning development environment..."
	rm -rf var/cache/dev
	rm -f var/app_dev.db
	@echo "✅ Development environment cleaned"

dev-db-create:
	@echo "🗄️  Creating development database..."
	-php bin/console doctrine:database:create --if-not-exists --env dev || echo "⚠️  Database creation failed (expected for SQLite) - continuing..."

dev-db-migrate:
	@echo "🔄 Running database migrations..."
	COMPOSER_MEMORY_LIMIT=-1 php bin/console doctrine:migrations:migrate -n --env dev

dev-cache-clear:
	@echo "🗑️  Clearing development cache..."
	rm -rf var/cache/dev
	@echo "✅ Development cache cleared"

dev-warmup:
	@echo "🔥 Warming up development cache..."
	COMPOSER_MEMORY_LIMIT=-1 php -d memory_limit=1G bin/console cache:warmup --env dev -n

dev-reset: dev-cache-clear dev-db-migrate
	@echo "✅ Development environment reset complete!" 