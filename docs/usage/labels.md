---
title: Labels
layout: default
parent: Usage
---

# Labels

Part-DB support the generation and printing of labels for parts, part lots and storage locations.
You can use the "Tools -> Label generator" menu entry to create labels or click the label generation link on the part.

You can define label templates by creating Label profiles. This way you can create many similar-looking labels with for
many parts.

The content of the labels is defined by the template's content field. You can use the WYSIWYG editor to create and style
the content (or write HTML code).
Using the "Label placeholder" menu in the editor, you can insert placeholders for the data of the parts.
It will be replaced by the concrete data when the label is generated.

## Label placeholders

A placeholder has the format `[[PLACEHOLDER]]` and will be filled with the concrete data by Part-DB.
You can use the "Placeholders" dropdown in the content editor, to automatically insert the placeholders.

### Common

| Placeholder         | Description                                                        | Example                 |
|---------------------|--------------------------------------------------------------------|-------------------------|
| `[[USERNAME]]`      | The user name of the currently logged in user                      | admin                   |
| `[[USERNAME_FULL]]` | The full name of the current user                                  | John Doe (@admin)       |
| `[[DATETIME]]`      | The current date and time in the selected locale                   | 31.12.2017, 18:34:11    |
| `[[DATE]]`          | The current date in the selected locale                            | 31.12.2017              |
| `[[TIME]]`          | The current time in the selected locale                            | 18:34:11                |
| `[[INSTALL_NAME]]`  | The name of the current installation (see $config['partdb_title']) | Part-DB                 |
| `[[INSTANCE_URL]]`  | The URL of the current installation                                | https://demo.part-db.de | 

### Parts

| Placeholder             | Description                                     | Example                     |
|-------------------------|-------------------------------------------------|-----------------------------|
| `[[ID]]`                | The internal ID of the part                     | 24                          |
| `[[NAME]]`              | The name of the part                            | ATMega328                   |
| `[[CATEGORY]]`          | The name of the category (without path)         | AVRs                        |
| `[[CATEGORY_FULL]]`     | The full path of the category                   | Aktiv->MCUs->AVRs           |
| `[[MANUFACTURER]]`      | The name of the manufacturer                    | Atmel                       |
| `[[MANUFACTURER_FULL]]` | The full path of the manufacturer               | Halbleiterhersteller->Atmel |
| `[[FOOTPRINT]]`         | The name of the footprint (without path)        | DIP-32                      |
| `[[FOOTPRINT_FULL]]`    | The full path of the footprint                  | Bedrahtet->DIP->DIP-32      |
| `[[MASS]]`              | The mass of the part                            | 123.4 g                     |
| `[[MPN]]`               | The manufacturer product number                 | BC547ACT                    |
| `[[TAGS]]`              | The tags of the part                            | SMD, Tag1                   |
| `[[M_STATUS]]`          | The manufacturing status of the part            | Active                      |
| `[[DESCRIPTION]]`       | The rich text description of the part           | *NPN*                       |
| `[[DESCRIPTION_T]]`     | The description as plain text                   | NPN                         |
| `[[COMMENT]]`           | The rich text comment of the part               |                             |
| `[[COMMENT_T]]`         | The comment as plain text                       |                             |
| `[[LAST_MODIFIED]]`     | The datetime when the element was last modified | 2/26/16, 5:38 PM            |
| `[[CREATION_DATE]]`     | The datetime when the element was created       | 2/26/16, 5:38 PM            |

### Part lot

| Placeholder           | Description                           | Example                |
|-----------------------|---------------------------------------|------------------------|
| `[[LOT_ID]]`          | Part lot ID                           | 123                    |
| `[[LOT_NAME]]`        | Part lot name                         |                        |
| `[[LOT_COMMENT]]`     | Part lot comment                      |                        |
| `[[EXPIRATION_DATE]]` | Expiration date of the part lot       |                        |
| `[[AMOUNT]]`          | The amount of parts in this lot       | 12                     |
| `[[LOCATION]]`        | The storage location of this part lot | Location A             |
| `[[LOCATION_FULL]]`   | The full path of the storage location | Location -> Location A |

### Storelocation

| Placeholder            | Description                                     | Example                |
|------------------------|-------------------------------------------------|------------------------|
| `[[ID]]`               | ID of the storage location                      |                        |
| `[[NAME]]`             | Name of the storage location                    | Location A             |
| `[[FULL_PATH]]`        | The full path of the storage location           | Location -> Location A |
| `[[PARENT]]`           | The name of the parent location                 | Location               |
| `[[PARENT_FULL_PATH]]` | The full path of the storage location           |                        |
| `[[COMMENT]]`          | The comment of the storage location             |                        |
| `[[COMMENT_T]]`        | The plain text version of the comment           |
| `[[LAST_MODIFIED]]`    | The datetime when the element was last modified | 2/26/16, 5:38 PM       |
| `[[CREATION_DATE]]`    | The datetime when the element was created       | 2/26/16, 5:38 PM       |

## Twig mode

If you select "Twig" in parser mode under advanced settings, you can input a twig template in the lines field (activate
source mode). You can use most of the twig tags and filters listed
in [official documentation](https://twig.symfony.com/doc/3.x/).

Twig allows you for much more complex and dynamic label generation. You can use loops, conditions, and functions to create
the label content and you can access almost all data Part-DB offers. The label templates are evaluated in a special sandboxed environment,
where only certain operations are allowed. Only read access to entities is allowed. However as it circumvents Part-DB normal permission system, 
the twig mode is only available to users with the "Twig mode" permission.

The following variables are in injected into Twig and can be accessed using `{% raw %}{{ variable }}{% endraw %}` (
or `{% raw %}{{ variable.property }}{% endraw %}`):

| Variable name                              | Description                                                                          |
|--------------------------------------------|--------------------------------------------------------------------------------------|
| `{% raw %}{{ element }}{% endraw %}`       | The target element, selected in label dialog.                                         |
| `{% raw %}{{ user }}{% endraw %}`          | The current logged in user. Null if you are not logged in                            |
| `{% raw %}{{ install_title }}{% endraw %}` | The name of the current Part-DB instance (similar to [[INSTALL_NAME]] placeholder).  |
| `{% raw %}{{ page }}{% endraw %}`          | The page number (the nth-element for which the label is generated                    |
| `{% raw %}{{ last_page }}{% endraw %}`     | The page number of the last element. Equals the number of all pages / element labels |
| `{% raw %}{{ paper_width }}{% endraw %}`   | The width of the label paper in mm                                                   |
| `{% raw %}{{ paper_height }}{% endraw %}`  | The height of the label paper in mm                                                  |

### Use the placeholders in twig mode

You can use the placeholders described above in the twig mode on `element` using the `{% raw %}{{ placeholder('PLACEHOLDER', element) }}{% endraw %}`
function or the ``{{ "[[PLACEHOLDER]]"|placeholders(element) }}`` filter:

```twig
{% raw %}
{# The function can be used to get the a single placeholder value of an element, if the placeholder does not exist, null is returned #}
{{ placeholder('[[NAME]]', element) }}

{# The filter can be used to replace all placeholders in a string with the values of the element #}
{{ "[[NAME]]: [[DESCRIPTION]]"|placeholders(element) }}

{# Using the apply environment every placeholder in the apply block will be replaced automatically #}
{% apply placeholders(element) %}
    [[NAME]]: [[DESCRIPTION]]
{% endapply %}

{# If the block contains HTML use placeholders(element)|raw to prevent escaping of the HTML #}
{% apply placeholders(element)|raw %}
    <b>[[NAME]]</b>: [[DESCRIPTION]]
{% endapply %}

{% endraw %}
```

### Important entity fields in twig mode

In twig mode you have access to many fields of the entity you are generating the label for and their associated entities.
Following are some important fields of the entities listed. See the [SandboxedTwigFactory service](https://github.com/Part-DB/Part-DB-server/blob/master/src/Services/LabelSystem/SandboxedTwigFactory.php) for the full list of allowed class methods.

Please not that the field names might change in the future.

#### Part

| Field name          | Description                                                                                   |
|---------------------|-----------------------------------------------------------------------------------------------|
| `id`                | The internal ID of the part                                                                   |
| `name`              | The name of the part                                                                          |
| `category`          | The category of the part                                                                      |
| `manufacturer`      | The manufacturer of the part                                                                  |
| `footprint`         | The footprint of the part                                                                     |
| `mass`              | The mass of the part                                                                          |
| `ManufacturerProductNumber` | The manufacturer product number of the part                                           |           
| `tags`              | The tags of the part                                                                          |
| `description`       | The rich text (markdown) description of the part                                              |
| `comment`           | The rich text (markdown) comment of the part                                                  |
| `lastModified`      | The datetime object when the part was last modified                                           |
| `creationDate`      | The datetime object when the part was created                                                 |
| `ipn`               | The internal part number of the part                                                          |
| `partUnit`          | The unit of the part                                                                          |
| `amountSum`         | The sum of the amount of all part lots of this part                                           |
| `amountUnknwon`     | Bool: True if there is at least one part lot with unknown amount                              |
| `partLots`          | The part lots of the part                                                                     |
| `parameters`        | The parameters of the part                                                                    |
| `orderdetails`      | The order details of the part as array of Orderdetails                                        |

#### Part lot

| Field name          | Description                                                                                   |
|---------------------|-----------------------------------------------------------------------------------------------|
| `id`                | The internal ID of the part lot                                                               |
| `name`              | The name of the part lot                                                                      |
| `comment`           | The rich text (markdown) comment of the part lot                                              |
| `expirationDate`    | The expiration date of the part lot (as Datetime object)                                      |
| `amount`            | The amount of parts in this lot                                                               |
| `storageLocation`   | The storage location of this part lot                                                         |
| `part`              | The part of this part lot                                                                     |
| `needsRefill`       | Bool: True if the part lot needs a refill                                                     |
| `expired`           | Bool: True if the part lot is expired                                                         |
| `vendorBarcode`     | The vendor barcode field of the lot                                                           |

#### Structural entities like categories, manufacturers, footprints, and storage locations

| Field name          | Description                                                                                   |
|---------------------|-----------------------------------------------------------------------------------------------|
| `id`                | The internal ID of the entity                                                                 |
| `name`              | The name of the entity                                                                        |
| `comment`           | The rich text (markdown) comment of the entity                                                |
| `parent`            | The parent entity of the entity                                                               |
| `children`          | The children entities of the entity                                                           |
| `lastModified`      | The datetime object when the entity was last modified                                         |
| `creationDate`      | The datetime object when the entity was created                                               |
| `level`             | The level of the entity in the hierarchy                                                      |
| `fullPath`          | The full path of the entity (you can pass the delimiter as parameter)                         |
| `pathArray`         | The path of the entity as array of strings                                                    |

#### Orderdetails

| Field name          | Description                                                                                   |
|---------------------|-----------------------------------------------------------------------------------------------|
| `id`                | The internal ID of the order detail                                                           |
| `part`              | The part of the order detail                                                                  |
| `supplier`          | The supplier/distributor of the order detail                                                  |
| `obsolete`          | Bool: True if the order detail is obsolete                                                    |
| `pricedetails`      | The price details of the order detail as array of Pricedetails                                |

#### Pricedetails

| Field name          | Description                                                                                   |
|---------------------|-----------------------------------------------------------------------------------------------|
| `id`                | The internal ID of the price detail                                                           |
| `price`             | The price of the price detail                                                                 |
| `currency`          | The currency of the price detail                                                              |
| `currencyIsoCode`   | The ISO code of the used currency                                                             |
| `pricePerUnit`      | The price per unit of the price detail                                                        |
| `priceRelatedQuantity` | The related quantity of the price detail                                                   |
| `minDiscountQuantity` | The minimum discount quantity of the price detail                                           |

#### User

| Field name          | Description                                                                                   |
|---------------------|-----------------------------------------------------------------------------------------------|
| `id`                | The internal ID of the user                                                                   |
| `username`          | The username of the user                                                                      |
| `email`             | The email of the user                                                                         |
| `fullName`          | The full name of the user                                                                     |
| `lastName`          | The last name of the user                                                                     |	
| `firstName`         | The first name of the user                                                                    |
| `department`        | The department of the user                                                                    |


### Part-DB specific twig functions and filters

Part-DB offers some custom twig functions and filters, which can be used in the twig mode and ease the rendering of
certain data:

#### Functions

| Function name                                | Description                                                                                   |
|----------------------------------------------|-----------------------------------------------------------------------------------------------|
| `placeholder(placeholder, element)`          | Get the value of a placeholder of an element                                                  |
| `entity_type(element)`                       | Get the type of an entity as string                                                           |
| `entity_url(element, type)`                  | Get the URL to a specific entity type page (e.g. `info`, `edit`, etc.)                        |
| `barcode_svg(content, type)`                 | Generate a barcode SVG from the content and type (e.g. `QRCODE`, `CODE128` etc.). A svg string is returned, which you need to data uri encode to inline it.       |

### Filters

| Filter name                                  | Description                                                                                   |
|----------------------------------------------|-----------------------------------------------------------------------------------------------|
| `format_bytes`                              | Format a byte count to a human readable string                                               |
| `format_money(price, currency)`             | Format a price to a human readable string with the currency                                  |
| `format_amount(amount, unit)`               | Format an amount to a human readable string with the unit  object                            |
| `format_si(value, unit_str)`                | Format a value using SI prefixes and the given unit string                                   |
| `placeholders(element)`                     | Replace all placeholders in a string with the values of the element                          |

## Use custom fonts for PDF labels

You can use your own fonts for label generation. To do this, put the TTF files of the fonts you want to use into
the `assets/fonts/dompdf` folder.
The filename will be used as name for the font family, and you can use a `_bold` (or `_b`), `_italic` (or `_i`)
or `_bold_italic` (or `_bi`) suffix to define
different styles of the font. So for example, if you copy the file `myfont.ttf` and `myfont_bold.ttf` into
the `assets/fonts/dompdf` folder, you can use the font family `myfont` with regular and bold style.
Afterward regenerate cache with `php bin/console cache:clear`, so the new fonts will be available for label generation.

The fonts will not be available from the UI directly, you have to use it in the HTML directly either by defining
a `style="font-family: 'myfont';"` attribute on the HTML element or by using a CSS class.
You can define the font globally for the label, by adding following statement to the "Additional styles (CSS)" option in
the label generator settings:

```css
* {
    font-family: 'myfont';
}
```

## Non-latin characters in PDF labels

The default used font (DejaVu) does not support all characters. Especially characters from non-latin languages like
Chinese, Japanese, Korean, Arabic, Hebrew, Cyrillic, etc. are not supported.
For this, we use [Unifont](http://unifoundry.com/unifont.html) as fallback font. This font supports all (or most) Unicode
characters but is not as beautiful as DejaVu.

If you want to use a different (more beautiful) font, you can use the [custom fonts](#use-custom-fonts-for-pdf-labels)
feature.
There is the [Noto](https://www.google.com/get/noto/) font family from Google, which supports a lot of languages and is
available in different styles (regular, bold, italic, bold-italic).
For example, you can use [Noto CJK](https://github.com/notofonts/noto-cjk) for more beautiful Chinese, Japanese, 
and Korean characters.