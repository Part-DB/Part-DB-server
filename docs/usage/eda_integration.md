---
layout: default
title: EDA / KiCad integration
parent: Usage
---

# EDA / KiCad integration

Part-DB can function as a central database for [EDA](https://en.wikipedia.org/wiki/Electronic_design_automation) or ECAD software used to design electronic schematics and PCBs.
You can connect your EDA software and can view your available parts, with the data saved from Part-DB directly in your EDA software.
Part-DB allows to configure additional metadata for the EDA, to associate symbols and footprints for use inside the EDA software, so the part becomes
directly usable inside the EDA software.
This also allows to configure available and usable parts and their properties in a central place, which is especially useful in teams, where multiple persons design PCBs.

**Currently only KiCad is supported!**

## KiCad Setup

{: .important }
> Part-DB uses the HTTP library feature of KiCad, which is experimental and not part of the stable KiCad 7 releases. If you want to use this feature, you need to install a KiCad nightly build (7.99 version). This feature will most likely also be part of KiCad 8.

Part-DB should be accessible from the PCs with KiCAD. The URL should be stable (so no dynamically changing IP).
You require a user account in Part-DB, which has permission to access Part-DB API and create API tokens. Every user can have its own account, or you set up a shared read-only account.

To connect KiCad with Part-DB do the following steps:

1. Create an API token on the user settings page for the KiCAD application and copy/save it, when it is shown. Currently, KiCad can only read Part-DB database, so a token with a read-only scope is enough.
2. Add some EDA metadata to parts, categories, or footprints. Only parts with usable info will show up in KiCad. See below for more info.
3. Create a file `partd.kicad_httplib` (or similar, only the extension is important) with the following content:
```
{
    "meta": {
        "version": 1.0
    },
    "name": "Part-DB library",
    "description": "This KiCAD library fetches information externally from ",
    "source": {
        "type": "REST_API",
        "api_version": "v1",
        "root_url": "http://kicad-instance.invalid/en/kicad-api/",
        "token": "THE_GENERATED_API_TOKEN"
    }
}    
```
4. Replace the `root_url` with the URL of your Part-DB instance plus `/en/kicad-api/`. You can find the right value for this in the Part-DB user settings page under "API endpoints" in the "API tokens" panel.
5. Replace the `token` field value with the token you have generated in step 1.
6. Open KiCad and add this created file as a library in the KiCad symbol table under (Preferences --> Manage Symbol Libraries)

If you then place a new part, the library dialog opens, and you should be able to see the categories and parts from Part-DB.

### How to associate footprints and symbols with parts

Part-DB doesn't save any concrete footprints or symbols for the part. Instead, Part-DB just contains a reference string in the part metadata, which points to a symbol/footprint in KiCad's local library.

You can define this on a per-part basis using the KiCad symbol and KiCad footprint field in the EDA tab of the part editor. Or you can define it at a category (symbol) or footprint level, to assign this value to all parts with this category and footprint.

For example, to configure the values for a BC547 transistor you would put `Transistor_BJT:BC547` on the parts Kicad symbol to give it the right schematic symbol in EEschema and `Package_TO_SOT_THT:TO-92` to give it the right footprint in PcbNew.

If you type in a character, you will get an autocomplete list of all symbols and footprints available in the KiCad standard library. You can also input your own value.

### Parts and category visibility

Only parts and their categories, on which there is any kind of EDA metadata are defined show up in KiCad. So if you want to see parts in KiCad,
you need to define at least a symbol, footprint, reference prefix, or value on a part, category or footprint.

You can use the "Force visibility" checkbox on a part or category to override this behavior and force parts to be visible or hidden in KiCad.

*Please note that KiCad caches the library categories. So if you change something, which would change the visible categories in KiCad, you have to reload EEschema to see the changes.*

### Category depth in KiCad

For performance reasons, only the most top-level categories of Part-DB are shown as categories in KiCad. All parts in the subcategories are shown in the top-level category.

You can configure the depth of the categories shown in KiCad, via the `EDA_KICAD_CATEGORY_DEPTH` env option. The default value is 0, which means only the top-level categories are shown.
To show more levels of categories, you can set this value to a higher number.

If you set this value to -1, all parts are shown inside a single category in KiCad, without any subcategories.

You can view the "real" category path of a part in the part details dialog in KiCad.
