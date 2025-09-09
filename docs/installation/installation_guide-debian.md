---
title: Direct Installation on Debian 12
layout: default
parent: Installation
nav_order: 4
---

# Part-DB installation guide for Debian 12 (Bookworm)

This guide shows you how to install Part-DB directly on Debian 12 using apache2 and SQLite. This guide should work with
recent Ubuntu and other Debian-based distributions with little to no changes.
Depending on what you want to do, using the prebuilt docker images may be a better choice, as you don't need to install
this many dependencies. See [here]({% link installation/installation_docker.md %}) for more information on the docker
installation.

{: .warning }
> The methods described here, configure PHP without HTTPS and therefore should only be used locally in a trusted
> network.
> If you want to expose Part-DB to the internet, you HAVE to configure an SSL connection!

It is recommended to install Part-DB on a 64-bit system, as the 32-bit version of PHP is affected by the 
[Year 2038 problem](https://en.wikipedia.org/wiki/Year_2038_problem) and can not handle dates after 2038 correctly.

## Installation with SQLite database

### Install prerequisites

For the installation of Part-DB, we need some prerequisites. They can be installed by running the following command:

```bash
sudo apt update && apt upgrade
sudo apt install git curl zip ca-certificates software-properties-common \
  apt-transport-https lsb-release nano wget sqlite3
```

Please run `sqlite3 --version` to assert that the SQLite version is 3.35 or higher.
Otherwise some database migrations will not succeed.

### Install PHP and apache2

Part-DB is written in [PHP](https://php.net) and therefore needs a PHP interpreter to run. Part-DB needs PHP 8.2 or
higher. However, it is recommended to use the most recent version of PHP for performance reasons and future
compatibility.

Install PHP with required extensions and apache2:

```bash
sudo apt install apache2 php8.2 libapache2-mod-php8.2 \
  php8.2-opcache php8.2-curl php8.2-gd php8.2-mbstring \
  php8.2-xml php8.2-bcmath php8.2-intl php8.2-zip php8.2-xsl \
  php8.2-sqlite3 php8.2-mysql
```

### Install composer

Part-DB uses [composer](https://getcomposer.org/) to install required PHP libraries. Install the latest version manually:

```bash
# Download composer installer script
wget -O /tmp/composer-setup.php https://getcomposer.org/installer
# Install composer globally
php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
# Make composer executable
chmod +x /usr/local/bin/composer
```

### Install yarn and nodejs

To build the front end (the user interface) Part-DB uses [yarn](https://yarnpkg.com/). As it depends on Node.js and the
shipped versions are pretty old, we install new versions from the official Node.js repository:

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs

```

We can install yarn with the following commands:

```bash
# Add yarn repository
curl -sL https://dl.yarnpkg.com/debian/pubkey.gpg | gpg --dearmor | sudo tee /usr/share/keyrings/yarnkey.gpg >/dev/null
echo "deb [signed-by=/usr/share/keyrings/yarnkey.gpg] https://dl.yarnpkg.com/debian stable main" | sudo tee /etc/apt/sources.list.d/yarn.list
# Install yarn
sudo apt update && sudo apt install yarn
```

### Create a folder for Part-DB and download it

We now have all prerequisites installed and can start to install Part-DB. We will create a folder for Part-DB in the
webroot of apache2 and download it to this folder. The downloading is done via git, which allows you to update easily
later.

```bash
# Download Part-DB into the new folder /var/www/partdb
git clone https://github.com/Part-DB/Part-DB-symfony.git /var/www/partdb
```

By default, you are now on the latest development version. In most cases, you want to use the latest stable version. You
can switch to the latest stable version (tagged) by running the following command:

```bash
# This finds the latest release/tag and checks it out
git checkout $(git describe --tags $(git rev-list --tags --max-count=1))
```

Alternatively, you can check out a specific version by running (
see [GitHub Releases page](https://github.com/Part-DB/Part-DB-server/releases) for a list of available versions):

```bash
# This checks out the version 2.0.0
git checkout v2.0.0
```

Change ownership of the files to the apache user:

```bash
chown -R www-data:www-data /var/www/partdb
```

For the next steps we should be in the Part-DB folder, so move into it:

```bash
cd /var/www/partdb
```

### Create configuration for Part-DB

The basic configuration of Part-DB is done by a `.env.local` file in the main directory. Create on by from the default
configuration:

```bash
cp .env .env.local
```

In your `.env.local` you can configure Part-DB according to your wishes and overwrite web interface settings.
A full list of configuration options can be  found [here](../configuration.md).

Please check that the configured base currency matches your mainly used currency, as
this can not be changed after creating price information.

### Install dependencies for Part-DB and build frontend

Part-DB depends on several other libraries and components. Install them by running the following commands:

```bash
# Install composer dependencies (please note the sudo command, to run it under the web server user)
sudo -u www-data composer install --no-dev -o

# Install yarn dependencies
sudo yarn install
# Build frontend
sudo yarn build
```

### Clear cache

To ensure everything is working, clear the cache:

```bash
sudo -u www-data php bin/console cache:clear
```

### Check if everything is installed

To check if everything is installed, run the following command:

```bash
sudo -u www-data php bin/console partdb:check-requirements
```

Most things should be green, and no red ones. Yellow messages mean optional dependencies which are not important
but can improve performance and functionality.

### Create a database for Part-DB

Part-DB by default uses a file-based SQLite database to store the data. Use the following command to create the
database. The database will normally be created at `/var/www/partdb/var/app.db`.

```bash
sudo -u www-data php bin/console doctrine:migrations:migrate
```

The command will warn you about schema changes and potential data loss. Continue with typing `yes`.

The command will output several lines of information. Somewhere should be a yellow background message
like `The initial password for the "admin" user is: f502481134`. Write down this password as you will need it later for the initial login.

### Configure apache2 to show Part-DB

Part-DB is now configured, but we have to say apache2 to serve Part-DB as web application. This is done by creating a
new apache site:

```bash
sudo nano /etc/apache2/sites-available/partdb.conf
```

and add the following content (change ServerName and ServerAlias to your needs):

```
<VirtualHost *:80>
    ServerName partdb.lan
    ServerAlias www.partdb.lan

    DocumentRoot /var/www/partdb/public
    <Directory /var/www/partdb/public>
        AllowOverride All
        Order Allow,Deny
        Allow from All
    </Directory>

    ErrorLog /var/log/apache2/partdb_error.log
    CustomLog /var/log/apache2/partdb_access.log combined
</VirtualHost>
```

Activate the new site by:

```bash
sudo ln -s /etc/apache2/sites-available/partdb.conf /etc/apache2/sites-enabled/partdb.conf
```

Configure apache to show pretty URL paths for Part-DB (`/label/dialog` instead of `/index.php/label/dialog`):

```bash
sudo a2enmod rewrite
```

If you want to access Part-DB via the IP-Address of the server, instead of the domain name, you have to remove the
apache2 default configuration with:

```bash
sudo rm /etc/apache2/sites-enabled/000-default.conf
```

Restart the apache2 webserver with:

```bash
sudo service apache2 restart
```

and Part-DB should now be available under `http://YourServerIP` (or `http://partdb.lan` if you configured DNS in your
network to point to the server).

### Login to Part-DB

Navigate to the Part-DB web interface and login via the user icon in the top right corner. You can log in using the
username `admin` and the password you have written down earlier.
As first steps, you should check out the system settings and check if everything is correct.

## Update Part-DB

If you want to update your existing Part-DB installation, you just have to run the following commands:

```bash
# Move into Part-DB folder
cd /var/www/partdb
# Pull latest Part-DB version from GitHub
git pull

# Checkout the latest version (or use a specific version, like described above) 
git checkout $(git describe --tags $(git rev-list --tags --max-count=1))

# Apply correct permission
chown -R www-data:www-data .
# Install new composer dependencies
sudo -u www-data composer install --no-dev -o
# Install yarn dependencies and build new frontend
sudo yarn install
sudo yarn build

# Check if all your configurations in .env.local and /var/www/partdb/config/parameters.yaml are correct.
sudo nano config/parameters.yaml

# Apply new database schemas (you should do a backup of your database file /var/www/partdb/var/app.db before)
sudo -u www-data php bin/console doctrine:migrations:migrate

# Clear Part-DB cache
sudo -u www-data php bin/console cache:clear
```

## MySQL/MariaDB database

To use a MySQL database, follow the steps from above (except the creation of the database, we will do this later).
Debian 12 does not ship MySQL in its repositories anymore, so we use the compatible MariaDB instead:

1. Install maria-db with:

```bash
sudo apt update && sudo apt install mariadb-server
```

2. Configure maria-db with:

```bash
sudo mysql_secure_installation
```

When asked for the root password, just press enter, as we have not set a root password yet.
In the next steps you are asked if you want to switch to unix_socket authentication, answer with `n` and press enter.
Then you are asked if you want to remove anonymous users, answer with `y` and press enter.
Then you are asked if you want to disallow root login remotely, answer with `y` and press enter.
Then you are asked if you want to remove the test database and access to it, answer with `y` and press enter.
Then you are asked if you want to reload the privilege tables now, answer with `y` and press enter.

3. Create a new database and user for Part-DB: Run the following commands:

```bash
sudo mariadb
```

A SQL shell will open, in which you can run the following commands to create a new database and user for Part-DB.
Replace 'YOUR_SECRET_PASSWORD' with a secure password.

```sql
CREATE DATABASE partdb;
GRANT ALL PRIVILEGES ON partdb.* TO 'partdb'@'localhost' IDENTIFIED BY 'YOUR_SECRET_PASSWORD';
```

Finally, save the changes with:

```sql
FLUSH PRIVILEGES;
```

and exit the SQL shell with:

```sql
exit
```

4. Configure Part-DB to use the new database. Open your `.env.local` file and search the line `DATABASE_URL`.
   Change it to the following (you have to replace `YOUR_SECRET_PASSWORD` with the password you have chosen in step 3):

```
DATABASE_URL=mysql://partdb:YOUR_SECRET_PASSWORD@127.0.0.1:3306/partdb
```

5. Create the database schema with:

```bash
sudo -u www-data php bin/console doctrine:migrations:migrate
``` 

6. The migration step should have shown you a password for the admin user, which you can use now to log in to Part-DB.
