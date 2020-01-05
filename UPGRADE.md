# Upgrade from legacy Versions (Part-DB 0.5/0.6)

This document describes how to upgrade from an old Part-DB version (Part-DB 0.6 or older) to Part-DB 1.0.
The instructions on how to install the new version or upgrade from Part-DB 1.0 to a newer version, see 
[README](README.md).

## Breaking Changes
Please note that there are some breaking changes with the new version. 
It is tried to keep the breaking changes as small as possible, so they should not have much impact for the most users:
 * PHP 7.2 is required now (Part-DB 0.5 supported PHP 5.4+, Part-DB 0.6 7.0). 
  PHP 7.2 (or newer) is shipped by all current major Linux distros now (and can be installed by third party sources on others),
   Releases are available for Windows too, so almost everybody should be able to use PHP 7.2
 * Console access highly required. The installation of composer and frontend dependencies require console access, also 
 the managment commands are using CLI, so you should have console access on your server.
 * Markdown/HTML is now used instead of BBCode for rich text in description and command fields.
 It is possible to migrate your existing BBCode to Markdown via `php bin/console php bin/console app:convert-bbcode`.
 * Server exceptions are not logged to Event log anymore. For security reasons (exceptions can contain sensitive informations)
 exceptions are only logged to server log (by default under './var/log'), so only the server admins can access it.
 
 ## Upgrade process
 1. Upgrade your existing Part-DB version the newest Part-DB 0.5.* version (in the moment Part-DB 0.5.8), like 
 described in the old Part-DB's repository.
 2. Make a backup of your database. If somethings goes wrong during migration, you can use this backup to start over.
 3. Setup the new Part-DB like described on [README](README.md) in section Installation. In `.env.local` enter the URL
 to your old Part-DB database.
 4. Run `php bin/console app:convert-bbcode` to convert the BBCode used in comments and part description to the newly used markdown.
 5. Copy the content of `data/media` from the old Part-DB version into `public/media` in the new version.
 6. Run 'php bin/console cache:clear'

You should now be able to access Part-DB and log in using your old credentials. 

**It is not possible to access the database using the old Part-DB version. 
If you do so, this could damage your database.** Therefore it is recommended to remove the old Part-DB version.