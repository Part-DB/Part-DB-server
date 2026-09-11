# Development

Two methods are available for setting up a development environment.
1. [Direct PHP environment](#direct-php-environment-setup)
2. [Docker based environment](#docker-based-environment-setup)


# Direct PHP Environment Setup


For setting up a native PHP development environment, you will need to install PHP, Composer, a database server (MySQL or MariaDB) and yarn (which needs a Node.js environment).
* Copy `.env` to `.env.local` and change `APP_ENV` to `APP_ENV=dev`. That way you will get development tools (Symfony profiler) and other features that
  will simplify development.
* Run `composer install` (without -o) to install PHP dependencies and `yarn install` to install frontend dependencies.
* Run `yarn watch`. The program will run in the background and compile the frontend files whenever you change something in the CSS or TypeScript files.
* For running Part-DB, it is recommended to use [Symfony CLI](https://symfony.com/download).
  That way you can run a correctly configured webserver with `symfony serve`.


# Docker Based Environment Setup


This document describes how to set up a complete Docker-based development environment for Part-DB.


## Requirements

The following software is required:

* Docker
* Docker Compose
* Git

No local installation of PHP, Composer, Node.js, Yarn or a database server is required.

---

## Development Environment

The development environment consists of two Docker images:

* **Dockerfile** – the upstream production image used as the development base.
* **Dockerfile.dev** – a lightweight development image layered on top of the production image that provides:

    * Composer development support
    * Node.js and Yarn
    * Live frontend rebuilding
    * Development PHP configuration
    * Automatic permission handling for bind-mounted source code

The development environment uses:

* SQLite database
* Symfony development mode
* Webpack Encore live asset rebuilding (`yarn watch`)

All application source code remains on the host machine.

---

## Initial Setup

Clone the repository:

```bash
git clone https://github.com/Part-DB/Part-DB-server.git
cd Part-DB-server
```

### Visual Studio Code Dev Container

The first setup can take several minutes. When it finishes, open either:

```
http://localhost:8080/
https://localhost:8443/
```

The HTTPS endpoint uses a self-signed development certificate. Your browser will show a certificate warning until you explicitly accept the certificate or add it to your local trust store. The command-line Docker setup only exposes HTTP.

During the first database migration an administrator account is created. To retrieve its generated password, open a local terminal and inspect the setup service logs:

```bash
docker compose -f compose.dev.yaml \
    -f .devcontainer/compose.devcontainer.yaml logs setup
```

The automatic setup runs again when the Dev Container is rebuilt. Docker, Composer, and Yarn reuse their caches, and database migrations only apply migrations that have not already run.

### Command-line Setup

Build the upstream production base image:

```bash
docker build --file Dockerfile --tag partdb-local-dev-base .
```

Build the development image:

```bash
docker compose -f compose.dev.yaml build
```

Install Composer dependencies:

```bash
docker compose -f compose.dev.yaml run --rm partdb composer install
```

Verify that Symfony is correctly configured:

```bash
docker compose -f compose.dev.yaml run --rm partdb php bin/console about
```

The output should report:

```text
Environment    dev
Debug          true
```

Build the frontend assets:

```bash
docker compose -f compose.dev.yaml run --rm assets \
    sh -lc 'yarn install --network-timeout 600000 && yarn build'
```

Create the development database:

```bash
docker compose -f compose.dev.yaml run --rm partdb \
    php bin/console doctrine:migrations:migrate
```

During the initial migration an administrator account is created.

The output contains the generated password:

```text
[warning] The initial password for the "admin" user is: ********
```

Record this password for the initial login.

Start the development environment:

```bash
docker compose -f compose.dev.yaml up -d partdb assets
```

Confirm both services are running:

```bash
docker compose -f compose.dev.yaml ps
```

Inspect the application logs:

```bash
docker compose -f compose.dev.yaml logs --tail=100 partdb
```

Inspect the frontend build logs:

```bash
docker compose -f compose.dev.yaml logs --tail=100 assets
```

Open Part-DB in your workstation browser:

```
http://localhost:8080/
```

---

## Daily Development Workflow

Start the development environment:

```bash
docker compose -f compose.dev.yaml up -d
```

Restart the services:

```bash
docker compose -f compose.dev.yaml restart
```

Check service status:

```bash
docker compose -f compose.dev.yaml ps
```

Watch the application log:

```bash
docker compose -f compose.dev.yaml logs -f partdb
```

Watch the frontend asset compiler:

```bash
docker compose -f compose.dev.yaml logs -f assets
```

Stop the development environment:

```bash
docker compose -f compose.dev.yaml down
```

---

## Composer

Install or update PHP dependencies:

```bash
docker compose -f compose.dev.yaml run --rm partdb composer install
```

Run any Composer command:

```bash
docker compose -f compose.dev.yaml run --rm partdb \
    composer <command>
```

Examples:

```bash
docker compose -f compose.dev.yaml run --rm partdb \
    composer update

docker compose -f compose.dev.yaml run --rm partdb \
    composer require vendor/package
```

---

## Symfony Console

Run any Symfony command:

```bash
docker compose -f compose.dev.yaml run --rm partdb \ 
    php bin/console <command>
```

Examples:

```bash
docker compose -f compose.dev.yaml run --rm partdb \
    php bin/console cache:clear

docker compose -f compose.dev.yaml run --rm partdb \
    php bin/console about
```

---

## Frontend Development

The `assets` service runs `yarn watch` and automatically rebuilds frontend assets 
whenever CSS or TypeScript files change.

To monitor the asset compiler:

```bash
docker compose -f compose.dev.yaml logs -f assets
```

To perform a one-off production build:

```bash
docker compose -f compose.dev.yaml run --rm assets yarn build
```

---

## Testing

To run phpunit testing:

```bash
docker compose -f compose.dev.yaml run --rm partdb php bin/phpunit <test file>
```


To run code coverage test:

```bash
docker compose -f compose.dev.yaml run --rm \
    -e XDEBUG_MODE=coverage partdb php bin/phpunit --coverage-text \
    <test file>
```

Examples:

```bash
docker compose -f compose.dev.yaml run --rm partdb php bin/phpunit \
  tests/Services/ImportExportSystem/BOMImporterTest.php

docker compose -f compose.dev.yaml run --rm -e XDEBUG_MODE=coverage \
    partdb php bin/phpunit --coverage-text \
    tests/Services/ImportExportSystem/BOMImporterTest.php
```

---

## Database

The development environment uses SQLite.

The database is stored on the host at:

```
var/db/app.db
```

The database is **not** removed when containers are recreated.

Run migrations only when:

* creating a new development environment
* new Doctrine migrations have been added
* the SQLite database has been deleted

Run migrations:

```bash
docker compose -f compose.dev.yaml run --rm partdb \
    php bin/console doctrine:migrations:migrate
```

---

## Logs

Application logs:

```bash
docker compose -f compose.dev.yaml logs -f partdb
```

Frontend logs:

```bash
docker compose -f compose.dev.yaml logs -f assets
```

---

## Docker Images

The development environment uses two images.

### Base image

Built from the upstream Dockerfile:

```bash
docker build --file Dockerfile --tag partdb-local-dev-base .
```

This image only needs rebuilding when:

* the upstream Dockerfile changes
* PHP packages change
* system packages change

### Development image

Built from `Dockerfile.dev`:

```bash
docker compose -f compose.dev.yaml build
```

Rebuild this image after changing:

* `Dockerfile.dev`
* `compose.dev.yaml`
* `.docker/dev/`

---

## Troubleshooting

### Rebuild the development image

```bash
docker compose -f compose.dev.yaml build
docker compose -f compose.dev.yaml up -d --force-recreate
```

### View container logs

```bash
docker compose -f compose.dev.yaml logs -f partdb

docker compose -f compose.dev.yaml logs -f assets
```

### Remove containers

```bash
docker compose -f compose.dev.yaml down
```

### Clean generated files

```bash
rm -rf \
    vendor \
    node_modules \
    var/cache \
    var/log \
    var/db \
    var/share \
    public/build \
    public/bundles
```

The tracked files under `uploads/` and `public/media/` should not be removed.

---

## Notes

The Docker development environment intentionally differs from the native development workflow described in `CONTRIBUTING.md`.

Specifically:

* No `.env.local` file is required.
* Symfony configuration is supplied through Docker environment variables.
* No local PHP installation is required.
* No local Composer installation is required.
* No local Node.js or Yarn installation is required.
* No local database server is required.
* Live frontend rebuilding is provided automatically by the `assets` service.
