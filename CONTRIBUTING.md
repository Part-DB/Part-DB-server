# How to contribute

Thank you for consider to contribute to Part-DB!
Please read the text below, so your contributed content can be contributed easily to Part-DB.

You can contribute to Part-DB in various ways:
* Report bugs and request new features via [issues](https://github.com/Part-DB/Part-DB-symfony/issues)
* Improve translations (via https://part-db.crowdin.com/part-db)
* Improve code (either PHP, Javascript or HTML templates) by creating a [pull request](https://github.com/Part-DB/Part-DB-symfony/pulls)

## Translations
The recommended way to create/improve translations is to use the online platform [Crowdin](https://part-db.crowdin.com/part-db).
Register an account there and join the Part-DB team.

If you want to start translation for a new language that does not have an entry on Crowdin yet, send an message to `@jbtronics`.

Part-DB uses translation keys (e.g. part.info.title) that are sorted by their usage, so you will most likely have to lookup, how the key
was translated in other languages (this is possible via the "Other languages" dropdown in the translation editor).

## Development environment
For setting up an development you will need to install PHP, composer, a database server (MySQL or MariaDB) and yarn (which needs an nodejs environment).
* Copy `.env` to `.env.local` and change `APP_ENV` to `APP_ENV=dev`. That way you will get development tools (symfony profiler) and other features that
will simplify development.
* Run `composer install` (without -o) to install PHP dependencies and `yarn install` to install frontend dependencies
* Run `yarn watch`. The program will run in the background and compile the frontend files whenever you change something in the CSS or TypeScript files
* For running Part-DB it is recommended to use [Symfony CLI](https://symfony.com/download). 
That way you can run a correct configured webserver with `symfony serve`

## Coding style
Code should follow the [PSR12-Standard](https://www.php-fig.org/psr/psr-12/) and symfony's [coding standards](https://symfony.com/doc/current/contributing/code/standards.html).

Part-DB uses [Easy Coding Standard](https://github.com/symplify/easy-coding-standard) to check and fix coding style violations:
* To check your code for valid code style run `vendor/bin/ecs check src/`
* To fix violations run `vendor/bin/ecs check src/` (please checks afterwards if the code is valid afterwards)


