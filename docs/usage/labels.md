---
title: Labels
layout: default
parent: Usage
---

# Labels

Part-DB supports the generation and printing of labels for parts, part lots and storage locations.
You can use the "Tools -> Label generator" menu entry to create labels or click the label generation link on the part.

You can define label templates by creating label profiles. This way you can create many similar-looking labels for
many parts.

The content of the labels is defined by the template's content field. You can use the WYSIWYG editor to create and style
the content (or write HTML code).
Using the "Label placeholder" menu in the editor, you can insert placeholders for the data of the parts.
It will be replaced by the concrete data when the label is generated.

## Printing with a print agent

Label PDFs printed through the browser's print dialog are often scaled by the printer driver, which blurs barcodes on
small labels. A *print agent* is a small program running on your computer that prints the label directly on a label
printer at the printer's native resolution. Part-DB does not ship an agent; one example is
[pdb-print](https://github.com/francisrath/partdb-print-agent) for Brother QL printers.

To connect a browser, open the connection link from your agent's settings and click "Connect" (or open
"Tools -> Print agent"). The connection is stored in this browser only (local storage), not on the server.
Afterwards a "Print" button appears next to "Download" in the label generator for saved label profiles, and a printer
icon next to each profile in the label menus of parts, part lots and storage locations. Printing always uses the saved
label profile; unsaved changes in the label generator are not printed.

### Agent contract

The connection link has the form `<Part-DB URL>/label/agent#url=<agent URL>&key=<pairing key>`, where the pairing key
is a hex string. The fragment is never sent to the Part-DB server.

When printing, Part-DB opens the following URL in a new window:

```
<agent URL>/v1/handoff?src=<Part-DB origin>&type=<part|part_lot|storelocation>&ids=<ids>&profile=<label profile id>&ts=<unix time>&sig=<signature>
```

`ids` uses the label generator's range syntax without spaces or empty entries (e.g. `1,2,5-10`). `sig` is the lowercase
hex HMAC-SHA256 of `src`, `type`, `ids`, `profile` and `ts` joined by newlines (`\n`). The HMAC key is the UTF-8 bytes of
the pairing key string as given in the connection link (lowercase, not hex-decoded). An agent should only accept a recent
`ts` (pdb-print: ±2 minutes), refuse a `sig` it has already seen, and compare `src` with the Part-DB URL it is configured
for by scheme, host and port. If the browser has no WebCrypto support (Part-DB served over plain HTTP from another host),
`sig` is omitted and the agent should ask the user to confirm. The agent then fetches the label PDF itself with an API token via `POST /api/labels/generate` and prints it.

Anyone who can run JavaScript on your Part-DB pages could read the pairing key from local storage and ask your agent to
print labels; it gives no access to Part-DB data.

### Print jobs

A print agent can also fetch print jobs from Part-DB. This works from any device (also a phone) and through NAT,
because the agent connects to Part-DB, not the other way round. The agent registers itself with an API token of its
user; a read-only token is enough, but the user needs the "create labels" and "access API" permissions. Agents and their jobs are only
visible to their owner, under "Tools -> Print agent".

Once an agent has reported its printers, the label generator's "Print" button sends the label to one of them, and a label
profile can name a default printer (the printer id reported by the agent): the printer icons in the label menus and
the "Print labels" actions of the parts tables then print on it in one click.

The agent API uses JSON and the usual `Authorization: Bearer <token>` header (`en` can be any language prefix):

* `POST /en/agent-api/v1/agents/<uuid>/heartbeat` with `{"name": "...", "version": "...", "printers": [{"id": "ql810", "name": "Brother QL-810W"}]}`
  registers or updates the agent. The agent generates the UUID once and keeps it, so a new API token keeps its jobs.
  Each printer needs a string `id`; other fields are stored as they are. Returns `{"agent_id": 1, "poll_seconds": 30}`.
  Agents should send it about every `poll_seconds`; an agent that has not reported for two minutes is shown offline.
* `GET /en/agent-api/v1/agents/<uuid>/jobs` returns the pending jobs, oldest first:
  `[{"id": 7, "type": "print", "printer": "ql810", "payload": {"profile_id": 2, "element_type": "part", "element_ids": "1,2,5-10"}, "created_at": "..."}]`.
  For print jobs, the agent fetches the PDF with `POST /api/labels/generate`.
* `POST /en/agent-api/v1/jobs/<id>/claim` claims a pending job (`409` if it is not pending anymore). Only claim a job
  right before handling it.
* `POST /en/agent-api/v1/jobs/<id>/result` with `{"status": "done"|"failed", "message": "..."}` reports the result.

If [push is configured]({% link configuration.md %}#print-agent-push-settings-all-env-only), the heartbeat answer also
contains `"mercure": {"hub": "...", "topic": "...", "token": "..."}`. The agent can then subscribe to the hub
(`GET <hub>?match=<topic>` with `Authorization: Bearer <token>`, a server-sent event stream) and look for jobs as soon as
an event arrives, instead of waiting for its next poll. The token is valid for one hour; the agent gets a new one with
every heartbeat. Polling stays the fallback, and the agent should list its pending jobs after every reconnect.

Part-DB never re-queues a job by itself: a claimed job without a result is shown as "unknown" after ten minutes, and the
user can send it again. Finished jobs are deleted after 30 days.

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
| `[[MIN_STOCK]]`         | The minimum stock of the part                   | 10                          |
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
where only certain operations are allowed. Only read access to entities is allowed. However, as it circumvents Part-DB normal permission system,
the twig mode is only available to users with the "Twig mode" permission.

It is useful to use the HTML embed feature of the editor, to have a block where you can write the twig code without worrying about the WYSIWYG editor messing with your code.

The following variables are in injected into Twig and can be accessed using `{% raw %}{{ variable }}{% endraw %}` (
or `{% raw %}{{ variable.property }}{% endraw %}`):

| Variable name                              | Description                                                                          |
|--------------------------------------------|--------------------------------------------------------------------------------------|
| `{% raw %}{{ element }}{% endraw %}`       | The target element, selected in label dialog.                                        |
| `{% raw %}{{ user }}{% endraw %}`          | The current logged in user. Null if you are not logged in                            |
| `{% raw %}{{ install_title }}{% endraw %}` | The name of the current Part-DB instance (similar to [[INSTALL_NAME]] placeholder).  |
| `{% raw %}{{ page }}{% endraw %}`          | The page number (the nth-element for which the label is generated )                  |
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

| Function name                            | Description                                                                                                                                                 |
|------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `placeholder(placeholder, element)`      | Get the value of a placeholder of an element                                                                                                                |
| `entity_type(element)`                   | Get the type of an entity as string                                                                                                                         |
| `entity_url(element, type)`              | Get the URL to a specific entity type page (e.g. `info`, `edit`, etc.)                                                                                      |
| `barcode_svg(content, type)`             | Generate a barcode SVG from the content and type (e.g. `QRCODE`, `CODE128` etc.). A svg string is returned, which you need to data uri encode to inline it. |
| `associated_parts(element)`              | Get the associated parts of an element like a storagelocation, footprint, etc. Only the directly associated parts are returned                              |
| `associated_parts_r(element)`            | Get the associated parts of an element like a storagelocation, footprint, etc. including all sub-entities recursively (e.g. sub-locations)                  |
| `associated_parts_count(element)`        | Get the count of associated parts of an element like a storagelocation, footprint, excluding sub-entities                                                   |
| `associated_parts_count_r(element)`      | Get the count of associated parts of an element like a storagelocation, footprint, including all sub-entities recursively (e.g. sub-locations)              |
| `type_label(element)`                     | Get the name of the type of an element (e.g. "Part", "Storage location", etc.)                                                                              |
| `type_label_p(element)`                    | Get the name of the type of an element in plural form (e.g. "Parts", "Storage locations", etc.)                                                              |
| `debug_vars()`                            | Debug helper: lists all variables currently available in the template (and their type), so you can drop `{% raw %}{{ debug_vars() }}{% endraw %}` anywhere in your template while writing it to see what you have access to |

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
