---
layout: default
title: Upgrade from legacy Part-DB version (<1.0)
---

# Upgrade from legacy Part-DB version

Part-DB 1.0 was a complete rewrite of the old Part-DB (< 1.0.0), which you can find [here](https://github.com/Part-DB/Part-DB). A lot of things changed internally, but Part-DB was always developed with compatibility in mind, so you can migrate smoothly to the new Part-DB version, and utilize its new features and improvements.

Some things changed however to the old version and some features are still missing, so be sure to read the following sections carefully before proceeding to upgrade.

## Changes
* PHP 7.4 or higher is required now (Part-DB 0.5 required PHP 5.4+, Part-DB 0.6 PHP 7.0). 
  PHP 7.4 (or newer) is shipped by all current major Linux distros now (and can be installed by third party sources on others),
  Releases are available for Windows too, so almost everybody should be able to use PHP 7.4
* **Console access highly required.** The installation of composer and frontend dependencies require console access, also more sensitive stuff like database migration work via CLI now, so you should have console access on your server.
* Markdown/HTML is now used instead of BBCode for rich text in description and command fields.
 It is possible to migrate your existing BBCode to Markdown via `php bin/console php bin/console partdb:migrations:convert-bbcode`.
* Server exceptions are not logged to Event log anymore. For security reasons (exceptions can contain sensitive informations)
 exceptions are only logged to server log (by default under './var/log'), so only the server admins can access it.
* Profile labels are now saved in Database (before they were saved in a seperate JSON file). **The profiles of legacy Part-DB versions can not be imported into new Part-DB 1.0**
* Label placeholders now use the `[[PLACEHOLDER]]` format instead of `%PLACEHOLDER%`. Also some placeholders has changed.
* Configuration is now done via configuration files / environment variables instead of the WebUI (this maybe change in the future).
* Database updated are now done via console instead of the WebUI

## Missing features
* No possibility to mark parts for ordering (yet)
* No import / export possibility for parts (yet), however you can import/export other datastructures like Categories, Footprints, etc.
* No support for 3D models of footprints (yet)


## Upgrade process
 1. Upgrade your existing Part-DB version the newest Part-DB 0.5.* version (in the moment Part-DB 0.5.8), like 
 described in the old Part-DB's repository.
 2. Make a backup of your database. If somethings goes wrong during migration, you can use this backup to start over.
 3. Setup the new Part-DB like described on [README](README.md) in section Installation. In `.env.local` enter the URL
 to your old Part-DB database.
 4. Run `php bin/console partdb:migrations:convert-bbcode` to convert the BBCode used in comments and part description to the newly used markdown.
 5. Copy the content of `data/media` from the old Part-DB version into `public/media` in the new version.
 6. Run 'php bin/console cache:clear'

You should now be able to access Part-DB and log in using your old credentials. 

**It is not possible to access the database using the old Part-DB version. 
If you do so, this could damage your database.** Therefore it is recommended to remove the old Part-DB version.
