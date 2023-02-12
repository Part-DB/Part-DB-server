---
layout: default
title: Getting started with Part-DB
nav_order: 4
---

# Getting started

After Part-DB you should begin with customizing the settings, and setting up the basic structures.
Before starting its useful to read a bit about the [concepts of Part-DB]({% link concepts.md %}).

1. TOC
{:toc}

## Customize config files

Before you start creating data structures, you should configure Part-DB to your needs by changing possible configuration options.
This is done either via changing the `.env.local` file in a direct installation or by changing the env variables in your `docker-compose.yaml` file.
A list of possible configuration options, can be found [here]({% link configuration.md %}).

## Change password, Set up Two-Factor-Authentication & Customize User settings

If you have not already done, you should change your user password. You can do this in the user settings (available in the navigation bar drop down with the user symbol).

![image]({% link assets/getting_started/change_password.png %})

There you can also find the option, to set up Two Factor Authentication methods like Google Authenticator. Using this is highly recommended (especially if you have admin permissions) to increase the security of your account. (Two Factor Authentication even can be enforced for all members of a user group)

In the user settings panel you can change account infos like your username, your first and last name (which will be shown alongside your username to identify you better), department information and your email address. The email address is used to send password reset mails, if your system is configured to use this.

![image]({% link assets/getting_started/user_settings.png %})

In the configuration tab you can also override global settings, like your preferred UI language (which will automatically be applied after login), the timezone you are in (and in which times will be shown for you), your preferred currency (all money values will be shown converted to this to you, if possible) and the theme that should be used.

## (Optional) Customize homepage banner

The banner which is shown on the homepage, can be customized/changed by changing the `config/banner.md` file with a text editor. You can use markdown and (safe) HTML here, to style and customize the banner.
You can even use Latex style equations by wrapping the expressions into `$` (like `$E=mc^2$`, which is rendered inline: $E=mc^2$) or `$$` (like `$$E=mc^2$$`) which will be rendered as a block, like so: $$E=mc^2$$

## Create groups, users and customize permissions

### Users

When logged in as administrator, you can open the users menu in the `Tools` section of the sidebar under `System -> Users`.
At this page you can create new users, change their passwords and settings and change their permissions.
For each user which should use Part-DB you should setup a own account, so that tracking of what user did what works properly.
![image]({% link assets/getting_started/user_admin.png %})


You should check the permissions for every user and ensure that they are in the intended way, and no user has more permissions than he needs.
For each capability you can choose between allow, forbid and inherit. In the last case, the permission is determined by the group a user has (if no group is chosen, it equals forbid)

![image]({% link assets/getting_started/user_permissions.png %})


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


## Create Attachment types

Every attachment (that is an file associated with a part, data structure, etc.) must have an attachment type. They can be used to group attachments logically, like differentiating between datasheets, pictures and other documents.

You can create/edit attachment types in the tools sidebar under "Edit -> Attachment types":

![image]({% link assets/getting_started/attachment_type_admin.png %})
 
Depending on your usecase different entries here make sense. For part mananagment the following (additional) entries maybe make sense:

* Datasheets (restricted to pdfs, Allowed filetypes: `application/pdf`)
* Pictures (for generic pictures of components, storage locations, etc., Allowed filetypes: `image/*`

For every attachment type a list of allowed file types, which can be uploaded to an attachment with this attachment type, can be defined. You can either pass a list of allowed file extensions (e.g. `.pdf, .zip, .docx`) and/or a list of [Mime Types](https://en.wikipedia.org/wiki/Media_type) (e.g. `application/pdf, image/jpeg`) or a combination of both here. To allow all browser supported images, you can use `image/*` wildcard here.

## (Optional) Create Currencies

If you want to save priceinformations for parts in a currency different to your global currency (by default Euro), you have to define the additional currencies you want to use under `Edit -> Currencies`:

![image]({% link assets/getting_started/currencies_admin.png %})

You create a new currency, name it however you want (it is recommended to use the official name of the currency) and select the currency ISO code from the list and save it. The currency symbol is determined automatically from chose ISO code.
You can define a exchange rate in terms of your base currency (e.g. how much euros is one unit of your currency worth) to convert the currencies values in your preferred display currency automatically. 


## (Optional) Create Measurement Units

By default Part-DB assumes that the parts in inventory can be counted by individual indivisible pieces, like LEDs in a box or books in a shelf. 
However if you want to manage things, that are divisible and and the instock is described by a physical quantity, like length for cables, or volumina of a liquid, you have to define additional measurement units.

This is possible under `Edit -> Measurement Units`:
![image]({% link assets/getting_started/units_admin.png %})

You can give the measurement unit a name and an optional unit symbol (like `m` for meters) which is shown when quantities in this unit are displayed. The option `Use SI prefix` is useful for almost all physical quantities, as big and small numbers are automatically formatted with SI-prefixes (like 1.5kg instead 1500 grams).

The measurement unit can be selected for each part individually, by setting the option in the advanced tab of a part`s edit menu.

## Create Categories

A category is used to group parts logically by their function (e.g. all NPN transistors would be put in a "NPN-Transistors" category). 
Categories are hierarchical structures meaning that you can create logical trees to group categories together.
See [Concepts]({% link concepts.md %}) for an example tree structure.

Creating/Editing categories is done in the tools sidebar under "Edit -> Categories".

Every part has to be assigned to a category, so you should create at least one category before you start adding parts.

## (Optional) Create Footprints

Footprints are used to describe the physical shape of a part, like a resistor or a capacitor. 
They can be used to group parts by their physical shape and to find parts with in the same package.

You can create/edit footprints in the tools sidebar under "Edit -> Footprints".

It is useful to create footprints for the most common packages, like SMD resistors, capacitors, etc. to make it easier to find parts with the same footprint.
You should create these as a tree structure, so that you can group footprints by their type.
See [Concepts]({% link concepts.md %}) for an example tree structure.

You can define attachments here which are associated with the footprint. The attachment set as preview image, will be
used whenever a visual representation of the footprint is needed (e.g. in the part list).

For many common footprints, you can use the built-in footprints, which can be found in the "Builtin footprint image gallery", which you can find in the tools menu.
Type the name of the image you want to use in the URL field of the attachment and select the image from the dropdown menu.

## (Optional) Create Storage locations

A storelocation represents a place where parts can be stored.

You can create/edit storage locations in the tools sidebar under "Edit -> Storage locations".

## (Optional) Create Manufacturers and suppliers

You can create/edit [manufacturers]({% link concepts.md %}#manufacturers) and [suppliers]({% link concepts.md %}#suppliers) in the tools sidebar under "Edit -> Manufacturers" and "Edit -> Suppliers".

## Create parts

You are now ready to create your first part. You can do this by clicking either by clicking "Edit -> New Part" in the tools sidebar tree
or by clicking the "Create new Part" above the (empty) part list, after clicking on one of your newly created categories.

You will be presented with a form where you can enter the basic information about your part:

![image]({% link assets/getting_started/new_part.png %})

You have to enter at least a name for the part and choose a category for it, the other fields are optional.
However, it is recommended to fill out as much information as possible, as this will make it easier to find the part later.

You can choose from your created datastructures to add manufacturer information, supplier information, etc. to the part.
You can also create new datastructures on the fly, if you want to add additional information to the part, by typing the
name of the new datastructure in the field and select the "New ..." option in the dropdown menu. See [tips]({% link usage/tips_tricks.md %}) for more information.