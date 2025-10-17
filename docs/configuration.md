---
layout: default
title: Configuration
nav_order: 5
---

# Configuration

Part-DBs behavior can be configured to your needs. There are different kinds of configuration options: Options, which are
user-changeable (changeable dynamically via frontend), options that can be configured by environment variables, and
options that are only configurable via Symfony config files.

## User configruation

The following things can be changed for every user and a user can change it for himself (if he has the correct permission
for it). Configuration is either possible via the user's own settings page (where you can also change the password) or via
the user admin page:

* **Language**: The language that the users prefer, and which will be used when no language is explicitly specified.
  Language can still always be changed via the language selector. By default, the globally configured language is used.
* **Timezone**: The timezone in which the user resides and in which all dates and times should be shown. By default, the
  globally configured language.
* **Theme**: The theme to use for the front end. Allows the user to choose the front end design, he prefers.
* **Preferred currency**: One of the defined currencies, in which all prices should be shown, if possible. Prices with
  other currencies will be converted to the price selected here

## System configuration (via web interface)

Many common configuration options can be changed via the web interface. You can find the settings page in the sidebar under
"System" -> "Settings". You need to have the "Change system settings" permission to access this page.

If a setting is greyed out and cannot be changed, it means that this setting is currently overwritten by an environment
variable. You can either change the environment variable to change the setting, or you can migrate the setting to the
database, so that it can be changed via the web interface. To do this, you can use the `php bin/console settings:migrate-env-to-settings` command 
and remove the environment variable afterward.

## Environment variables (.env.local)

The following configuration options can only be changed by the server administrator, by either changing the server
variables, changing the `.env.local` file or setting env for your docker container. Here are just the most important
options listed, see `.env` file for the full list of possible env variables.

Environment variables allow to overwrite settings in the web interface. This is useful, if you want to enforce certain
settings to be unchangable by users, or if you want to configure settings in a central place in a deployed environment.
On the settings page, you can hover over a setting to see, which environment variable can be used to overwrite it, it 
is shown as tooltip. API keys or similar sensitve data which is overwritten by env variables, are redacted on the web
interface, so that even administrators cannot see them (only the last 2 characters and the length).

For technical and security reasons some settings can only be configured via environment variables and not via the web
interface. These settings are marked with "(env only)" in the description below.

### General options

* `DATABASE_URL` (env only): Configures the database which Part-DB uses:
   * For MySQL (or MariaDB) use a string in the form of `mysql://<USERNAME>:<PASSWORD>@<HOST>:<PORT>/<TABLE_NAME>` here
  (e.g. `DATABASE_URL=mysql://user:password@127.0.0.1:3306/part-db`).
   * For SQLite use the following format to specify the
  absolute path where it should be located `sqlite:///path/part/app.db`. You can use `%kernel.project_dir%` as
  placeholder for the Part-DB root folder (e.g. `sqlite:///%kernel.project_dir%/var/app.db`)
   * For Postgresql use a string in the form of `DATABASE_URL=postgresql://user:password@127.0.0.1:5432/part-db?serverVersion=x.y`.

     Please note that **`serverVersion=x.y`** variable is required due to dependency of Symfony framework.

* `DATABASE_MYSQL_USE_SSL_CA` (env only): If this value is set to `1` or `true` and a MySQL connection is used, then the connection
 is encrypted by SSL/TLS and the server certificate is verified against the system CA certificates or the CA certificate
bundled with Part-DB. Set `DATABASE_MYSQL_SSL_VERIFY_CERT` if you want to accept all certificates.
* `DATABASE_EMULATE_NATURAL_SORT` (default 0) (env only): If set to 1, Part-DB will emulate natural sorting, even if the database 
  does not support it natively. However this is much slower than the native sorting, and contain bugs or quirks, so use
  it only, if you have to.
* `DEFAULT_LANG`: The default language to use server-wide (when no language is explicitly specified by a user or via
  language chooser). Must be something like `en`, `de`, `fr`, etc.
* `DEFAULT_TIMEZONE`: The default timezone to use globally, when a user has no timezone specified. Must be something
  like `Europe/Berlin`. See [here](https://en.wikipedia.org/wiki/List_of_tz_database_time_zones) under TZ Database name
  for a list of available options.
* `BASE_CURRENCY`: The currency to use internally for monetary values and when no currency is explicitly specified. When
  migrating from a legacy Part-DB version, this should be the same as the currency in the old Part-DB instance (normally
  euro). This should be the currency you use the most. **Please note that you can not really change this setting after
  you have created data**. The value has to be a valid [ISO4217](https://en.wikipedia.org/wiki/ISO_4217) code,
  like `EUR` or `USD`.
* `INSTANCE_NAME`: The name of your installation. It will be shown as a title in the navbar and other places. By
  default `Part-DB`, but you can customize it to something likes `ExampleCorp. Inventory`.
* `ALLOW_ATTACHMENT_DOWNLOADS` (allowed values `0` or `1`): By setting this option to 1, users can make Part-DB directly
  download a file specified as a URL and create it as a local file. Please note that this allows users access to all
  resources publicly available to the server (so full access to other servers in the same local network), which could
  be a security risk.
* `ATTACHMENT_DOWNLOAD_BY_DEFAULT`: When this is set to 1, the "download external file" checkbox is checked by default
  when adding a new attachment. Otherwise, it is unchecked by default. Use this if you wanna download all attachments
  locally by default. Attachment download is only possible, when `ALLOW_ATTACHMENT_DOWNLOADS` is set to 1.
* `USE_GRAVATAR`: Set to `1` to use [gravatar.com](https://gravatar.com/) images for user avatars (as long as they have
  not set their own picture). The users browsers have to download the pictures from a third-party (gravatar) server, so
  this might be a privacy risk.
* `MAX_ATTACHMENT_FILE_SIZE`: The maximum file size (in bytes) for attachments. You can use the suffix `K`, `M` or `G`
  to specify the size in kilobytes, megabytes or gigabytes. By default `100M` (100 megabytes). Please note that this is
  only the limit of Part-DB. You still need to configure the php.ini `upload_max_filesize` and `post_max_size` to allow
  bigger files to be uploaded.
* `DEFAULT_URI` (env only): The default URI base to use for the Part-DB, when no URL can be determined from the browser request.
  This should be the primary URL/Domain, which is used to access Part-DB. This value is used to create correct links in
  emails and other places, where the URL is needed. It is also used, when SAML is enabled.s If you are using a reverse
  proxy, you should set this to the URL of the reverse proxy (e.g. `https://part-db.example.com`). **This value must end
  with a slash**.
* `ENFORCE_CHANGE_COMMENTS_FOR`: With this option, you can configure, where users are enforced to give a change reason,
  which will be written to the log. This is a comma-separated list of values (e.g. `part_edit,part_delete`). Leave empty
  to make change comments optional everywhere. Possible values are:
    * `part_edit`: Edit operation of an existing part
    * `part_delete`: Delete operation of an existing part
    * `part_create`: Creation of a new part
    * `part_stock_operation`: Stock operation on a part (therefore withdraw, add or move stock)
    * `datastructure_edit`: Edit operation of an existing datastructure (e.g. category, manufacturer, ...)
    * `datastructure_delete`: Delete operation of a existing datastructure (e.g. category, manufacturer, ...)
    * `datastructure_create`: Creation of a new datastructure (e.g. category, manufacturer, ...)
* `CHECK_FOR_UPDATES` (default `1`): Set this to 0, if you do not want Part-DB to connect to GitHub to check for new
  versions, or if your server can not connect to the internet.
* `APP_SECRET` (env only): This variable is a configuration parameter used for various security-related purposes,
  particularly for securing and protecting various aspects of your application. It's a secret key that is used for
  cryptographic operations and security measures (session management, CSRF protection, etc..). Therefore this
  value should be handled as confidential data and not shared publicly.
* `SHOW_PART_IMAGE_OVERLAY`: Set to 0 to disable the part image overlay, which appears if you hover over an image in the
  part image gallery

### E-Mail settings (all env only)

* `MAILER_DSN`: You can configure the mail provider which should be used for email delivery (
  see https://symfony.com/doc/current/components/mailer.html for full documentation). If you just want to use an SMTP
  mail account, you can use the following syntax `MAILER_DSN=smtp://user:password@smtp.mailserver.invalid:587`
* `EMAIL_SENDER_EMAIL`: The email address from which emails should be sent from (in most cases this has to be the same
  as the email address used for SMTP access)
* `EMAIL_SENDER_NAME`: Similar to `EMAIL_SENDER_EMAIL`, but this allows you to specify the name from which the mails are
  sent from.
* `ALLOW_EMAIL_PW_RESET`: Set this value to true, if you want to allow users to reset their password via an email
  notification. You have to configure the mail provider first before via the MAILER_DSN setting.

### Table related settings

* `TABLE_DEFAULT_PAGE_SIZE`: The default page size for tables. This is the number of rows which are shown per page. Set
  to `-1` to disable pagination and show all rows at once.
* `TABLE_PARTS_DEFAULT_COLUMNS`: The columns in parts tables, which are visible by default (when loading table for first
  time).
  Also specify the default order of the columns. This is a comma separated list of column names. Available columns
  are: `name`, `id`, `ipn`, `description`, `category`, `footprint`, `manufacturer`, `storage_location`, `amount`, `minamount`, `partUnit`, `addedDate`, `lastModified`, `needs_review`, `favorite`, `manufacturing_status`, `manufacturer_product_number`, `mass`, `tags`, `attachments`, `edit`.
* `TABLE_ASSEMBLIES_DEFAULT_COLUMNS`: The columns in assemblies tables, which are visible by default (when loading table for first time).
    Also specify the default order of the columns. This is a comma separated list of column names. Available columns
    are: `name`, `id`, `ipn`, `description`, `referencedAssemblies`, `edit`, `addedDate`, `lastModified`.
* `TABLE_ASSEMBLIES_BOM_DEFAULT_COLUMNS`: The columns in assemblies bom tables, which are visible by default (when loading table for first time).
    Also specify the default order of the columns. This is a comma separated list of column names. Available columns
    are: `quantity`, `name`, `id`, `ipn`, `description`, `category`, `footprint`, `manufacturer`, `designator`, `mountnames`, `storage_location`, `amount`, `addedDate`, `lastModified`.
* `CREATE_ASSEMBLY_USE_IPN_PLACEHOLDER_IN_NAME`: Use an %%ipn%% placeholder in the name of a assembly. Placeholder is replaced with the ipn input while saving.

### History/Eventlog-related settings

The following options are used to configure, which (and how much) data is written to the system log:

* `HISTORY_SAVE_CHANGED_FIELDS`: When this option is set to true, the name of the fields that are changed, are saved to
  the DB (so for example it is logged that a user has changed, that the user has changed the name and description of the
  field, but not the data/content of these changes)
* `HISTORY_SAVE_CHANGED_DATA`: When this option is set to true, the changed data is saved to log (so it is logged, that
  a user has changed the name of a part and what the name was before). This can increase database size when you have a
  lot of changes to entities.
* `HISTORY_SAVE_REMOVED_DATA`: When this option is set to true, removed data is saved to log, meaning that you can
  easily undelete an entity, when it was removed accidentally.
* `HISTORY_SAVE_NEW_DATA`: When this option is set to true, the new data (the data after a change) is saved to element
  changed log entries. This allows you to easily see the changes between two revisions of an entity. This can increase
  database size, when you have a lot of changes to entities.

If you want to use want to revert changes or view older revisions of entities,
then `HISTORY_SAVE_CHANGED_FIELDS`, `HISTORY_SAVE_CHANGED_DATA` and `HISTORY_SAVE_REMOVED_DATA` all have to be true.

### Error pages settings (all env only)

* `ERROR_PAGE_ADMIN_EMAIL`: You can set an email address here, which is shown on the error page, who should be contacted
  about the issue (e.g. an IT support email of your company)
* `ERROR_PAGE_SHOW_HELP`: Set this 0, to disable the solution hints shown on an error page. These hints should not
  contain sensitive information but could confuse end-users.

### EDA related settings

* `EDA_KICAD_CATEGORY_DEPTH`: A number, which determines how many levels of Part-DB categories should be shown inside KiCad.
   All parts in the selected category and all subcategories are shown in KiCad.
   For performance reason this value should not be too high. The default is 0, which means that only the top level categories are shown in KiCad.
   All parts in the selected category and all subcategories are shown in KiCad. Set this to a higher value, if you want to show more categories in KiCad.
   When you set this value to -1, all parts are shown inside a single category in KiCad.

### SAML SSO settings (all env only)

The following settings can be used to enable and configure Single-Sign on via SAML. This allows users to log in to
Part-DB without entering a username and password, but instead they are redirected to a SAML Identity Provider (IdP) and
are logged in automatically. This is especially useful when you want to use Part-DB in a company, where all users have
a SAML account (e.g. via Active Directory or LDAP).
You can find more advanced settings in the `config/packages/hslavich_onelogin_saml.yaml` file. Please note that this
file is not backed up by the backup script, so you have to back up it manually, if you want to keep your changes. If you
want to edit it on docker, you have to map the file to a volume.

* `SAML_ENABLED`: When this is set to 1, SAML SSO is enabled and the SSO Login button is shown in the login form. You
  have to configure the SAML settings below before you can use this feature.
* `SAML_BEHIND_PROXY`: Set this to 1, if Part-DB is behind a reverse proxy. See [here]({% link installation/reverse-proxy.md %})
  for more information. Otherwise, leave it to 0 (default.)
* `SAML_ROLE_MAPPING`: A [JSON](https://en.wikipedia.org/wiki/JSON)-encoded map which specifies how Part-DB should
  convert the user roles given by SAML attribute `group` should be converted to a Part-DB group (specified by ID). You
  can use a wildcard `*` to map all otherwise unmapped roles to a certain group.
  Example: `{"*": 1, "admin": 2, "editor": 3}`. This would map all roles to the group with ID 1, except the
  role `admin`, which is mapped to the group with ID 2, and the role `editor`, which is mapped to the group with ID 3.
* `SAML_UPDATE_GROUP_ON_LOGIN`: When this is enabled the group of the user is updated on every login of the user based
  on the SAML role attributes. When this is disabled, the group is only assigned on the first login of the user, and a
  Part-DB administrator can change the group afterward by editing the user.
* `SAML_IDP_ENTITY_ID`: The entity ID of your SAML Identity Provider (IdP). You can find this value in the metadata XML
  file or configuration UI of your IdP.
* `SAML_IDP_SINGLE_SIGN_ON_SERVICE`: The URL of the SAML IdP Single Sign-On Service (SSO). You can find this value in
  the metadata XML file or configuration UI of your IdP.
* `SAML_IDP_SINGLE_LOGOUT_SERVICE`: The URL of the SAML IdP Single Logout Service (SLO). You can find this value in the
  metadata XML file or configuration UI of your IdP.
* `SAML_IDP_X509_CERT`: The base64 encoded X.509 public certificate of your SAML IdP. You can find this value in the
  metadata XML file or configuration UI of your IdP. It should start with `MIIC` and end with `=`.
* `SAML_SP_ENTITY_ID`: The entity ID of your SAML Service Provider (SP). This is the value you have configured for the
  Part-DB client in your IdP.
* `SAML_SP_X509_CERT`: The public X.509 certificate of your SAML SP (here Part-DB). This is the value you have
  configured for the Part-DB client in your IdP. It should start with `MIIC` and end with `=`. IdPs like keycloak allows
  you to generate a public/private key pair for the client which you can set up here and in the `SAML_SP_PRIVATE_KEY`
  setting.
* `SAML_SP_PRIVATE_KEY`: The private key of your SAML SP (here Part-DB), corresponding the public key specified
  in `SAML_SP_X509_CERT`. This is the value you have configured for the Part-DB client in your IdP. It should start
  with `MIIE` and end with `=`. IdPs like keycloak allows you to generate a public/private key pair for the client which
  you can set up here and in the `SAML_SP_X509_CERT` setting.

### Information provider settings

The settings prefixes with `PROVIDER_*` are used to configure the information providers.
See the [information providers]({% link usage/information_provider_system.md %}) page for more information.

### Other / less-used options

* `TRUSTED_PROXIES` (env only): Set the IP addresses (or IP blocks) of trusted reverse proxies here. This is needed to get correct
  IP information (see [here](https://symfony.com/doc/current/deployment/proxies.html) for more info).
* `TRUSTED_HOSTS` (env only): To prevent `HTTP Host header attacks` you can set a regex containing all host names via which Part-DB
  should be accessible. If accessed via the wrong hostname, an error will be shown.
* `DEMO_MODE` (env only): Set Part-DB into demo mode, which forbids users to change their passwords and settings. Used for the demo
  instance. This should not be needed for normal installations.
* `NO_URL_REWRITE_AVAILABLE` (allowed values `true` or `false`) (env only): Set this value to true, if your webserver does not
  support rewrite. In this case, all URL paths will contain index.php/, which is needed then. Normally this setting does
  not need to be changed.
* `REDIRECT_TO_HTTPS` (env only): If this is set to true, all requests to http will be redirected to https. This is useful if your
  web server does not already do this (like the one used in the demo instance). If your web server already redirects to
  https, you don't need to set this. Ensure that Part-DB is accessible via HTTPS before you enable this setting.
* `FIXER_API_KEY`: If you want to automatically retrieve exchange rates for base currencies other than euros, you have to
  configure an exchange rate provider API. [Fixer.io](https://fixer.io/) is preconfigured, and you just have to register
  there and set the retrieved API key in this environment variable.
* `APP_ENV` (env only): This value should always be set to `prod` in normal use. Set it to `dev` to enable debug/development
  mode. (**You should not do this on a publicly accessible server, as it will leak sensitive information!**)
* `BANNER`: You can configure the text that should be shown as the banner on the homepage. Useful especially for docker
  containers. In all other applications you can just change the `config/banner.md` file.
* `DISABLE_YEAR2038_BUG_CHECK` (env only): If set to `1`, the year 2038 bug check is disabled on 32-bit systems, and dates after
2038 are no longer forbidden. However this will lead to 500 error messages when rendering dates after 2038 as all current
32-bit PHP versions can not format these dates correctly. This setting is for the case that future PHP versions will
handle this correctly on 32-bit systems. 64-bit systems are not affected by this bug, and the check is always disabled.

## Banner

To change the banner you can find on the homepage, you can either set the `BANNER` environment variable to the text you
want to show, or change it in the system settings webinterface. The banner is written in markdown, so you can use all
markdown (and even some subset of HTML) syntax to format the text.

## parameters.yaml

You can also configure some options via the `config/parameters.yaml` file. This should normally not need,
and you should know what you are doing, when you change something here. You should expect, that you will have to do some
manual merge, when you have changed something here and update to a newer version of Part-DB. It is possible that
configuration options here will change or be  completely removed in future versions of Part-DB.

If you change something here, you have to clear the cache, before the changes will take effect with the
command `bin/console cache:clear`.

The following options are available:

* `partdb.locale_menu`: The codes of the languages, which should be shown in the language chooser menu (the one with the
  user icon in the navbar). The first language in the list will be the default language.
* `partdb.gdpr_compliance`: When set to true (default value), IP addresses which are saved in the database will be
  anonymized, by removing the last byte of the IP. This is required by the GDPR (General Data Protection Regulation) in
  the EU.
* `partdb.sidebar.items`: The panel contents which should be shown in the sidebar by default. You can also change the
  number of sidebar panels by changing the number of items in this list.
* `partdb.sidebar.root_node_enable`: Show a root node in the sidebar trees, of which all nodes are children of
* `partdb.sidebar.root_expanded`: Expand the root node in the sidebar trees by default
* `partdb.available_themes`: The list of available themes a user can choose from.
