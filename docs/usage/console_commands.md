---
title: Console commands
layout: default
parent: Usage
---

# Console commands

Part-DB provides some console commands to display various information or perform some tasks.
The commands are invoked from the main directory of Part-DB with the command `php bin/console [command]` in the context
of the database user (so usually the webserver user), so you maybe have to use `sudo` or `su` to execute the commands.

You can get help for every command with the parameter `--help`. See `php bin/console` for a list of all available
commands.

## User management commands

* `php bin/console partdb:users:list`: List all users of this Part-DB instance
* `php bin/console partdb:users:set-password [username]`: Set/Changes the password of the user with the given username.
  This allows administrators to reset a password of a user, if he forgot it.
* `php bin/console partdb:users:enable [username]`: Enable/Disable the user with the given username (use `--disable` to
  disable the user, which prevents login)
* `php bin/console partdb:users:permissions`: View/Change the permissions of the user with the given username
* `php bin/console partdb:users:upgrade-permissions-schema`: Upgrade the permissions schema of users to the latest
  version (this is normally automatically done when the user visits a page)
* `php bin/console partdb:logs:show`: Show the most recent entries of the Part-DB event log / recent activity
* `php bin/console partdb:user:convert-to-saml-user`: Convert a local user to a SAML/SSO user. This is needed, if you
  want to use SAML/SSO authentication for a user, which was created before you enabled SAML/SSO authentication.

## Currency commands

* `php bin/console partdb:currencies:update-exchange-rates`: Update the exchange rates of all currencies from the
  internet

## Installation/Maintenance commands

* `php bin/console partdb:backup`: Backup the database and the attachments
* `php bin/console partdb:version`: Display the current version of Part-DB and the used PHP version
* `php bin/console partdb:check-requirements`: Check if the requirements for Part-DB are met (PHP version, PHP
  extensions, etc.) and make suggestions what could be improved
* `partdb:migrations:convert-bbcode`: Migrate the old BBCode markup codes used in legacy Part-DB versions (< 1.0.0) to
  the new Markdown syntax
* `partdb:attachments:clean-unused`: Remove all attachments which are not used by any database entry (e.g. orphaned
  attachments)
* `partdb:cache:clear`: Clears all caches, so the next page load will be slower, but the cache will be rebuilt. This can
  maybe fix some issues, when the cache were corrupted. This command is also needed after changing things in
  the `parameters.yaml` file or upgrading Part-DB.
* `partdb:migrations:import-partkeepr`: Imports a mysqldump XML dump of a PartKeepr database into Part-DB. This is only
  needed for users, which want to migrate from PartKeepr to Part-DB. *All existing data in the Part-DB database is
  deleted!*

## Database commands

* `php bin/console doctrine:migrations:migrate`: Migrate the database to the latest version
* `php bin/console doctrine:migrations:up-to-date`: Check if the database is up-to-date