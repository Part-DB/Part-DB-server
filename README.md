[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Part-DB/Part-DB-symfony/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/Part-DB/Part-DB-symfony/?branch=master)
[![Build Status](https://travis-ci.org/Part-DB/Part-DB-symfony.svg?branch=master)](https://travis-ci.org/Part-DB/Part-DB-symfony)

# Part-DB
Part-DB is an Open-Source inventory managment system for your electronic components.
It is installed on a web server and so can be accessed with any browser without the need to install additional software.

The version in this Repository is a complete rewrite of the legacy [Part-DB](https://github.com/Part-DB/Part-DB) (Version < 1.0) based on a modern framework.
In the moment it lacks many features from the old Part-DB and the testing and documentation is not finished, so
this version is not recommendend for productive work!!

## Demo
If you want to test Part-DB without installing it, you can use [this](https://part-db.herokuapp.com) Heroku instance. 
(Or this link for the [German Version](https://part-db.herokuapp.com/de/)). 

You can log in with username: *user* and password: *user*.

Every change to the master branch gets automatically deployed, so it represents the currenct development progress and is
maybe not completly stable. Please mind, that the free Heroku instance is used, so it can take some time when loading the page
for the first time.

## Features
 * TODO
 
## Requirements
 * A **web server** (like Apache2 or nginx) that is capable of running [Symfony 4](https://symfony.com/doc/current/reference/requirements.html),
 this includes a minimum PHP version of **PHP 7.1.3**
 * A **MySQL**/**MariaDB** database server
 * Shell access to your server is highly suggested!
 * For building the client side assets **yarn** and **nodejs** is needed.
 
## Installation
1. Copy or clone this repository into a folder on your server.
2. Configure your webserver to serve from the `public/` folder. See [here](https://symfony.com/doc/current/setup/web_server_configuration.html)
for additional informations.
3. Copy the global config file `cp .env .env.local` and edit `.env.local`:
    * Change the line `APP_ENV=dev` to `APP_ENV=prod`
    * Change the value of `DATABASE_URL=` to your needs (see [here](http://docs.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html#connecting-using-a-url)) for the format.
      *Currently it is not possible to create a new databse. You have to use the database created by an old Part-DB version!*
4. Install composer dependencies and generate autoload files: `composer install --no-dev`
5. Install client side dependencies and build it: `yarn install` and `yarn build`
6. Optional (speeds up first load): Warmup cache: `php bin/console cache:warmup`

When you want to upgrade to a newer version, then just copy the new files into the folder
and repeat the steps 4. to 7.

## Built with
* [Symfony 4](https://symfony.com/): The main framework used for the serverside PHP
* [Bootstrap 4](https://getbootstrap.com/) and [Fontawesome](https://fontawesome.com/) : Used for the webpages

## Authors
* **Jan Böhmer** - *Inital work* - [Github](https://github.com/jbtronics/)

See also the list of [contributors](https://github.com/Part-DB/Part-DB-symfony/graphs/contributors) who participated in this project.

Based on the original Part-DB by Christoph Lechner and K. Jacobs

## License
Part-DB is licensed under the General Public License 2 (or at your opinion any later).
This mostly means that you can use Part-DB for whatever you want (even use it commercially)
as long as you publish the source code for every change you make under the GPL, too.

See [License.md](https://github.com/Part-DB/Part-DB-symfony/blob/master/LICENSE.md) for more informations.
