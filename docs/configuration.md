---
layout: default
title: Configuration
nav_order: 5
---

# Configuration

Part-DBs behavior can be configured to your needs. There are different kind of configuration options: Options which are user changable (changable dynamically via frontend), options which can be configured by environment variables, and options which are only configurable via symfony config files.

## User changable
Following things can be changed for every user and a user can change it for himself (if he has the correct permission for it). Configuration is either possible via the users own setting page (where you can also change the password) or via the user admin page:
* **Language**: The language that the users prefers, and which will be used when no language is explicitly specified. Language can still always be changed via the language selector. By default the global configured language is used.
* **Timezone**: The timezone which the user resides in and in which all dates and times should be shown. By default the globally configured language.
* **Theme**: The theme to use for the frontend. Allows the user to choose the frontend design, he prefers.
* **Prefered currency**: One of the defined currencies, in which all prices should be shown, if possible. Prices with other currencies will be converted to the price selected here

## Environment variables (.env.local)
The following configuration options can only be changed by the server administrator, by either changing the server variables, changing the `.env.local` file or setting env for your docker container. Here are just the most important options listed, see `.env` file for full list of possible env variables.

### General options
* `DATABASE_URL`: Configures the database which Part-DB uses. For mysql use a string in the form of `mysql://<USERNAME>:<PASSWORD>@<HOST>:<PORT>/<TABLE_NAME>` here (e.g. `DATABASE_URL=mysql://user:password@127.0.0.1:3306/part-db`. For sqlite use the following format to specify the absolute path where it should be located `sqlite:///path/part/app.db`. You can use `%kernel.project_dir%` as placeholder for the Part-DB root folder (e.g. `sqlite:///%kernel.project_dir%/var/app.db`)
* `DEFAULT_LANG`: The default language to use serverwide (when no language is explictly specified by a user or via language chooser). Must be something like `en`, `de`, `fr`, etc.
* `DEFAULT_TIMEZONE`: The default timezone to use globally, when a user has not timezone specified. Must be something like `Europe/Berlin`. See [here](https://en.wikipedia.org/wiki/List_of_tz_database_time_zones) under TZ Database name for a list of available options.
* `BASE_CURRENCY`: The currency to use internally for monetary values and when no currency is explictly specified. When migrating from a legacy Part-DB version, this should be the same as the currency in the old Part-DB instance (normally euro). This should be the currency you use the most. **Please note that you can not really change this setting after you have created data**. The value has to be a valid [ISO4217](https://en.wikipedia.org/wiki/ISO_4217) code, like `EUR` or `USD`.
* `INSTANCE_NAME`: The name of your installation. It will be shown as a title in the navbar and other places. By default `Part-DB`, but you can customize it to something likes `ExampleCorp. Inventory`.
* `ALLOW_ATTACHMENT_DOWNLOADS` (allowed values `0` or `1`): By setting this option to 1, users can make Part-DB directly download a file specified as an URL and create it as local file. Please not that this allows users access to all ressources publicly available to the server (so full access to other servers in the same local network), which could be a security risk.
* `USE_GRAVATAR`: Set to `1` to use [gravatar.com](gravatar.com) images for user avatars (as long as they have not set their own picture). The users browsers have to download the pictures from a third-party (gravatars) server, so this might be a privacy risk.


### E-Mail settings
* `MAILER_DSN`: You can configure the mail provider which should be used for email delivery (see https://symfony.com/doc/current/components/mailer.html for full documentation). If you just want to use an SMTP mail account, you can use the following syntax `MAILER_DSN=smtp://user:password@smtp.mailserver.invalid:587`
* `EMAIL_SENDER_EMAIL`: The email address from which emails should be sent from (in most cases this has to be the same as the email address used for SMTP access)
* `EMAIL_SENDER_NAME`: Similar to `EMAIL_SENDER_EMAIL` but this allows you to specify the name from which the mails are sent from.
* `ALLOW_EMAIL_PW_RESET`: Set this value to true, if you wan to allow users to reset their password via an email notification. You have to configure the mailprovider first before via the MAILER_DSN setting.

### History/Eventlog related settings
The following options are used to configure, which (and how much) data is written to the system log:
* `HISTORY_SAVE_CHANGED_FIELDS`: When this option is set to true, the name of the fields which are changed, are saved to the DB (so for example it is logged that a user has changed, that the user has changed the name and description of the field, but not the data/content of these changes)
* `HISTORY_SAVE_CHANGED_DATA`: When this option is set to true, the changed data is saved to log (so it is logged, that a user has changed the name of a part and what the name was before). This can increase database size, when you have a lot of changes to enties.
* `HISTORY_SAVE_REMOVED_DATA`: When this option is set to true, removed data is saved to log, meaning that you can easily undelete an entity, when it was removed accidentally.

If you wanna use want to revert changes or view older revisions of entities, then `HISTORY_SAVE_CHANGED_FIELDS`, `HISTORY_SAVE_CHANGED_DATA` and `HISTORY_SAVE_REMOVED_DATA` all have to be true.

### Error pages settings
* `ERROR_PAGE_ADMIN_EMAIL`: You can set an email-address here, which is shown on the error page, who should be contacted about the issue (e.g. an IT support email of your company)
* `ERROR_PAGE_SHOW_HELP`: Set this 0, to disable the solution hints shown on an error page. These hints should not contain senstive informations, but could confuse end-users.

### Other / less used options
* `TRUSTED_PROXIES`: Set the IP addresses (or IP blocks) of trusted reverse proxies here. This is needed to get correct IP informations (see [here](https://symfony.com/doc/current/deployment/proxies.html) for more info).
* `TRUSTED_HOSTS`: To prevent `HTTP Host header attacks` you can set a regex containing all host names via which Part-DB should be accessible. If accessed via the wrong hostname, an error will be shown.
* `DEMO_MODE`: Set Part-DB into demo mode, which forbids users to change their passwords and settings. Used for the demo instance, should not be needed for normal installations.
* `NO_URL_REWRITE_AVAILABLE` (allowed values `true` or `false`): Set this value to true, if your webserver does not support rewrite. In this case, all URL pathes will contain index.php/, which is needed then. Normally this setting do not need to be changed.
* `FIXER_API_KEY`: If you want to automatically retrieve exchange rates for base currencies other than euros, you have configure an exchange rate provider API. [Fixer.io](https://fixer.io/) is preconfigured, and you just have to register there and set the retrieved API key in this environment variable.
* `APP_ENV`: This value should always be set to `prod` in normal use. Set it to `dev` to enable debug/development mode. (**You should not do this on a publicly accessible server, as it will leak sensitive informations!**)
* `BANNER`: You can configure the text that should be shown as the banner on the homepage. Useful especially for docker container. In all other applications you can just change the `config/banner.md` file.