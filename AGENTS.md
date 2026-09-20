# AGENTS.md

Guidance for coding agents working in this repository. See also `CONTRIBUTING.md` and `DEVELOPMENT.md` for more detail.

## Project structure

Part-DB follows Symfony's recommended project structure:

* `public`: publicly accessible web root; static assets go here.
* `assets`: frontend source (JavaScript/TypeScript, CSS).
* `src`: PHP code, organized by purpose (`Controller`, `Entity`, `Repository`, `Services`, etc.).
* `translations`: translation catalogs (XLIFF `.xlf` files).
* `templates`: Twig templates, including email templates.
* `tests`: PHPUnit tests.
* `migrations`: Doctrine migrations.

## Coding style

Code should follow PSR-12 and Symfony's coding standards.

## Static analysis

PHPStan runs at level 5 (see `phpstan.dist.neon`). Check code you touch with:

```bash
composer phpstan
```

## Testing

Run PHPUnit tests for anything you change:

```bash
php bin/phpunit <test file>
```

With Docker:

```bash
docker compose -f compose.dev.yaml run --rm partdb php bin/phpunit <test file>
```

## Other checks worth running before finishing

* If you changed `config/` files: `php bin/console lint:yaml config --parse-tags`
* If you changed Twig templates: `php bin/console lint:twig templates --env=prod`
* If you changed Doctrine entities/mappings: `php bin/console doctrine:schema:validate --skip-sync`
* If you changed entity mappings in a way that affects the database schema, add a corresponding Doctrine migration in `migrations/`.

## Translations

Translation strings live in `translations/<domain>.<locale>.xlf` (XLIFF 2.0). Never hardcode user-facing strings in templates or PHP
code — reference a translation key instead, and add the English source string to the relevant `<domain>.en.xlf` file.

Keep Symfony placeholders (`%name%`) and the entity-synonym tokens (`[part]`, `[[Part]]`, etc. — see `CONTRIBUTING.md`) intact and
unmodified when editing existing strings.

**After adding or changing translation keys, run the translation catalog touch command to fix up message IDs:**

```bash
php bin/console translation:touch-catalog
```

or for a specific domain/locale, e.g.:

```bash
php bin/console translation:touch-catalog messages en
```

Do this before finishing the task whenever you've added a translation key.
