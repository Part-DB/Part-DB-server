---
title: Home
layout: home
nav_order: 0
---

# Part-DB
Part-DB is an Open-Source inventory management system for your electronic components.
It is installed on a web server and so can be accessed with any browser without the need to install additional software.

{: .important-title }
> Demo
> 
> If you want to test Part-DB without installing it, you can use [this](https://part-db.herokuapp.com) Heroku instance. 
> (Or this link for the [German Version](https://part-db.herokuapp.com/de/)). 
>
> You can log in with username: **user** and password: **user**, to change/create data.
>
> Every change to the master branch gets automatically deployed, so it represents the currenct development progress and is
> maybe not completly stable. Please mind, that the free Heroku instance is used, so it can take some time when loading the page
> for the first time.

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

## License
Part-DB is licensed under the GNU Affero General Public License v3.0 (or at your opinion any later).
This mostly means that you can use Part-DB for whatever you want (even use it commercially)
as long as you publish the source code for every change you make under the AGPL, too.

See [LICENSE](https://github.com/Part-DB/Part-DB-symfony/blob/master/LICENSE) for more informations.

## Donate for development
If you want to donate to the Part-DB developer, see the sponsor button in the top bar (next to the repo name).
There you will find various methods to support development on a monthly or a one time base.

## Built with
* [Symfony 5](https://symfony.com/): The main framework used for the serverside PHP
* [Bootstrap 5](https://getbootstrap.com/) and [Bootswatch](https://bootswatch.com/): Used as website theme
* [Fontawesome](https://fontawesome.com/): Used as icon set
* [Hotwire Stimulus](https://stimulus.hotwired.dev/) and [Hotwire Turbo](https://turbo.hotwired.dev/): Frontend Javascript

## Authors
* **Jan Böhmer** - *Inital work and Maintainer* - [Github](https://github.com/jbtronics/)

See also the list of [contributors](https://github.com/Part-DB/Part-DB-symfony/graphs/contributors) who participated in this project.

Based on the original Part-DB by Christoph Lechner and K. Jacobs
