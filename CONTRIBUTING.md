# How to contribute

Thank you for consider to contribute to Part-DB!
Please read the text below, so your contributed content can be contributed easily to Part-DB.

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
Part-DB uses symfony's recommended [project structure](https://symfony.com/doc/current/best_practices.html).
Interesting folders are:
* `public`: Everything in this directory will be publicy accessible via web. Use this folder to serve static images.
* `assets`: The frontend assets are saved here. You can find the javascript and CSS code here.
* `src`: Part-DB's PHP code is saved here. Note that the sub directories are structured by the classes purposes (so use `Controller` Controllers, `Entities` for Database models, etc.)
* `translations`: The translations used in Part-DB are saved here
* `templates`: The templates (HTML) that are used by Twig to render the different pages. Email templates are also saved here.
* `tests/`: Tests that can be run by PHPunit.

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

## GitHub actions
Part-DB uses GitHub actions to run various tests and checks on the code:
* Yarn dependencies can compile
* PHPunit tests run successful
* Config files, translations and templates has valid syntax
* Doctrine schema valid
* No known vulnerable dependecies are used
* Static analysis successful (phpstan with `--level=2`)

Further the code coverage of the PHPunit tests is determined and uploaded to [CodeCov](https://codecov.io/gh/Part-DB/Part-DB-server).
