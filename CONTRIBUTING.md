# How to contribute

Thank you for considering contributing to Part-DB!
Please read the text below, so your contributed content can be incorporated into Part-DB easily.

You can contribute to Part-DB in various ways:
* Report bugs and request new features via [issues](https://github.com/Part-DB/Part-DB-server/issues)
* Improve translations (via https://part-db.crowdin.com/part-db)
* Improve code (either PHP, Javascript or HTML templates) by creating a [pull request](https://github.com/Part-DB/Part-DB-server/pulls)

## Translations
The recommended way to create/improve translations is to use the online platform [Crowdin](https://part-db.crowdin.com/part-db).
Register an account there and join the Part-DB team.

If you want to start translation for a new language that does not have an entry on Crowdin yet, send a message to `@jbtronics`.

Part-DB uses translation keys (e.g. part.info.title) that are sorted by their usage, so you will most likely have to lookup, how the key
was translated in other languages (this is possible via the "Other languages" dropdown in the translation editor).

## Project structure
Part-DB uses Symfony's recommended [project structure](https://symfony.com/doc/current/best_practices.html).
Interesting folders are:
* `public`: Everything in this directory will be publicly accessible via web. Use this folder to serve static images.
* `assets`: The frontend assets are saved here. You can find the JavaScript and CSS code here.
* `src`: Part-DB's PHP code is saved here. Note that the subdirectories are structured by the classes' purposes (so use `Controller` for Controllers, `Entity` for Database models, etc.)
* `translations`: The translations used in Part-DB are saved here.
* `templates`: The templates (HTML) that are used by Twig to render the different pages. Email templates are also saved here.
* `tests/`: Tests that can be run by PHPUnit.

## Development environment
For setting up a development environment, you will need to install PHP, Composer, a database server (MySQL or MariaDB) and yarn (which needs a Node.js environment).
* Copy `.env` to `.env.local` and change `APP_ENV` to `APP_ENV=dev`. That way you will get development tools (Symfony profiler) and other features that
will simplify development.
* Run `composer install` (without -o) to install PHP dependencies and `yarn install` to install frontend dependencies.
* Run `yarn watch`. The program will run in the background and compile the frontend files whenever you change something in the CSS or TypeScript files.
* For running Part-DB, it is recommended to use [Symfony CLI](https://symfony.com/download). 
That way you can run a correctly configured webserver with `symfony serve`.

## Coding style
Code should follow the [PSR-12 Standard](https://www.php-fig.org/psr/psr-12/) and Symfony's [coding standards](https://symfony.com/doc/current/contributing/code/standards.html).

Part-DB uses [Easy Coding Standard](https://github.com/symplify/easy-coding-standard) to check and fix coding style violations:
* To check your code for valid code style, run `vendor/bin/ecs check src/`
* To fix violations, run `vendor/bin/ecs check src/ --fix` (please check afterwards if the code is still valid)

## GitHub actions
Part-DB uses GitHub Actions to run various tests and checks on the code:
* Yarn dependencies can compile
* PHPUnit tests run successfully
* Config files, translations, and templates have valid syntax
* Doctrine schema is valid
* No known vulnerable dependencies are used
* Static analysis is successful (phpstan with `--level=2`)

Further, the code coverage of the PHPUnit tests is determined and uploaded to [CodeCov](https://codecov.io/gh/Part-DB/Part-DB-server).
