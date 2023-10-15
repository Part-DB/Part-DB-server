---
title: "Choosing database: SQLite or MySQL"
layout: default
parent: Installation
nav_order: 1
---

# Choosing database: SQLite or MySQL

Part-DB saves its data in a [relational (SQL) database](https://en.wikipedia.org/wiki/Relational_database). Part-DB
supports either the use of [SQLite](https://www.sqlite.org/index.html)
or [MySQL](https://www.mysql.com/) / [MariaDB](https://mariadb.org/) (which are mostly the same, except for some minor
differences).

{: .important }
You have to choose between the database types before you start using Part-DB and **you can not change it (easily) after
you have started creating data**. So you should choose the database type for your use case (and possible future uses).

## Comparison

**SQLite** is the default database type which is configured out of the box. All data is saved in a single file (
normally `var/app.db` in the Part-DB folder) and no additional installation or configuration besides Part-DB is needed.
To use **MySQL/MariaDB** as database, you have to install and configure the MySQL server, configure it and create a
database and user for Part-DB, which needs some additional work. When using docker you need an additional docker
container, and volume for the data

When using **SQLite** The database can be backuped easily by just copying the SQLite file to a safe place. Ideally the *
*MySQL** database has to be dumped to a SQL file (using `mysqldump`). The `console partdb:backup` command can do this
automatically

However, SQLite does not support certain operations like regex search, which has to be emulated by PHP and therefore are
pretty slow compared to the same operation at MySQL. In future there might be features that may only be available, when
using MySQL.

In general MySQL might perform better for big Part-DB instances with many entries, lots of users and high activity, than
SQLite.

## Conclusion and Suggestion

When you are a hobbyist and use Part-DB for your own small inventory management with only you as user (or maybe sometimes
a few other people), then the easy-to-use SQLite database will be fine.

When you are planning to have a very big database, with a lot of entries and many users which regularly (and
concurrently) using Part-DB you should maybe use MySQL as this will scale better.