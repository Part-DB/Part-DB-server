---
layout: default
title: Import & Export data
nav_order: 4
parent: Usage
---

# Import & Export data

Part-DB offers the possibility to import existing data (parts, data structures, etc.) from existing data sources into
Part-DB. Data can also be exported from Part-DB into various formats.

## Import

{: .note }
> As data import is a very powerful feature and can easily fill up your database with lots of data, import is by default
> only available for
> administrators. If you want to allow other users to import data, or can not import data, check the permissions of the
> user. You can enable import for each data structure
> individually in the permissions settings.

If you want to import data from PartKeepr you might want to look into the [PartKeepr migration guide]({% link
upgrade_legacy.md %}).

### Import parts

Part-DB supports the import of parts from CSV files and other formats. This can be used to import existing parts from
other databases or data sources into Part-DB. The import can be done via the "Tools -> Import parts" page, which you can
find in the "Tools" sidebar panel.

{: .important }
> When importing data, the data is immediately written to database during the import process, when the data is formally
> valid.
> You will not be able to check the data before it is written to the database, so you should review the data before
> using the import tool.

You can upload the file that should be imported here and choose various options on how the data should be treated:

* **Format**: By default "auto" is selected here and Part-DB will try to detect the format of the file automatically
  based on its file extension. If you want to force a specific format or Part-DB can not auto-detect the format, you can
  select it here.
* **CSV delimiter**: If you upload a CSV file, you can select the delimiter character which is used to separate the
  columns in the CSV file. Depending on the CSV file, this might be a comma (`,`) or semicolon (`;`).
* **Category override**: You can select (or create) a category here, to which all imported parts should be assigned, no
  matter what was specified in the import file. This can be useful if you want to assign all imports to a certain
  category or if no category is specified in the data. If you leave this field empty, the category will be determined by
  the import file (or the export will error, if no category is specified).
* **Mark all imported parts as "Needs review"**: If this is selected, all imported parts will be marked as "Needs
  review" after the import. This can be useful if you want to review all imported parts before using them.
* **Create unknown data structures**: If this is selected Part-DB will create new data structures (like categories,
  manufacturers, etc.) if no data structure(s) with the same name and path already exists. If this is not selected, only
  existing data structures will be used and if no matching data strucure is found, the imported parts field will be empty.
* **Path delimiter**: Part-DB allows you to create/select nested data structures (like categories, manufacturers, etc.)
  by using a path (e.g. `Category 1->Category 1.1`, which will select/create the `Category 1.1` whose parent
  is `Category 1`). This path is separated by the path delimiter. If you want to use a different path delimiter than the
  default one (which is `>`), you can select it here.
* **Abort on validation error**: If this is selected, the import will be aborted if a validation error occurs (e.g. if a
  required field is empty) for any of the imported parts and validation errors will be shown on top of the page. If this
  is not selected, the import will continue for the other parts and only the invalid parts will be skipped.

After you have selected the options, you can start the import by clicking the "Import" button. When the import is
finished, you will see the results of the import in the lower half of the page. You can find a table with the imported
parts (including links to them) there.

#### Fields description

For the importing of parts, you can use the following fields which will be imported into each part. Please note that the
field names are case-sensitive (so `name` is not the same as `Name`). All fields (besides name) are optional, so you can
leave them empty or do not include the column in your file.

* **`name`** (required): The name of the part. This is the only required field, all other fields are optional.
* **`description`**: The description of the part, you can use markdown/HTML syntax here for rich text formatting.
* **`notes`** or **`comment`**: The notes of the part, you can use markdown/HTML syntax here for rich text formatting.
* **`category`**: The category of the part. This can be a path (e.g. `Category 1->Category 1.1`), which will
  select/create the `Category 1.1` whose parent is `Category 1`. If you want to use a different path delimiter than the
  default one (which is `->`), you can select it in the import options. If the category does not exist and the option "
  Create unknown datastructures" is selected, it will be created.
* **`footprint`**: The footprint of the part. Can be a path similar to the category field.
* **`favorite`**: If this is set to `1`, the part will be marked as favorite.
* **`manufacturer`**: The manufacturer of the part. Can be a path similar to the category field.
* **`manufacturer_product_number`** or **`mpn`**: The manufacturer product number of the part.
* **`manufacturer_product_url`**: The URL to the product page of the manufacturer of the part.
* **`manufacturing_status`**: The manufacturing status of the part, must be one of the following
  values: `announced`, `active`, `nrfnd`, `eol`, `discontinued` or left empty.
* **`needs_review`** or **`needs_review`**: If this is set to `1`, the part will be marked as "needs review".
* **`tags`**: A comma-separated list of tags for the part.
* **`mass`**: The mass of the part in grams.
* **`ipn`**: The IPN (Item Part Number) of the part.
* **`minamount`**: The minimum amount of the part which should be in stock.
* **`partUnit`**: The measurement unit of the part to use. Can be a path similar to the category field.

With the following fields, you can specify storage locations and amount/quantity in stock of the part. A PartLot will
be created automatically from the data and assigned to the part. The following fields are helpers for an easy import of
parts at one storage location. If you need to create a Part with multiple PartLots you have to use JSON format (or CSV)
with nested objects:

**`storage_location`** or **`storelocation`**: The storage location of the part. Can be a path similar to the category
field.
**`amount`**, **`quantity`** or **`instock`**: The amount of the part in stock. If this value is not set, the part lot
will be marked with "unknown amount"

The following fields can be used to specify the supplier/distributor, supplier product number, and the price of the part.
This is only possible for a single supplier/distributor and price with these fields. If you need to specify multiple
suppliers/distributors or prices, you have to use JSON format (or CSV) with nested objects.
**Please note that the supplier fields is required, if you want to import prices or supplier product numbers**. If the
supplier is not specified, the price and supplier product number fields will be ignored:

* **`supplier`**: The supplier of the part. Can be a path similar to the category field.
* **`supplier_product_number`** or **`supplier_part_number`** or * **`spn`**: The supplier product number of the part.
* **`price`**: The price of the part in the base currency of the database (by default euro).

#### Example data

Here you can find some example data for the import of parts, you can use it as a template for your own import (
especially the CSV file).

* [Part import CSV example]({% link assets/usage/import_export/part_import_example.csv %}) with all possible fields

## Export

By default, every user, who can read the datastructure, can also export the data of this datastructure, as this does not
give the user any additional information.

### Exporting data structures (categories, manufacturers, etc.)

You can export data structures (like categories, manufacturers, etc.) in the respective edit page (e.g. Tools Panel ->
Edit -> Category).
If you select a certain data structure from your list, you can export it (and optionally all sub data structures) in the "
Export" tab.
If you want to export all data structures of a certain type (e.g. all categories in your database), you can select the "
Export all" function in the "Import / Export" tab of the "new element" page.

You can select between the following export formats:

* **CSV** (Comma Separated Values): A semicolon-separated list of values, where every line represents an element. This
  format can be imported into Excel or LibreOffice Calc and is easy to work with. However, it does not support nested
  data structures or sub data (like parameters, attachments, etc.), very well (many columns are generated, as every
  possible sub-data is exported as a separate column).
* **JSON** (JavaScript Object Notation): A text-based format, which is easy to work with programming languages. It
  supports nested data structures and sub-data (like parameters, attachments, etc.) very well. However, it is not easy to
  work with in Excel or LibreOffice Calc and you may need to write some code to work with the exported data
  efficiently.
* **YAML** (Yet Another Markup Language): Very similar to JSON
* **XML** (Extensible Markup Language): Good support with nested data structures. Similar use cases as JSON and YAML.

Also, you can select between the following export levels:

* **Simple**: This will only export very basic information about the name (like the name, or description for parts)
* **Extended**: This will export all commonly used information about this data structure (like notes, options, etc.)
* **Full**: This will export all available information about this data structure (like all parameters, attachments)

Please note that the level will also be applied to all sub-data or children elements. So if you select "Full" for a
part, all the associated categories, manufacturers, footprints, etc. will also be exported with all available
information, this can lead to very large export files.

### Exporting parts

You can export parts in all part tables. Select the parts you want via the checkbox in the table line and select the
export format and level in the appearing menu.

See the section about exporting data structures for more information about the export formats and levels.