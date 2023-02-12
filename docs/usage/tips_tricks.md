---
title: Tips & Tricks
layout: default
parent: Usage
---

# Tips & Tricks

Following you can find miscellaneous tips and tricks for using Part-DB.

## Create datastructures directly from part edit page

Instead of first creating a category, manufacturer, footprint, etc. and then creating the part, you can create the 
datastructures directly from the part edit page: Just type the name of the datastructure you want to create into the 
select field on the part edit page and press "Create new ...". The new datastructure will be created, when you save
the part changes.

You can create also create nested datastructures this way. For example, if you want to create a new category "AVRs", 
as a subcategory of "MCUs", you can just type "MCUs->AVRs" into the category select field and press "Create new".
The new category "AVRs" will be created as a subcategory of "MCUs". If the category "MCUs" does not exist, it will
be created too.

## Builtin footprint images
Part-DB includes several builtin images for common footprints. You can use these images in your footprint datastructures,
by creating an attachment on the datastructure and selecting it as preview image.
Type the name of the footprint image you want to use into the URL field of the attachment and select it from the
dropdown menu. You can find a gallery of all builtin footprint images and their names in the "Builtin footprint image gallery",
which you can find in the "Tools" menu (you maybe need to give your user the permission to access this tool).

## Parametric search
In the "parameters" tab of the filter panel on parts list page, you can define constraints, which parameter values
have to fullfill. This allows you to search for parts with specific parameters (or parameter ranges), for example you
can search for all parts with a voltage rating of greater than 5 V.

## View own users permissions
If you want to see which permissions your user has, you can find a list of the permissions in the "Permissions" panel
on the user info page.

## Use LaTeX equations
You can use LaTeX equations everywhere where markdown is supported (for example in the description or notes field of a part).
[KaTeX](https://katex.org/) is used to render the equations.
You can find a list of supported features in the [KaTeX documentation](https://katex.org/docs/supported.html).

To input a LaTeX equation, you have to wrap it in a pair of dollar signs (`$`). Single dollar signs mark inline equations,
double dollar signs mark displayed equations (which will be its own line and centered). For example, the following equation
will be rendered as an inline equation:

```
$E=mc^2$
```

while this one will be rendered as a displayed equation:

```
$$E=mc^2$$
```

## Update currency exchange rates automatically
Part-DB can update the currency exchange rates of all defined currencies programatically
by calling the `php bin/console partdb:currencies:update-exchange-rates`.

If you call this command regularly (e.g. with a cronjob), you can keep the exchange rates up-to-date.

Please note that if you use a base currency, which is not the Euro, you have to configure an exchange rate API, as the
free API used by default only supports the Euro as base currency.