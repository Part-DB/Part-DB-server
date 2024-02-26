---
title: Backup & Restore Data
layout: default
parent: Usage
---

# Backup and Restore Data

When working productively you should back up the data and configuration of Part-DB regularly to prevent data loss. This
is also useful if you want to migrate your Part-DB instance from one server to another. In that case, you just have to
back up the data on server 1, move the backup to server 2, install Part-DB on server 2, and restore the backup.

## Backup (automatic / Part-DB supported)

Part-DB includes a command `php bin/console partdb:backup` which automatically collects all the needed data (described
below) and saves them to a ZIP file.

If you are using a MySQL/MariaDB database you need to have `mysqldump` installed and added to your `$PATH` env.

### Usage

To back up all possible data, run the following
command: `php bin/console partdb:backup --full /path/to/backup/partdb_backup.zip`.

It is possible to do only partial backups (config, attachments, or database). See `php bin/console partdb:backup --help`
for more info about these options.

## Backup (manual)

3 parts have to be backup-ed: The configuration files, which contain the instance-specific options, the
uploaded files of attachments, and the database containing the most data of Part-DB.
Everything else like thumbnails and cache files, are recreated automatically when needed.

### Configuration files

You have to copy the `.env.local` file and (if you have changed it) the `config/parameters.yaml` and `config/banner.md`
to your backup location.

### Attachment files

You have to recursively copy the `uploads/` folder and the `public/media` folder to your backup location.

### Database

#### SQLite

If you are using sqlite, it is sufficient to just copy your `app.db` from your database location (normally `var/app.db`)
to your backup location.

#### MySQL / MariaDB

For MySQL / MariaDB you have to dump the database to an SQL file. You can do this manually with phpmyadmin, or you
use [`mysqldump`](https://mariadb.com/kb/en/mariadb-dumpmysqldump/) to dump the database to an SQL file via command line
interface (`mysqldump -uBACKUP -pPASSWORD DATABASE`)

## Restore

Install Part-DB as usual as described in the installation section, except for the database creation/migration part. You
have to use the same database type (SQLite or MySQL) as on the backuped server instance.

### Restore configuration

Copy configuration files `.env.local`, (and if existing) `config/parameters.yaml` and `config/banner.md` from the backup
to your new Part-DB instance and overwrite the existing files there.

### Restore attachment files

Copy the `uploads/` and the `public/media/` folder from your backup into your new Part-DB folder.

### Restore database

#### SQLite

Copy the backup-ed `app.db` into the database folder normally `var/app.db` in Part-DB root folder.

#### MySQL / MariaDB

Recreate a database and user with the same credentials as before (or update the database credentials in the `.env.local`
file).
Import the dumped SQL file from the backup into your new database.