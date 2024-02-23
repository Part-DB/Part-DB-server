---
layout: default
title: Troubleshooting
---

# Troubleshooting

Sometimes things go wrong and Part-DB shows an error message. This page should help you to solve the problem.

## Error messages

When a common, easy fixable error occurs (like a non-up-to-date database), Part-DB will show you some short instructions
on how to fix the problem. If you have a problem that is not listed here, please open an issue on GitHub.

## General procedure

If you encounter an error, try the following steps:

* Clear the cache of Part-DB with the console command:

```bash
php bin/console cache:clear
```

* Check if the database needs an update (and perform it when needed) with the console command:

```bash
php bin/console doctrine:migrations:migrate
```

If this does not help, please [open an issue on GitHub](https://github.com/Part-DB/Part-DB-symfony).

## Search for the user and reset the password:

You can list all users with the following command: `php bin/console partdb:users:list`
To reset the password of a user you can use the following
command: `php bin/console partdb:users:set-password [username]`

## Error logs

Detailed error logs can be found in the `var/log` directory.
When Part-DB is installed directly, the errors are written to the `var/log/prod.log` file.

When Part-DB is installed with Docker, the errors are written directly to the console output.
You can see the logs with the following command, when you are in the folder with the `docker-compose.yml` file

```bash
docker-compose logs -f
```

Please include the error logs in your issue on GitHub, if you open an issue.

## Report Issue

If an error occurs, or you found a bug, please [open an issue on GitHub](https://github.com/Part-DB/Part-DB-symfony).
