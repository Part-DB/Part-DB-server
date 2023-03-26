---
layout: default
title: Upgrade from legacy Part-DB version (<1.0)
nav_order: 100
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
* Permission system changed: **You will have to newly set the permissions of all users and groups!**
* Import / Export file format changed. Fields must be english now (unlike in legacy Part-DB versions, where german fields in CSV were possible)
and you maybe have to change the header line/field names of your CSV files.

## Missing features
* No possibility to mark parts for ordering (yet)
* No support for 3D models of footprints (yet)
* No possibility to disable footprints, manufacturers globally (or per category). This should not have a big impact, when you forbid users to edit/create them.
* No resistor calculator or SMD labels tools

## Upgrade process

{: .warning }
> Once you have upgraded the database to the latest version, you will not be able to access the database with Part-DB 0.5.*. Doing so could lead to data corruption. So make a a backup before you proceed the upgrade, so you will be able to revert the upgrade, when you are not happy with the new version
>
> Beware that all user and group permissions will be reset, and you have to set the permissions again 
> the new Part-DB as many permissions changed, and automatic migration is not possible.

 1. Upgrade your existing Part-DB version the newest Part-DB 0.5.* version (in the moment Part-DB 0.5.8), like described in the old Part-DB's repository.
 2. Make a backup of your database and attachments. If somethings goes wrong during migration, you can use this backup to start over. If you have some more complex permission configuration, you maybe want to do screenshots of it, so you can redo it again later.
 3. Setup the new Part-DB like described in installation section. You will need to do the setup for a MySQL instance (either via docker or direct installation). Set the `DATABASE_URL` environment variable in your `.env.local` (or `docker-compose.yaml`) to your existing database. (e.g. `DATABASE_URL=mysql://PARTDB_USER:PASSWORD@localhost:3306/DATABASE_NAME`)
 4. Ensure that the correct base currency is configured (`BASE_CURRENCY` env), this must match the currency used in the old Part-DB version. If you used Euro, you do not need to change anything. 
 5. Run `php bin/console cache:clear` and `php bin/console doctrine:migrations:migrate`.
 4. Run `php bin/console partdb:migrations:convert-bbcode` to convert the BBCode used in comments and part description to the newly used markdown.
 5. Copy the content of the `data/media` folder from the old Part-DB instance into `public/media` folder in the new version.
 6. Run `php bin/console cache:clear`
 7. You should be able to login to Part-DB now using your admin account and the old password. If you do not know the admin username, run `php bin/console partdb:users:list` and look for the user with ID 1. You can reset the password of this user using `php bin/console partdb:users:set-password [username]`.
 8. All other users besides the admin user are disabled (meaning they can not login). Go to "System->User" and "System->Group" and check the permissions of the users (and change them if needed). If you are done enable the users again, by removing the disabled checkmark in the password section. If you have a lot of users you can enable them all at once using `php bin/console partdb:users:enable --all`

**It is not possible to access the database using the old Part-DB version. 
If you do so, this could damage your database.** Therefore it is recommended to remove the old Part-DB version, after everything works.


## Issues
If you encounter any issues (especially during the database migration) or features do not work like intended, please open an issue ticket at GitHub.