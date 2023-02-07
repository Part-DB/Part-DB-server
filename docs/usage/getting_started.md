---
layout: default
title: Getting started with Part-DB
nav_order: 4
---

# Getting started

After Part-DB you should begin with customizing the settings, and setting up the basic structures.
Before starting its useful to read a bit about the [concepts of Part-DB](https://github.com/Part-DB/Part-DB-symfony/wiki/Concepts).

## Customize config files

<details>
<summary>Click to expand</summary>

Before you start creating data structures, you should configure Part-DB to your needs by changing possible configuration options.
This is done either via changing the `.env.local` file in a direct installation or by changing the env variables in your `docker-compose.yaml` file.
A list of possible configuration options, can be found [here](Configuration).

</details>

## Change password, Set up Two-Factor-Authentication & Customize User settings

<details>
<summary>Click to expand</summary>

If you have not already done, you should change your user password. You can do this in the user settings (available in the navigation bar drop down with the user symbol).

![image](https://user-images.githubusercontent.com/5410681/190141016-5539a125-3215-48bb-b61b-7c646c352d4d.png)

There you can also find the option, to set up Two Factor Authentication methods like Google Authenticator. Using this is highly recommended (especially if you have admin permissions) to increase the security of your account. (Two Factor Authentication even can be enforced for all members of a user group)

In the user settings panel you can change account infos like your username, your first and last name (which will be shown alongside your username to identify you better), department information and your email address. The email address is used to send password reset mails, if your system is configured to use this.

![image](https://user-images.githubusercontent.com/5410681/190142624-0b4d4153-33ea-46e6-baba-5723b5ba9f52.png)

In the configuration tab you can also override global settings, like your preferred UI language (which will automatically be applied after login), the timezone you are in (and in which times will be shown for you), your preferred currency (all money values will be shown converted to this to you, if possible) and the theme that should be used.
</details>

## Create groups, users and customize permissions

<details>
<summary>Click to expand</summary>

### Users

When logged in as administrator, you can open the users menu in the `Tools` section of the sidebar under `System -> Users`.
At this page you can create new users, change their passwords and settings and change their permissions.
For each user which should use Part-DB you should setup a own account, so that tracking of what user did what works properly.
![image](https://user-images.githubusercontent.com/5410681/193269065-cf0695b9-21bd-4697-87ab-1557197ef0f6.png)


You should check the permissions for every user and ensure that they are in the intended way, and no user has more permissions than he needs.
For each capability you can choose between allow, forbid and inherit. In the last case, the permission is determined by the group a user has (if no group is chosen, it equals forbid)

![image](https://user-images.githubusercontent.com/5410681/193269185-3a783628-44ca-4dcf-9629-fc6af2e39709.png)


### Anonymous user

The `anonymous` user is special, as its settings and permissions are used for everybody who is not logged in. By default the anonymous user has read capabilities for your parts. If your Part-DB instance is publicly available you maybe want to restrict the permissions.

### Groups

If you have many users which should share the same permissions, it is useful to define the permissions using user groups, which you can create and edit in the `System -> Groups` menu.

By default 3 groups are defined:
* `readonly` which users have only have read permissions (like viewing, searching parts, attachments, etc.)
* `users` which users also have rights to edit/delete/create elements
* `admin` which users can do administrative operations (like creating new users, show global system log, etc.)

Users only use the setting of a capability from a group, if the user has a group associated and the capability on the user is set to `inherit` (which is the default if creating a new user). You can override the permissions settings of a group per user by explicitly settings the permission at the user.

Groups are organized as trees, meaning a group can have parent and child permissions and child groups can inherit permissions from their parents.
To inherit the permissions from a parent group set the capability to inherit, otherwise set it explicitly to override the parents permission.

</details>

## Create Attachment types

<details>
<summary>Click to expand</summary>

Every attachment (that is an file associated with a part, data structure, etc.) must have an attachment type. They can be used to group attachments logically, like differentiating between datasheets, pictures and other documents.

You can create/edit attachment types in the tools sidebar under "Edit -> Attachment types":

![image](https://user-images.githubusercontent.com/5410681/206695965-60a15725-c690-456c-bbdb-d6af858ee75b.png)
 
Depending on your usecase different entries here make sense. For part mananagment the following (additional) entries maybe make sense:

* Datasheets (restricted to pdfs, Allowed filetypes: `application/pdf`)
* Pictures (for generic pictures of components, storage locations, etc., Allowed filetypes: `image/*`

For every attachment type a list of allowed file types, which can be uploaded to an attachment with this attachment type, can be defined. You can either pass a list of allowed file extensions (e.g. `.pdf, .zip, .docx`) and/or a list of [Mime Types](https://en.wikipedia.org/wiki/Media_type) (e.g. `application/pdf, image/jpeg`) or a combination of both here. To allow all browser supported images, you can use `image/*` wildcard here.

</details>

## (Optional) Create Currencies

<details>
<summary>Click to expand</summary>
If you want to save priceinformations for parts in a currency different to your global currency (by default Euro), you have to define the additional currencies you want to use under `Edit -> Currencies`:

![image](https://user-images.githubusercontent.com/5410681/206698733-01e22c0b-c871-438a-a98d-f09f1c03fe90.png)

You create a new currency, name it however you want (it is recommended to use the official name of the currency) and select the currency ISO code from the list and save it. The currency symbol is determined automatically from chose ISO code.
You can define a exchange rate in terms of your base currency (e.g. how much euros is one unit of your currency worth) to convert the currencies values in your preferred display currency automatically. 


</details>

## (Optional) Create Measurement Units

<details>
<summary>Click to expand</summary>

By default Part-DB assumes that the parts in inventory can be counted by individual indivisible pieces, like LEDs in a box or books in a shelf. However if you want to manage things, that are divisible and and the instock is described by a physical quantity, like length for cables, or volumina of a liquid, you have to define additional measurement units.

This is possible under `Edit -> Measurement Units`:
![image](https://user-images.githubusercontent.com/5410681/206701450-40a5f323-21ea-4889-a68b-8fb6c3d2f46d.png)

You can give the measurement unit a name and an optional unit symbol (like `m` for meters) which is shown when quantities in this unit are displayed. The option `Use SI prefix` is useful for almost all physical quantities, as big and small numbers are automatically formatted with SI-prefixes (like 1.5kg instead 1500 grams).

The measurement unit can be selected for each part individually, by setting the option in the advanced tab of a part`s edit menu.

</details>

## (Optional) Customize homepage banner

<details>
<summary>Click to expand</summary>
The banner which is shown on the homepage, can be customized/changed by changing the `config/banner.md` file with a text editor. You can use markdown and (safe) HTML here, to style and customize the banner.
You can even use Latex style equations by wrapping the expressions into `$` (like `$E=mc^2$`, which is rendered inline: $E=mc^2$) or `$$` (like `$$E=mc^2$$`) which will be rendered as a block, like so: $$E=mc^2$$
</details>