---
title: Backup & Restore Data
layout: default
parent: Usage
---

# Labels

## Label placeholders
A placeholder has the format `[[PLACEHOLDER]]` and will be filled with the concrete data by Part-DB.
You can use the "Placeholders" dropdown in content editor, to automatically insert the placeholders.

### Common

| Placeholder | Description  | Example  |
|---|---|---|
| `[[USERNAME]]`  | The user name of the currently logged in user  | admin  |
| `[[USERNAME_FULL]]`  | The full name of the current user  | John Doe (@admin)  |
| `[[DATETIME]]`  | The current date and time in the selected locale  | 31.12.2017, 18:34:11  |
| `[[DATE]]`  | The current date in the selected locale  | 31.12.2017 |
| `[[TIME]]` | The current time in the selected locale | 18:34:11 |
| `[[INSTALL_NAME]]` | The name of the current installation (see $config['partdb_title']) | Part-DB |

### Parts

| Placeholder  | Description  | Example  |
|---|---|---|
| `[[ID]]`  | The internal ID of the part  | 24  |
| `[[NAME]]`  | The name of the part  | ATMega328  |
| `[[CATEGORY]]`  | The name of the category (without path)  | AVRs  |
| `[[CATEGORY_FULL]]`  | The full path of the category  | Aktiv->MCUs->AVRs  |
| `[[MANUFACTURER]]`  | The name of the manufacturer  | Atmel  |
| `[[MANUFACTURER_FULL]]`  | The full path of the manufacturer  | Halbleiterhersteller->Atmel  |
| `[[FOOTPRINT]]`  | The name of the footprint (without path)  | DIP-32  |
| `[[FOOTPRINT_FULL]]`  | The full path of the footprint  | Bedrahtet->DIP->DIP-32  |
| `[[MASS]]` | The mass of the part | 123.4 g |
| `[[MPN]]`  | The manufacturer product number | BC547ACT |
| `[[TAGS]]` | The tags of the part | SMD, Tag1 |
| `[[M_STATUS]]` | The manufacturing status of the part | Active |
| `[[DESCRIPTION]]` | The rich text description of the part | *NPN* |
| `[[DESCRIPTION_T]]` | The description as plain text | NPN |
| `[[COMMENT]]` | The rich text comment of the part | |
| `[[COMMENT_T]]` | The comment as plain text | |
| `[[LAST_MODIFIED]]` | The datetime when the element was last modified |  2/26/16, 5:38 PM |
| `[[CREATION_DATE]]` | The datetime when the element was created  | 2/26/16, 5:38 PM |

### Part lot
| Placeholder  | Description  | Example  |
|---|---|---|
| `[[LOT_ID]]`   | Part lot ID | 123  |
| `[[LOT_NAME]]` | Part lot name |  |
| `[[LOT_COMMENT]]` | Part lot comment | |
| `[[EXPIRATION_DATE]]` | Expiration date of the part lot | |
| `[[AMOUNT]]` | The amount of parts in this lot | 12 |
| `[[LOCATION]]` | The storage location of this part lot | Location A |
| `[[LOCATION_FULL]]` | The full path of the storage location | Location -> Location A |

### Storelocation
| Placeholder  | Description  | Example  |
|---|---|---|
| `[[ID]]` | ID of the storage location |  |
| `[[NAME]]` | Name of the storage location | Location A |
| `[[FULL_PATH]]` | The full path of the storage location | Location -> Location A |
| `[[PARENT]]` | The name of the parent location | Location |
| `[[PARENT_FULL_PATH]]` | The full path of the storage location | |
| `[[COMMENT]]` | The comment of the storage location | |
| `[[COMMENT_T]]` | The plain text version of the comment | 
| `[[LAST_MODIFIED]]` | The datetime when the element was last modified |  2/26/16, 5:38 PM |
| `[[CREATION_DATE]]` | The datetime when the element was created  | 2/26/16, 5:38 PM |

## Twig mode
If you select "Twig" in parser mode under advanced settings, you can input a twig template in the lines field (activate source mode). You can use most of the twig tags and filters listed in [offical documentation](https://twig.symfony.com/doc/3.x/).

The following variables are in injected into Twig and can be accessed using `{{ variable }}` (or `{{ variable.property }}`):

| Variable name | Description |
| ------------- | ----------- |
| `{{ element }}` | The target element, selected in label dialog |
| `{{ user }}`   | The current logged in user. Null if you are not logged in |
| `{{ install_title }}` | The name of the current Part-DB instance (similar to [[INSTALL_NAME]] placeholder). |
| `{{ page }}` | The page number (the nth-element for which the label is generated |