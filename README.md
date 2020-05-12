[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Part-DB/Part-DB-symfony/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/Part-DB/Part-DB-symfony/?branch=master)
[![Build Status](https://travis-ci.com/Part-DB/Part-DB-symfony.svg?branch=master)](https://travis-ci.com/Part-DB/Part-DB-symfony)
[![codecov](https://codecov.io/gh/Part-DB/Part-DB-symfony/branch/master/graph/badge.svg)](https://codecov.io/gh/Part-DB/Part-DB-symfony)
![GitHub License](https://img.shields.io/github/license/Part-DB/Part-DB-symfony)
![PHP Version](https://img.shields.io/badge/PHP-%3E%3D%207.2-green)

![Docker Pulls](https://img.shields.io/docker/pulls/jbtronics/part-db1)
![Docker Cloud Build Status](https://img.shields.io/docker/cloud/build/jbtronics/part-db1)

# Part-DB
Part-DB is an Open-Source inventory managment system for your electronic components.
It is installed on a web server and so can be accessed with any browser without the need to install additional software.

The version in this Repository is a complete rewrite of the legacy [Part-DB](https://github.com/Part-DB/Part-DB) (Version < 1.0) based on a modern framework.
In the moment it lacks some features from the old Part-DB and the testing and documentation is not finished.
Part-DB is in undergoing development, that means features and appeareance can change, and the software can contain bugs.

If you find a bug, please open an [Issue on Github](https://github.com/Part-DB/Part-DB-symfony/issues) so it can be fixed for everybody.

## Demo
If you want to test Part-DB without installing it, you can use [this](https://part-db.herokuapp.com) Heroku instance. 
(Or this link for the [German Version](https://part-db.herokuapp.com/de/)). 

You can log in with username: *user* and password: *user*.

Every change to the master branch gets automatically deployed, so it represents the currenct development progress and is
maybe not completly stable. Please mind, that the free Heroku instance is used, so it can take some time when loading the page
for the first time.

## Features
As this version of Part-DB is under development, some of the features listed below is not existing yet and the
list of the features could change in the future. Features that are not working yet are marked with a star (*).

* Inventory managment of your electronic parts. Each part can be assigned to a category, footprint, manufacturer 
and multiple store locations and price informations. Parts can be grouped using tags. Support for file attachments like datasheets. 
* Multi-Language support (currently German, English, Russian, Japanese and French (experimental))
* Barcodes/Labels generator for parts and storage locations
* User system with groups and detailed permissions. 
Two-factor authentication is supported (Google Authenticator and U2F keys) and can be enforced. Password reset via email can be setuped.
* Import/Export system (*)
* Project managment: Parts can be assigned to projects to manage how often a project can be build. (*)
* Order managment: Collect parts that should be ordered during the next order on your distributor and automatically add
it to your instock, when they arrive. (*)
* Event log: Track what changes happens to your inventory, track which user does what. Revert your parts to older versions.
* Responsive design: You can use Part-DB on your PC, your tablet and your smartphone using the same interface.
* PartKeepr import (*)

With this features Part-DB is useful to hobbyists, who want to keep track of their private electronic parts inventory,
or makerspaces, where many users have should have (controlled) access to the shared inventory.

Part-DB is also used by small companies and universities for managing their inventory.

## Requirements
 * A **web server** (like Apache2 or nginx) that is capable of running [Symfony 4](https://symfony.com/doc/current/reference/requirements.html),
 this includes a minimum PHP version of **PHP 7.2.5**
 * A **MySQL** (at least 5.6.5) /**MariaDB** (at least 10.0.1) database server
 * Shell access to your server is highly suggested!
 * For building the client side assets **yarn** and **nodejs** is needed.
 
## Installation
**Caution:** It is possible to upgrade the old Part-DB databases. 
Anyhow, the migrations that will be made, are not compatible with the old Part-DB versions, so you must not use the old Part-DB versions with the new database, or the DB could become corrupted. 
Also after the migration it is not possible to go back to the old database scheme, so make sure to make a backup of your database beforehand.
See [UPGRADE](UPGRADE.md) for more infos.

*Hint:* A docker image is available under [jbtronics/part-db1](https://hub.docker.com/repository/docker/jbtronics/part-db1).

1. Copy or clone this repository into a folder on your server.
2. Configure your webserver to serve from the `public/` folder. See [here](https://symfony.com/doc/current/setup/web_server_configuration.html)
for additional informations.
3. Copy the global config file `cp .env .env.local` and edit `.env.local`:
    * Change the line `APP_ENV=dev` to `APP_ENV=prod`
    * Change the value of `DATABASE_URL=` to your needs (see [here](http://docs.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html#connecting-using-a-url)) for the format.
4. Install composer dependencies and generate autoload files: `composer install --no-dev`
5. If you have put Part-DB into a sub-directory on your server (like `part-db/`), you have to edit the file 
`webpack.config.js` and uncomment the lines (remove the `//` before the lines) `.setPublicPath('/part-db/build')` (line 43) and
 `.setManifestKeyPrefix('build/')` (line 44). You have to replace `/part-db` with your own path on line 44.
6. Install client side dependencies and build it: `yarn install` and `yarn build`
7. _Optional_ (speeds up first load): Warmup cache: `php bin/console cache:warmup`
8. Upgrade database to new scheme (or create it, when it was empty): `php bin/console doctrine:migrations:migrate` and follow the instructions given. **Caution**: This steps tamper with your database and could potentially destroy it. So make sure to make a backup of your database.
9. You can configure Part-DB via `config/parameters.yaml`. You should check if settings match your expectations, after you installed/upgraded Part-DB. Check if `partdb.default_currency` matches your mainly used currency (this can not be changed after creating price informations). 
   Run `php bin/console cache:clear` when you changed something.

When you want to upgrade to a newer version, then just copy the new files into the folder
and repeat the steps 4. to 7.

## Useful console commands
Part-DB provides some command consoles which can be invoked by `php bin/console [command]`. You can get help for every command with the parameter `--help`.
Useful commands are:
* `php bin/console app:set-password [username]`: Sets a new password for the user with the given username. Useful if you forget the password to your Part-DB instance.
* `php bin/console app:show-logs`: Show last activty log on console. Use `-x` options, to include extra column.
* `php bin/console app:update-exchange-rates`: Update the exchange rates of your currencies from internet. Setup this to be run in a cronjob to always get up-to-date exchange rates.
 If you dont use Euro as base currency, you have to setup an fixer.io API key in `.env.local`.
* `php bin/console app:clean-attachments`: Removes all unused files (files without an associated attachment) in attachments folder. 
Normally Part-DB should be able to delete the attachment file, if you delete the attachment, but if you have some obsolete files left over from legacy Part-DB you can remove them safely with this command.
* `php cache:clear`: Remove and rebuild all caches. If you encounter some weird issues in Part-DB, it maybe helps to run this command.
* `php .\bin\console doctrine:migrations:up-to-date`: Check if your database is up to date.

## Built with
* [Symfony 4](https://symfony.com/): The main framework used for the serverside PHP
* [Bootstrap 4](https://getbootstrap.com/) and [Fontawesome](https://fontawesome.com/) : Used for the webpages

## Authors
* **Jan Böhmer** - *Inital work* - [Github](https://github.com/jbtronics/)

See also the list of [contributors](https://github.com/Part-DB/Part-DB-symfony/graphs/contributors) who participated in this project.

Based on the original Part-DB by Christoph Lechner and K. Jacobs

## License
Part-DB is licensed under the GNU Affero General Public License v3.0 (or at your opinion any later).
This mostly means that you can use Part-DB for whatever you want (even use it commercially)
as long as you publish the source code for every change you make under the AGPL, too.

See [LICENSE](https://github.com/Part-DB/Part-DB-symfony/blob/master/LICENSE) for more informations.
