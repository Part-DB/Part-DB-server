[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Part-DB/Part-DB-symfony/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/Part-DB/Part-DB-symfony/?branch=master)
![PHPUnit Tests](https://github.com/Part-DB/Part-DB-symfony/workflows/PHPUnit%20Tests/badge.svg)
![Static analysis](https://github.com/Part-DB/Part-DB-symfony/workflows/Static%20analysis/badge.svg)
[![codecov](https://codecov.io/gh/Part-DB/Part-DB-server/branch/master/graph/badge.svg)](https://codecov.io/gh/Part-DB/Part-DB-server)
![GitHub License](https://img.shields.io/github/license/Part-DB/Part-DB-symfony)
![PHP Version](https://img.shields.io/badge/PHP-%3E%3D%208.1-green)

![Docker Pulls](https://img.shields.io/docker/pulls/jbtronics/part-db1)
![Docker Build Status](https://github.com/Part-DB/Part-DB-symfony/workflows/Docker%20Image%20Build/badge.svg)
[![Crowdin](https://badges.crowdin.net/e/8325196085d4bee8c04b75f7c915452a/localized.svg)](https://part-db.crowdin.com/part-db)

**[Documentation](https://docs.part-db.de/)** | **[Demo](https://demo.part-db.de/)** | **[Docker Image](https://hub.docker.com/r/jbtronics/part-db1)**

# Part-DB

Part-DB is an Open-Source inventory management system for your electronic components.
It is installed on a web server and so can be accessed with any browser without the need to install additional software.

The version in this repository is a complete rewrite of the legacy [Part-DB](https://github.com/Part-DB/Part-DB)
(Version < 1.0) based on a modern framework and is the recommended version to use.

If you find a bug, please open an [Issue on GitHub,](https://github.com/Part-DB/Part-DB-server/issues) so it can be fixed
for everybody.

## Demo

If you want to test Part-DB without installing it, you can use [this](https://demo.part-db.de/) Heroku instance.
(Or this link for the [German Version](https://demo.part-db.de/de/)).

You can log in with username: *user* and password: *user*.

Every change to the master branch gets automatically deployed, so it represents the current development progress and is
may not completely stable. Please mind, that the free Heroku instance is used, so it can take some time when loading
the page
for the first time.

<img src="https://github.com/Part-DB/Part-DB-server/raw/master/docs/assets/readme/part_info.png">
<img src="https://github.com/Part-DB/Part-DB-server/raw/master/docs/assets/readme/parts_list.png">

## Features

* Inventory management of your electronic parts. Each part can be assigned to a category, footprint, manufacturer,
  and multiple store locations and price information. Parts can be grouped using tags. You can associate various files
  like datasheets or pictures with the parts.
* Multi-language support (currently German, English, Russian, Japanese, French, Czech, Danish, and Chinese)
* Barcodes/Labels generator for parts and storage locations, scan barcodes via webcam using the builtin barcode scanner
* User system with groups and detailed (fine granular) permissions.
  Two-factor authentication is supported (Google Authenticator and Webauthn/U2F keys) and can be enforced for groups.
  Password reset via email can be set up.
* Optional support for single sign-on (SSO) via SAML (using an intermediate service
  like [Keycloak](https://www.keycloak.org/) you can connect Part-DB to an existing LDAP or Active Directory server)
* Import/Export system for parts and data structure. BOM import for projects from KiCAD is supported.
* Project management: Create projects and assign parts to the bill of material (BOM), to show how often you could build
  this project and directly withdraw all components needed from DB
* Event log: Track what changes happen to your inventory, track which user does what. Revert your parts to older
  versions.
* Responsive design: You can use Part-DB on your PC, your tablet, and your smartphone using the same interface.
* MySQL and SQLite are supported as database backends
* Support for rich text descriptions and comments in parts
* Support for multiple currencies and automatic update of exchange rates supported
* Powerful search and filter function, including parametric search (search for parts according to some specifications)
* Automatic thumbnail generation for pictures
* Use cloud providers (like Octopart, Digikey, Farnell, LCSC or TME) to automatically get part information, datasheets, and
  prices for parts
* API to access Part-DB from other applications/scripts
* [Integration with KiCad](https://docs.part-db.de/usage/eda_integration.html): Use Part-DB as the central datasource for your
  KiCad and see available parts from Part-DB directly inside KiCad.

With these features, Part-DB is useful to hobbyists, who want to keep track of their private electronic parts inventory,
or maker spaces, where many users should have (controlled) access to the shared inventory.

Part-DB is also used by small companies and universities for managing their inventory.

## Requirements

* A **web server** (like Apache2 or nginx) that is capable of
  running [Symfony 5](https://symfony.com/doc/current/reference/requirements.html),
  this includes a minimum PHP version of **PHP 8.1**
* A **MySQL** (at least 5.7) /**MariaDB** (at least 10.2.2) database server if you do not want to use SQLite.
* Shell access to your server is highly suggested!
* For building the client-side assets **yarn** and **nodejs** (>= 18.0) is needed.

## Installation

If you want to upgrade your legacy (< 1.0.0) version of Part-DB to this version, please
read [this](https://docs.part-db.de/upgrade_legacy.html) first.

*Hint:* A docker image is available under [jbtronics/part-db1](https://hub.docker.com/r/jbtronics/part-db1). How to set
up Part-DB via docker is described [here](https://docs.part-db.de/installation/installation_docker.html).

**Below you find a very rough outline of the installation process, see [here](https://docs.part-db.de/installation/)
for a detailed guide on how to install Part-DB.**

1. Copy or clone this repository into a folder on your server.
2. Configure your webserver to serve from the `public/` folder.
   See [here](https://symfony.com/doc/current/setup/web_server_configuration.html)
   for additional information.
3. Copy the global config file `cp .env .env.local` and edit `.env.local`:
    * Change the line `APP_ENV=dev` to `APP_ENV=prod`
    * If you do not want to use SQLite, change the value of `DATABASE_URL=` to your needs (
      see [here](http://docs.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html#connecting-using-a-url))
      for the format.
      In bigger instances with concurrent accesses, MySQL is more performant. This can not be changed easily later, so
      choose wisely.
4. Install composer dependencies and generate autoload files: `composer install -o --no-dev`
5. Install client side dependencies and build it: `yarn install` and `yarn build`
6. _Optional_ (speeds up first load): Warmup cache: `php bin/console cache:warmup`
7. Upgrade database to new scheme (or create it, when it was empty): `php bin/console doctrine:migrations:migrate` and
   follow the instructions given. During the process the password for the admin is user is shown. Copy it. **Caution**:
   These steps tamper with your database and could potentially destroy it. So make sure to make a backup of your
   database.
8. You can configure Part-DB via `config/parameters.yaml`. You should check if settings match your expectations after
   you installed/upgraded Part-DB. Check if `partdb.default_currency` matches your mainly used currency (this can not be
   changed after creating price information).
   Run `php bin/console cache:clear` when you change something.
9. Access Part-DB in your browser (under the URL you put it) and log in with user *admin*. Password is the one outputted
   during DB setup.
   If you can not remember the password, set a new one with `php bin/console app:set-password admin`. You can create
   new users with the admin user and start using Part-DB.

When you want to upgrade to a newer version, then just copy the new files into the folder
and repeat the steps 4. to 7.

Normally a random password is generated when the admin user is created during initial database creation,
however, you can set the initial admin password, by setting the `INITIAL_ADMIN_PW` env var.

You can configure Part-DB to your needs by changing environment variables in the `.env.local` file.
See [here](https://docs.part-db.de/configuration.html) for more information.

### Reverse proxy

If you are using a reverse proxy, you have to ensure that the proxies set the `X-Forwarded-*` headers correctly, or you
will get HTTP/HTTPS mixup and wrong hostnames.
If the reverse proxy is on a different server (or it cannot access Part-DB via localhost) you have to set
the `TRUSTED_PROXIES` env variable to match your reverse proxy's IP address (or IP block). You can do this in
your `.env.local` or (when using docker) in your `docker-compose.yml` file.

## Donate for development

If you want to donate to the Part-DB developer, see the sponsor button in the top bar (next to the repo name).
There you will find various methods to support development on a monthly or a one-time base.

## Built with

* [Symfony 5](https://symfony.com/): The main framework used for the serverside PHP
* [Bootstrap 5](https://getbootstrap.com/) and [Bootswatch](https://bootswatch.com/): Used as website theme
* [Fontawesome](https://fontawesome.com/): Used as icon set
* [Hotwire Stimulus](https://stimulus.hotwired.dev/) and [Hotwire Turbo](https://turbo.hotwired.dev/): Frontend
  Javascript

## Authors

* **Jan Böhmer** - *Initial work* - [GitHub](https://github.com/jbtronics/)

See also the list of [contributors](https://github.com/Part-DB/Part-DB-server/graphs/contributors) who participated in
this project.

Based on the original Part-DB by Christoph Lechner and K. Jacobs

## License

Part-DB is licensed under the GNU Affero General Public License v3.0 (or at your opinion any later).
This mostly means that you can use Part-DB for whatever you want (even use it commercially)
as long as you publish the source code for every change you make under the AGPL, too.

See [LICENSE](https://github.com/Part-DB/Part-DB-server/blob/master/LICENSE) for more information.
