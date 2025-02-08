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

## Using the different databases

The only difference in using the different databases, is a different value in the `DATABASE_URL` environment variable in the `.env.local` file
or in the `DATABASE_URL` environment variable in your server or container configuration. It has the shape of a URL, where the scheme (the part before `://`)
is the database type, and the rest is connection information.

**The env var format below is for the `env.local` file. It might work differently for other env configuration. E.g. in a docker-compose file you have to remove the quotes!**

### SQLite

```shell
DATABASE_URL="sqlite:///%kernel.project_dir%/var/app.db"
```

Here you just need to configure the path to the SQLite file, which is created by Part-DB when performing the database migrations.
The `%kernel.project_dir%` is a placeholder for the path to the project directory, which is replaced by the actual path by Symfony, so that you do not
need to specify the path manually. In the example the database will be created as `app.db` in the `var` directory of your Part-DB installation folder.

### MySQL/MariaDB

```shell
DATABASE_URL="mysql://user:password@127.0.0.1:3306/database?serverVersion=8.0.37"
```

Here you have to replace `user`, `password` and `database` with the credentials of the MySQL/MariaDB user and the database name you want to use.
The host (here 127.0.0.1) and port should also be specified according to your MySQL/MariaDB server configuration.

In the `serverVersion` parameter you can specify the version of the MySQL/MariaDB server you are using, in the way the server returns it 
(e.g. `8.0.37` for MySQL and `10.4.14-MariaDB`). If you do not know it, you can leave the default value.

If you want to use a unix socket for the connection instead of a TCP connnection, you can specify the socket path in the `unix_socket` parameter.
```shell
DATABASE_URL="mysql://user:password@localhost/database?serverVersion=8.0.37&unix_socket=/var/run/mysqld/mysqld.sock"
```

### PostgreSQL

```shell
DATABASE_URL="postgresql://db_user:db_password@127.0.0.1:5432/db_name?serverVersion=12.19&charset=utf8"
```

Here you have to replace `db_user`, `db_password` and `db_name` with the credentials of the PostgreSQL user and the database name you want to use.
The host (here 127.0.0.1) and port should also be specified according to your PostgreSQL server configuration.

In the `serverVersion` parameter you can specify the version of the PostgreSQL server you are using, in the way the server returns it
(e.g. `12.19 (Debian 12.19-1.pgdg120+1)`). If you do not know it, you can leave the default value.

The `charset` parameter specify the character set of the database. It should be set to `utf8` to ensure that all characters are stored correctly.

If you want to use a unix socket for the connection instead of a TCP connnection, you can specify the socket path in the `host` parameter.
```shell
DATABASE_URL="postgresql://db_user@localhost/db_name?serverVersion=16.6&charset=utf8&host=/var/run/postgresql"
```


## Natural Sorting

Natural sorting is the sorting of strings in a way that numbers are sorted by their numerical value, not by their ASCII value.

For example in the classical binary sorting the string `DIP-4`, `DIP-8`, `DIP-16`, `DIP-28` would be sorted as following:

* `DIP-16`
* `DIP-28`
* `DIP-4`
* `DIP-8`

In natural sorting, it would be sorted as:

* `DIP-4`
* `DIP-8`
* `DIP-16`
* `DIP-28`

Part-DB can sort names in part tables and tree views naturally. PostgreSQL and MariaDB 10.7+ support natural sorting natively,
and it is automatically used if available.

For SQLite and MySQL < 10.7 it has to be emulated if wanted, which is pretty slow. Therefore it has to be explicity enabled by setting the
`DATABASE_EMULATE_NATURAL_SORT` environment variable to `1`. If it is 0 the classical binary sorting is used, on these databases. The emulations
might have some quirks and issues, so it is recommended to use a database which supports natural sorting natively, if you want to use it.
