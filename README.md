[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Part-DB/Part-DB-symfony/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/Part-DB/Part-DB-symfony/?branch=master)
![PHPUnit Tests](https://github.com/Part-DB/Part-DB-symfony/workflows/PHPUnit%20Tests/badge.svg)
![Static analysis](https://github.com/Part-DB/Part-DB-symfony/workflows/Static%20analysis/badge.svg)
[![codecov](https://codecov.io/gh/Part-DB/Part-DB-symfony/branch/master/graph/badge.svg)](https://codecov.io/gh/Part-DB/Part-DB-symfony)
![GitHub License](https://img.shields.io/github/license/Part-DB/Part-DB-symfony)
![PHP Version](https://img.shields.io/badge/PHP-%3E%3D%207.4-green)

![Docker Pulls](https://img.shields.io/docker/pulls/jbtronics/part-db1)
![Docker Build Status](https://github.com/Part-DB/Part-DB-symfony/workflows/Docker%20Image%20Build/badge.svg)
[![Crowdin](https://badges.crowdin.net/e/8325196085d4bee8c04b75f7c915452a/localized.svg)](https://part-db.crowdin.com/part-db)

*When updgrading from a version from before 2022-11-27, please read [this](https://github.com/Part-DB/Part-DB-symfony/discussions/193) before upgrading!*

**[Documentation](https://docs.part-db.de/)**

# Part-DB
Part-DB is an Open-Source inventory managment system for your electronic components.
It is installed on a web server and so can be accessed with any browser without the need to install additional software.

The version in this Repository is a complete rewrite of the legacy [Part-DB](https://github.com/Part-DB/Part-DB) (Version < 1.0) based on a modern framework. Currently it is still missing some (less) features from the old version (see [UPGRADE.md](./UPGRADE.md)) for more details, but also many huge improvements and advantages compared to the old version. If you start completly new with Part-DB it is recommended that you use the version from this repository, as it is actively developed.

If you find a bug, please open an [Issue on Github](https://github.com/Part-DB/Part-DB-symfony/issues) so it can be fixed for everybody.

## Demo
If you want to test Part-DB without installing it, you can use [this](https://part-db.herokuapp.com) Heroku instance. 
(Or this link for the [German Version](https://part-db.herokuapp.com/de/)). 

You can log in with username: *user* and password: *user*.

Every change to the master branch gets automatically deployed, so it represents the currenct development progress and is
maybe not completly stable. Please mind, that the free Heroku instance is used, so it can take some time when loading the page
for the first time.

## Features
* Inventory managment of your electronic parts. Each part can be assigned to a category, footprint, manufacturer 
and multiple store locations and price informations. Parts can be grouped using tags. You can associate various files like datasheets or pictures with the parts.
* Multi-Language support (currently German, English, Russian, Japanese and French (experimental))
* Barcodes/Labels generator for parts and storage locations, scan barcodes via webcam using the builtin barcode scanner
* User system with groups and detailed (fine granular) permissions. 
Two-factor authentication is supported (Google Authenticator and Webauthn/U2F keys) and can be enforced for groups. Password reset via email can be setuped.
* Import/Export system (partial working)
* Project managment: Create projects and assign parts to the bill of material (BOM), to show how often you could build this project and directly withdraw all components needed from DB
* Event log: Track what changes happens to your inventory, track which user does what. Revert your parts to older versions.
* Responsive design: You can use Part-DB on your PC, your tablet and your smartphone using the same interface.
* MySQL and SQLite (experimental) supported as database backends
* Support for rich text descriptions and comments in parts
* Support for multiple currencies and automatic update of exchange rates supported
* Powerful search and filter function, including parametric search (search for parts according to some specifications)


With this features Part-DB is useful to hobbyists, who want to keep track of their private electronic parts inventory,
or makerspaces, where many users have should have (controlled) access to the shared inventory.

Part-DB is also used by small companies and universities for managing their inventory.

## Requirements
 * A **web server** (like Apache2 or nginx) that is capable of running [Symfony 5](https://symfony.com/doc/current/reference/requirements.html),
 this includes a minimum PHP version of **PHP 7.4**
 * A **MySQL** (at least 5.7) /**MariaDB** (at least 10.2.2) database server if you do not want to use SQLite.
 * Shell access to your server is highly suggested!
 * For building the client side assets **yarn** and **nodejs** is needed.
 
## Installation
**Caution:** It is possible to upgrade the old Part-DB databases. 
Anyhow, the migrations that will be made, are not compatible with the old Part-DB versions, so you must not use the old Part-DB versions with the new database, or the DB could become corrupted. 
Also after the migration it is not possible to go back to the old database scheme, so make sure to make a backup of your database beforehand.
See [UPGRADE](UPGRADE.md) for more infos.

*Hint:* A docker image is available under [jbtronics/part-db1](https://hub.docker.com/r/jbtronics/part-db1). How to setup Part-DB via docker is described [here](https://docs.part-db.de/installation/installation_docker.html).

**Below you find some general hints for installation, see [here](https://docs.part-db.de/installation/installation_guide-debian.html) for a detailed guide how to install Part-DB on Debian/Ubuntu.**

1. Copy or clone this repository into a folder on your server.
2. Configure your webserver to serve from the `public/` folder. See [here](https://symfony.com/doc/current/setup/web_server_configuration.html)
for additional informations.
3. Copy the global config file `cp .env .env.local` and edit `.env.local`:
    * Change the line `APP_ENV=dev` to `APP_ENV=prod`
    * If you do not want to use SQLite, change the value of `DATABASE_URL=` to your needs (see [here](http://docs.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html#connecting-using-a-url)) for the format.
      In bigger instances with concurrent accesses, MySQL is more performant. This can not be changed easily later, so choose wisely.
4. Install composer dependencies and generate autoload files: `composer install -o --no-dev`
5. If you have put Part-DB into a sub-directory on your server (like `part-db/`), you have to edit the file 
`webpack.config.js` and uncomment the lines (remove the `//` before the lines) `.setPublicPath('/part-db/build')` (line 43) and
 `.setManifestKeyPrefix('build/')` (line 44). You have to replace `/part-db` with your own path on line 44.
6. Install client side dependencies and build it: `yarn install` and `yarn build`
7. _Optional_ (speeds up first load): Warmup cache: `php bin/console cache:warmup`
8. Upgrade database to new scheme (or create it, when it was empty): `php bin/console doctrine:migrations:migrate` and follow the instructions given. During the process the password for the admin is user is shown. Copy it. **Caution**: This steps tamper with your database and could potentially destroy it. So make sure to make a backup of your database.
9. You can configure Part-DB via `config/parameters.yaml`. You should check if settings match your expectations, after you installed/upgraded Part-DB. Check if `partdb.default_currency` matches your mainly used currency (this can not be changed after creating price informations). 
   Run `php bin/console cache:clear` when you changed something.
10. Access Part-DB in your browser (under the URL you put it) and login with user *admin*. Password is the one outputted during DB setup.
   If you can not remember the password, set a new one with `php bin/console app:set-password admin`. You can create new users with the admin user and start using Part-DB.

When you want to upgrade to a newer version, then just copy the new files into the folder
and repeat the steps 4. to 7.

Normally a random password is generated when the admin user is created during inital database creation,
however you can set the inital admin password, by setting the `INITIAL_ADMIN_PW` env var.

### Reverse proxy
If you are using a reverse proxy, you have to ensure that the proxies sets the `X-Forwarded-*` headers correctly, or you will get HTTP/HTTPS mixup and wrong hostnames.
If the reverse proxy is on a different server (or it cannot access Part-DB via localhost) you have to set the `TRUSTED_PROXIES` env variable to match your reverse proxies IP-address (or IP block). You can do this in your `.env.local` or (when using docker) in your `docker-compose.yml` file.

## Donate for development
If you want to donate to the Part-DB developer, see the sponsor button in the top bar (next to the repo name).
There you will find various methods to support development on a monthly or a one time base.

## Built with
* [Symfony 5](https://symfony.com/): The main framework used for the serverside PHP
* [Bootstrap 5](https://getbootstrap.com/) and [Bootswatch](https://bootswatch.com/): Used as website theme
* [Fontawesome](https://fontawesome.com/: Used as icon set
* [Hotwire Stimulus](https://stimulus.hotwired.dev/) and [Hotwire Turbo](https://turbo.hotwired.dev/): Frontend Javascript

## Authors
* **Jan Böhmer** - *Inital work* - [Github](https://github.com/jbtronics/)

See also the list of [contributors](https://github.com/Part-DB/Part-DB-symfony/graphs/contributors) who participated in this project.

Based on the original Part-DB by Christoph Lechner and K. Jacobs

## License
Part-DB is licensed under the GNU Affero General Public License v3.0 (or at your opinion any later).
This mostly means that you can use Part-DB for whatever you want (even use it commercially)
as long as you publish the source code for every change you make under the AGPL, too.

See [LICENSE](https://github.com/Part-DB/Part-DB-symfony/blob/master/LICENSE) for more informations.
