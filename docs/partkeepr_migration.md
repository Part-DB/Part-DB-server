---
layout: default
title: Migrate from PartKeepr to Part-DB
nav_order: 101
---

# Migrate from PartKeepr to Part-DB

{: .warning }
> This feature is currently in beta. Please report any bugs you find.

This guide describes how to migrate from [PartKeepr](https://partkeepr.org/) to Part-DB.

Part-DB has a built-in migration tool, which can be used to migrate the data from an existing PartKeepr instance to
a new Part-DB instance. Most of the data can be migrated, however there are some limitations, you can find below.

## What can be imported

* Datastructures (Categories, Footprints, Storage Locations, Manufacturers, Distributors, Part Measurement Units)
* Basic part information's (Name, Description, Comment, etc.)
* Attachments and images of parts, projects, footprints, manufacturers and storage locations
* Part prices (distributor infos)
* Part parameters
* Projects (including parts and attachments)
* Users (optional): Passwords however will be not migrated, and need to be reset later

## What can't be imported

* Metaparts (A dummy version of the metapart will be created in Part-DB, however it will not function as metapart)
* Multiple manufacturers per part (only the last manufacturer of a part will be migrated)
* Overage information for project parts (the overage info will be set as comment in the project BOM, but will have no
  effect)
* Batch Jobs
* Parameter Units (the units will be written into the parameters)
* Project Reports and Project Runs
* Stock history
* Any kind of PartKeepr preferences

## How to migrate

1. Install Part-DB like described in the installation guide. You can use any database backend you want (mysql or
   sqlite). Run the database migration, but do not create any new data yet.
2. Export your PartKeepr database as XML file using [mysqldump](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html):
   When the MySQL database is running on the local computer, and you are root you can just run the
   command `mysqldump --xml PARTKEEPR_DATABASE --result-file pk.xml`.
   If your server is remote or your MySQL authentication is different, you need to
   run `mysqldump --xml -h PARTKEEPR_HOST -u PARTKEEPR_USER -p PARTKEEPR_DATABASE`, where you replace `PARTKEEPR_HOST`
   with the hostname of your MySQL database and `PARTKEEPR_USER` with the username of MySQL user which has access to the
   PartKeepr database. You will be asked for the MySQL user password.
3. Go the Part-DB main folder and run the command `php bin/console partdb:migrations:import-partkeepr path/to/pk.xml`.
   This step will delete all existing data in the Part-DB database and import the contents of PartKeepr.
4. Copy the contents of `data/files/` from your PartKeepr installation to the `uploads/` folder of your Part-DB
   installation and the contents of `data/images` from PartKeepr to `public/media/` of Part-DB.
5. Clear the cache of Part-DB by running: `php bin/console cache:clear`
6. Go to the Part-DB web interface. You can log in with the username `admin` and the password, which is shown during the
   installation process of Part-DB (step 1). You should be able to see all the data from PartKeepr.

## Import users

If you want to import the users (mostly the username and email address) from PartKeepr, you can add the `--import-users`
option on the database import command (step 3):
`php bin/console partdb:migrations:import-partkeepr --import-users path/to/pk.xml`.

All imported users of PartKeepr will be assigned to a new group "PartKeepr Users", which has normal user permissions (so
editing data, but no administrative tasks). You can change the group and permissions later in Part-DB users management.
Passwords can not be imported from PartKeepr and all imported users get marked as disabled user. So to allow users to
login, you need to enable them in the user management and assign a password.