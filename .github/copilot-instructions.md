# Copilot Instructions for Part-DB

Part-DB is an Open-Source inventory management system for electronic components built with Symfony 7.4 and modern web technologies.

## Technology Stack

- **Backend**: PHP 8.2+, Symfony 7.4, Doctrine ORM
- **Frontend**: Bootstrap 5, Hotwire Stimulus/Turbo, TypeScript, Webpack Encore
- **Database**: MySQL 5.7+/MariaDB 10.4+/PostgreSQL 10+/SQLite
- **Testing**: PHPUnit with DAMA Doctrine Test Bundle
- **Code Quality**: Easy Coding Standard (ECS), PHPStan (level 5)

## Project Structure

- `src/`: PHP application code organized by purpose (Controller, Entity, Service, Form, etc.)
- `assets/`: Frontend TypeScript/JavaScript and CSS files
- `templates/`: Twig templates for views
- `tests/`: PHPUnit tests mirroring the `src/` structure
- `config/`: Symfony configuration files
- `public/`: Web-accessible files
- `translations/`: Translation files for multi-language support

## Coding Standards

### PHP Code

- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) and [Symfony coding standards](https://symfony.com/doc/current/contributing/code/standards.html)
- Use type hints for all parameters and return types
- Always declare strict types: `declare(strict_types=1);` at the top of PHP files
- Use PHPDoc blocks for complex logic or when type information is needed

### TypeScript/JavaScript

- Use TypeScript for new frontend code
- Follow existing Stimulus controller patterns in `assets/controllers/`
- Use Bootstrap 5 components and utilities
- Leverage Hotwire Turbo for dynamic page updates

### Naming Conventions

- Entities: Use descriptive names that reflect database models (e.g., `Part`, `StorageLocation`)
- Controllers: Suffix with `Controller` (e.g., `PartController`)
- Services: Descriptive names reflecting their purpose (e.g., `PartService`, `LabelGenerator`)
- Tests: Match the class being tested with `Test` suffix (e.g., `PartTest`, `PartControllerTest`)

## Development Workflow

### Dependencies

- Install PHP dependencies: `composer install`
- Install JS dependencies: `yarn install`
- Build frontend assets: `yarn build` (production) or `yarn watch` (development)

### Database

- Create database: `php bin/console doctrine:database:create --env=dev`
- Run migrations: `php bin/console doctrine:migrations:migrate --env=dev`
- Load fixtures: `php bin/console partdb:fixtures:load -n --env=dev`

Or use Makefile shortcuts:
- `make dev-setup`: Complete development environment setup
- `make dev-reset`: Reset development environment (cache clear + migrate)

### Testing

- Set up test environment: `make test-setup`
- Run all tests: `php bin/phpunit`
- Run specific test: `php bin/phpunit tests/Path/To/SpecificTest.php`
- Run tests with coverage: `php bin/phpunit --coverage-html var/coverage`
- Test environment uses SQLite by default for speed

### Static Analysis

- Run PHPStan: `composer phpstan` or `COMPOSER_MEMORY_LIMIT=-1 php -d memory_limit=1G vendor/bin/phpstan analyse src --level 5`
- PHPStan configuration is in `phpstan.dist.neon`

### Running the Application

- Development server: `symfony serve` (requires Symfony CLI)
- Or configure Apache/nginx to serve from `public/` directory
- Set `APP_ENV=dev` in `.env.local` for development mode

## Best Practices

### Security

- Always sanitize user input
- Use Symfony's security component for authentication/authorization
- Check permissions using the permission system before allowing actions
- Never expose sensitive data in logs or error messages
- Use parameterized queries (Doctrine handles this automatically)

### Performance

- Use Doctrine query builder for complex queries instead of DQL when possible
- Lazy load relationships to avoid N+1 queries
- Cache results when appropriate using Symfony's cache component
- Use pagination for large result sets (DataTables integration available)

### Database

- Always create migrations for schema changes: `php bin/console make:migration`
- Review migration files before running them
- Use Doctrine annotations or attributes for entity mapping
- Follow existing entity patterns for relationships and lifecycle callbacks

### Frontend

- Use Stimulus controllers for interactive components
- Leverage Turbo for dynamic page updates without full page reloads
- Use Bootstrap 5 classes for styling
- Keep JavaScript modular and organized in controllers
- Use the translation system for user-facing strings

### Translations

- Use translation keys, not hardcoded strings: `{{ 'part.info.title'|trans }}`
- Add new translation keys to `translations/` files
- Primary language is English (en)
- Translations are managed via Crowdin, but can be edited locally if needed

### Testing

- Write unit tests for services and helpers
- Write functional tests for controllers
- Use fixtures for test data
- Tests should be isolated and not depend on execution order
- Mock external dependencies when appropriate
- Follow existing test patterns in the repository

## Common Patterns

### Creating an Entity

1. Create entity class in `src/Entity/` with Doctrine attributes
2. Generate migration: `php bin/console make:migration`
3. Review and run migration: `php bin/console doctrine:migrations:migrate`
4. Create repository if needed in `src/Repository/`
5. Add fixtures in `src/DataFixtures/` for testing

### Adding a Form

1. Create form type in `src/Form/`
2. Extend `AbstractType` and implement `buildForm()` and `configureOptions()`
3. Use in controller and render in Twig template
4. Follow existing form patterns for consistency

### Creating a Controller Action

1. Add method to appropriate controller in `src/Controller/`
2. Use route attributes for routing
3. Check permissions using security voters
4. Return Response or render Twig template
5. Add corresponding template in `templates/`

### Adding a Service

1. Create service class in `src/Services/`
2. Use dependency injection via constructor
3. Tag service in `config/services.yaml` if needed
4. Services are autowired by default

## Important Notes

- Part-DB uses fine-grained permissions - always check user permissions before actions
- Multi-language support is critical - use translation keys everywhere
- The application supports multiple database backends - write portable code
- Responsive design is important - test on mobile/tablet viewports
- Event system is used for logging changes - emit events when appropriate
- API Platform is integrated for REST API endpoints

## Multi-tenancy Considerations

- Part-DB is designed as a single-tenant application with multiple users
- User groups have different permission levels
- Always scope queries to respect user permissions
- Use the security context to get current user information

## Resources

- [Documentation](https://docs.part-db.de/)
- [Contributing Guide](CONTRIBUTING.md)
- [Symfony Documentation](https://symfony.com/doc/current/index.html)
- [Doctrine Documentation](https://www.doctrine-project.org/projects/doctrine-orm/en/current/)
- [Bootstrap 5 Documentation](https://getbootstrap.com/docs/5.1/)
- [Hotwire Documentation](https://hotwired.dev/)
