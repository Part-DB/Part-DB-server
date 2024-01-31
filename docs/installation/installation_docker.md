---
title: Installation using Docker
layout: default
parent: Installation
nav_order: 2
---

# Installation of Part-DB via docker

Part-DB can be installed containerized via docker. This is the easiest way to get Part-DB up and running and works on
all platforms,
where docker is available (especially recommended for Windows and macOS).

{: .warning }
> The methods described here, configure PHP without HTTPS and therefore should only be used locally in a trusted
> network.
> If you want to expose Part-DB to the internet, you have to configure a reverse proxy with an SSL certificate!

## Docker-compose

Docker-compose configures the needed images and automatically creates the needed containers and volumes.

1. Install docker and docker-compose like described under https://docs.docker.com/compose/install/
2. Create a folder where the Part-DB data should live
3. Create a file named docker-compose.yaml with the following content:

```yaml
version: '3.3'
services:
  partdb:
    container_name: partdb
    # By default Part-DB will be running under Port 8080, you can change it here
    ports:
      - '8080:80'
    volumes:
      # By default
      - ./uploads:/var/www/html/uploads
      - ./public_media:/var/www/html/public/media
      - ./db:/var/www/html/var/db
    restart: unless-stopped
    image: jbtronics/part-db1:latest
    environment:
      # Put SQLite database in our mapped folder. You can configure some other kind of database here too.
      - DATABASE_URL=sqlite:///%kernel.project_dir%/var/db/app.db
      # In docker env logs will be redirected to stderr
      - APP_ENV=docker

      # You can configure Part-DB using environment variables
      # Below you can find the most essential ones predefined
      # However you can add any other environment configuration you want here
      # See .env file for all available options or https://docs.part-db.de/configuration.html
      # !!! Do not use quotes around the values, as they will be interpreted as part of the value and this will lead to errors !!!

      # The language to use serverwide as default (en, de, ru, etc.)
      - DEFAULT_LANG=en
      # The default timezone to use serverwide (e.g. Europe/Berlin)
      - DEFAULT_TIMEZONE=Europe/Berlin
      # The currency that is used inside the DB (and is assumed when no currency is set). This can not be changed later, so be sure to set it the currency used in your country
      - BASE_CURRENCY=EUR
      # The name of this installation. This will be shown as title in the browser and in the header of the website
      - INSTANCE_NAME=Part-DB

      # Allow users to download attachments to the server by providing an URL
      # This could be a potential security issue, as the user can retrieve any file the server has access to (via internet)
      - ALLOW_ATTACHMENT_DOWNLOADS=0
      # Use gravatars for user avatars, when user has no own avatar defined
      - USE_GRAVATAR=0

      # Override value if you want to show a given text on homepage.
      # When this is empty the content of config/banner.md is used as banner
      #- BANNER=This is a test banner<br>with a line break
    
      # If you use a reverse proxy in front of Part-DB, you must configure the trusted proxies IP addresses here (see reverse proxy documentation for more information):
      # - TRUSTED_PROXIES=127.0.0.0/8,::1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16  
```

4. Customize the settings by changing the environment variables (or add new ones). See [Configuration]({% link
   configuration.md %}) for more information.
5. Inside the folder, run

```bash
   docker-compose up -d
```    

6. Create the initial database with

 ```bash
docker exec --user=www-data partdb php bin/console doctrine:migrations:migrate
 ```

and watch for the password output

6. Part-DB is available under `http://localhost:8080` and you can log in with username `admin` and the password shown
   before

The docker image uses a SQLite database and all data (database, uploads and other media) is put into folders relative to
the docker-compose.yml.

### MySQL

If you want to use MySQL as a database, you can use the following docker-compose.yaml, and follow the steps from above:

{: .warning }
> You have to replace the values for MYSQL_ROOT_PASSWORD and MYSQL_PASSWORD with your own passwords!!
> You have to change MYSQL_PASSWORD in the database section and for the DATABASE_URL in the partdb section.

```yaml
version: '3.3'
services:
  partdb:
    container_name: partdb
    # By default Part-DB will be running under Port 8080, you can change it here
    ports:
      - '8080:80'
    volumes:
      # By default
      - ./uploads:/var/www/html/uploads
      - ./public_media:/var/www/html/public/media
      - ./db:/var/www/html/var/db
    restart: unless-stopped
    image: jbtronics/part-db1:latest
    depends_on:
      - database
    environment:
      # Replace SECRET_USER_PASSWORD with the value of MYSQL_PASSWORD from below
      - DATABASE_URL=mysql://partdb:SECRET_USER_PASSWORD@database:3306/partdb
      # In docker env logs will be redirected to stderr
      - APP_ENV=docker

      # You can configure Part-DB using environment variables
      # Below you can find the most essential ones predefined
      # However you can add add any other environment configuration you want here
      # See .env file for all available options or https://docs.part-db.de/configuration.html

      # The language to use serverwide as default (en, de, ru, etc.)
      - DEFAULT_LANG=en
      # The default timezone to use serverwide (e.g. Europe/Berlin)
      - DEFAULT_TIMEZONE=Europe/Berlin
      # The currency that is used inside the DB (and is assumed when no currency is set). This can not be changed later, so be sure to set it the currency used in your country
      - BASE_CURRENCY=EUR
      # The name of this installation. This will be shown as title in the browser and in the header of the website
      - INSTANCE_NAME=Part-DB

      # Allow users to download attachments to the server by providing an URL
      # This could be a potential security issue, as the user can retrieve any file the server has access to (via internet)
      - ALLOW_ATTACHMENT_DOWNLOADS=0
      # Use gravatars for user avatars, when user has no own avatar defined
      - USE_GRAVATAR=0

      # Override value if you want to show to show a given text on homepage.
      # When this is empty the content of config/banner.md is used as banner
      #- BANNER=This is a test banner<br>with a line break

  database:
    container_name: partdb_database
    image: mysql:8.0
    restart: unless-stopped
    command: --default-authentication-plugin=mysql_native_password
    environment:
      # Change this Password
      MYSQL_ROOT_PASSWORD: SECRET_ROOT_PASSWORD
      MYSQL_DATABASE: partdb
      MYSQL_USER: partdb
      MYSQL_PASSWORD: SECRET_USER_PASSWORD
    # Uncomment the following line if you need to access, your MySQL database from outside of docker (e.g. for debugging), normally you should leave that disabled
    #ports:
    #  - '4306:3306'
    volumes:
      - ./mysql:/var/lib/mysql

```

### Update Part-DB

You can update Part-DB by pulling the latest image and restarting the container.
Then you have to run the database migrations again

```bash
docker-compose pull
docker-compose up -d
docker exec --user=www-data partdb php bin/console doctrine:migrations:migrate
```

## Direct use of docker image

You can use the `jbtronics/part-db1:master` image directly. You have to expose the port 80 to a host port and configure
volumes for `/var/www/html/uploads` and `/var/www/html/public/media`.

If you want to use SQLite database (which is default), you have to configure Part-DB to put the database file in a
mapped volume via the `DATABASE_URL` environment variable.
For example if you set `DATABASE_URL=sqlite:///%kernel.project_dir%/var/db/app.db` then you will have to map
the `/var/www/html/var/db/` folder to the docker container (see docker-compose.yaml for example).

You also have to create the database like described above in step 4.

## Running console commands

You can run the console commands described in README by
executing `docker exec --user=www-data -it partdb bin/console [command]`

## Troubleshooting

*Login not possible. Login page is just reloading and no error message is shown or something like "CSFR token invalid"*:

Clear all cookies in your browser or use an inkognito tab for Part-DB.
This related to the fact that Part-DB can not set cookies via HTTP, after some webpage has set cookies before under
localhost via https. This is a security mechanism of the browser and can not be bypassed by Part-DB.
