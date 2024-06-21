---
title: "Choosing database: SQLite or MySQL"
layout: default
parent: Installation
nav_order: 1
---

# Choosing database: SQLite or MySQL

Part-DB saves its data in a [relational (SQL) database](https://en.wikipedia.org/wiki/Relational_database).

For this multiple database types are supported, currently these are:

* [SQLite](https://www.sqlite.org/index.html)
* [MySQL](https://www.mysql.com/) / [MariaDB](https://mariadb.org/) (which are mostly the same, except for some minor
  differences)
* [PostgreSQL](https://www.postgresql.org/)

All these database types allow for the same basic functionality and allow Part-DB to run. However, there are some minor
differences between them, which might be important for you. Therefore the pros and cons of the different database types
are listed here.

{: .important }
You have to choose between the database types before you start using Part-DB and **you can not change it (easily) after
you have started creating data**. So you should choose the database type for your use case (and possible future uses).

## Comparison

### SQLite

#### Pros

* **Easy to use**: No additional installation or configuration is needed, just start Part-DB and it will work out of the box
* **Easy backup**: Just copy the SQLite file to a safe place, and you have a backup, which you can restore by copying it
  back. No need to work with SQL dumps

#### Cons

* **Performance**: SQLite is not as fast as MySQL or PostgreSQL, especially when using complex queries or many users.
* **Emulated RegEx search**: SQLite does not support RegEx search natively. Part-DB can emulate it, however that is pretty slow.
* **Emualted natural sorting**: SQLite does not support natural sorting natively. Part-DB can emulate it, but it is pretty slow.
* **Limitations with Unicode**: SQLite has limitations in comparisons and sorting of Unicode characters, which might lead to
  unexpected behavior when using non-ASCII characters in your data. For example `µ` (micro sign) is not seen as equal to 
  `μ` (greek minuscule mu), therefore searching for `µ` (micro sign) will not find parts containing `μ` (mu) and vice versa.
  The other databases behave more intuitive in this case.
* **No advanced features**: SQLite do no support many of the advanced features of MySQL or PostgreSQL, which might be utilized
  in future versions of Part-DB


### MySQL/MariaDB

**If possible, it is recommended to use MariaDB 10.7+ (instead of MySQL), as it supports natural sorting of columns natively.**

#### Pros

* **Performance**: Compared to SQLite, MySQL/MariaDB will probably perform better, especially in large databases with many
  users and high activity.
* **Natural Sorting**: MariaDB 10.7+ supports natural sorting of columns. On other databases it has to be emulated, which is pretty
  slow.
* **Native RegEx search**: MySQL supports RegEx search natively, which is faster than emulating it in PHP.
* **Advanced features**: MySQL/MariaDB supports many advanced features, which might be utilized in future versions of Part-DB.
* **Full Unicode support**: MySQL/MariaDB has better support for Unicode characters, which makes it more intuitive to use
  non-ASCII characters in your data.

#### Cons

* **Additional installation and configuration**: You have to install and configure the MySQL server, create a database and
  user for Part-DB, which needs some additional work compared to SQLite.
* **Backup**: The MySQL database has to be dumped to a SQL file (using `mysqldump`). The `console partdb:backup` command can automate this.


### PostgreSQL

#### Pros
* **Performance**: PostgreSQL is known for its performance, especially in large databases with many users and high activity.
* **Advanced features**: PostgreSQL supports many advanced features, which might be utilized in future versions of Part-DB.
* **Full Unicode support**: PostgreSQL has better support for Unicode characters, which makes it more intuitive to use
  non-ASCII characters in your data.
* **Native RegEx search**: PostgreSQL supports RegEx search natively, which is faster than emulating it in PHP.
* **Native Natural Sorting**: PostgreSQL supports natural sorting of columns natively in all versions and in general the support for it
  is better than on MariaDB.
* **Support of transactional DDL**: PostgreSQL supports transactional DDL, which means that if you encounter a problem during a schema change,
the database will automatically rollback the changes. On MySQL/MariaDB you have to manually rollback the changes, by restoring from a database backup.

#### Cons
* **New backend**: The support of postgresql is new, and it was not tested as much as the other backends. There might be some bugs caused by this.
* **Additional installation and configuration**: You have to install and configure the PostgreSQL server, create a database and
  user for Part-DB, which needs some additional work compared to SQLite.
* **Backup**: The PostgreSQL database has to be dumped to a SQL file (using `pg_dump`). The `console partdb:backup` command can automate this.


## Recommendation

When you are a hobbyist and use Part-DB for your own small inventory management with only you as user (or maybe sometimes
a few other people), then the easy-to-use SQLite database will be fine, as long as you can live with the limitations, stated above.
However using MariaDB (or PostgreSQL), has no disadvantages in that situation (besides the initial setup requirements), so you might
want to use it, to be prepared for future use cases.

When you are planning to have a very big database, with a lot of entries and many users which regularly using Part-DB, then you should
use MariaDB or PostgreSQL, as they will perform better in that situation and allow for more advanced features.
If you should use MariaDB or PostgreSQL depends on your personal preference and what you already have installed on your servers and 
what you are familiar with.