---
title: Tips & Tricks
layout: default
parent: Usage
---

# Tips & Tricks

Following you can find miscellaneous tips and tricks for using Part-DB.

## Create data structures directly from part edit page

Instead of first creating a category, manufacturer, footprint, etc., and then creating the part, you can create the
data structures directly from the part edit page: Just type the name of the data structure you want to create into the
select field on the part edit page and press "Create new ...". The new data structure will be created when you save
the part changes.

You can create also create nested data structures this way. For example, if you want to create a new category "AVRs",
as a subcategory of "MCUs", you can just type "MCUs->AVRs" into the category select field and press "Create new".
The new category "AVRs" will be created as a subcategory of "MCUs". If the category "MCUs" does not exist, it will
be created too.

## Built-in footprint images

Part-DB includes several built-in images for common footprints. You can use these images in your footprint
data structures,
by creating an attachment on the data structure and selecting it as the preview image.
Type the name of the footprint image you want to use into the URL field of the attachment and select it from the
dropdown menu. You can find a gallery of all builtin footprint images and their names in the "Builtin footprint image
gallery",
which you can find in the "Tools" menu (you may need to give your user the permission to access this tool).

## Parametric search

In the "parameters" tab of the filter panel on parts list page, you can define constraints, and which parameter values
have to fulfill. This allows you to search for parts with specific parameters (or parameter ranges), for example, you
can search for all parts with a voltage rating of greater than 5 V.

## View own user's permissions

If you want to see which permissions your user has, you can find a list of the permissions in the "Permissions" panel
on the user info page.

## Use LaTeX equations

You can use LaTeX equations everywhere where markdown is supported (for example in the description or notes field of a
part).
[KaTeX](https://katex.org/) is used to render the equations.
You can find a list of supported features in the [KaTeX documentation](https://katex.org/docs/supported.html).

To input a LaTeX equation, you have to wrap it in a pair of dollar signs (`$`). Single dollar signs mark inline
equations, double dollar signs mark displayed equations (which will be their own line and centered). 
For example, the following equation will be rendered as an inline equation:

```
$E=mc^2$
```

while this one will be rendered as a displayed equation:

```
$$E=mc^2$$
```

## Update currency exchange rates automatically

Part-DB can update the currency exchange rates of all defined currencies programmatically
by calling the `php bin/console partdb:currencies:update-exchange-rates`.

If you call this command regularly (e.g. with a cronjob), you can keep the exchange rates up-to-date.

Please note that if you use a base currency, which is not the Euro, you have to configure an exchange rate API, as the
free API used by default only supports the Euro as base currency.

## Enforce log comments

On almost any editing operation it is possible to add a comment describing, what or why you changed something.
This comment will be written to changelog and can be viewed later.
If you want to force your users to add comments to certain operations, you can do this by setting
the `ENFORCE_CHANGE_COMMENTS_FOR` option.
See the configuration reference for more information.

## Personal stocks and stock locations

For maker spaces and universities with a lot of users, where each user can have his own stock, which only he should be
able to access, you can assign
the user as "owner" of a part lot. This way, only he is allowed to add or remove parts from this lot.

## Update notifications

Part-DB can show you a notification that there is a newer version than currently installed available. The notification
will be shown on the homepage and the server info page.
It is only be shown to users which has the `Show available Part-DB updates` permission.

For the notification to work, Part-DB queries the GitHub API every 2 days to check for new releases. No data is sent to
GitHub besides the metadata required for the connection (so the public IP address of your computer running Part-DB).
If you don't want Part-DB to query the GitHub API, or if your server can not reach the internet, you can disable the
update notifications by setting the `CHECK_FOR_UPDATES` option to `false`.