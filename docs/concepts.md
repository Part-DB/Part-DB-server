---
layout: default
title: Concepts
nav_order: 2
---

# Concepts

This page explains the different concepts of Part-DB and what their intended use is:

1. TOC
{:toc}

## Part management

### Part

A part is the central concept of Part-DB. A part represents a single kind (or type) of a thing, like an electronic
component, a device, a book or similar (depending on what you use Part-DB for). A part entity just represents a certain
type of thing, so if you have 1000 times a BC547 transistor you would create ONE part with the name BC547 and set its
quantity to 1000. The individual quantities (so a single BC547 transistor) of a part, should be indistinguishable from
each other so that it does not matter which one of your 1000 things of Part you use.
A part entity has many fields, which can be used to describe it better. Most of the fields are optional:

* **Name** (Required): The name of the part or how you want to call it. This could be a manufacturer-provided name, or a
  name you thought of yourself. Each name needs to be unique and must exist in a single category.
* **Description**: A short (single-line) description of what this part is/does. For longer information, you should use
  the comment field or the specifications
* **Category** (Required): The category (see there) to which this part belongs to.
* **Tags**: The list of tags this part belongs to. Tags can be used to group parts logically (similar to the category),
  but tags are much less strict and formal (they don't have to be defined forehands) and you can assign multiple tags to
  a part. When clicking on a tag, a list with all parts which have the same tag, is shown.
* **Min Instock**: *Not really implemented yet*. Parts where the total instock is below this value, will show up for
  ordering.
* **Footprint**: See there. Useful especially for electronic parts, which have one of the common electronic footprints (
  like DIP8, SMD0805 or similar). If a part has no explicitly defined preview picture, the preview picture of its
  footprint will be shown instead in tables.
* **Manufacturer**: The manufacturer which has manufactured (not sold) this part. See Manufacturer entity for more info.
* **Manufacturer part number** (MPN): If you have used your own name for a part, you can put the part number the
  manufacturer uses in this field so that you can find a part also under its manufacturer number.
* **Link to product page**: If you want to link to the manufacturer website of a part, and it is not possible to
  determine it automatically from the part name, set in the manufacturer entity (or no manufacturer is set), you can set
  the link here for each part individually.
* **Manufacturing Status**: The manufacturing status of this part, meaning the information about where the part is in
  its manufacturing lifecycle.
* **Needs review**: If you think parts information may be inaccurate or incomplete and needs some later
  review/checking, you can set this flag. A part with this flag is marked, so that users know the information is not
  completely trustworthy.
* **Favorite**: Parts with this flag are highlighted in parts lists
* **Mass**: The mass of a single piece of this part (so of a single transistor). Given in grams.
* **Internal Part number** (IPN): Each part is automatically assigned a numerical ID that identifies a part in the
  database. This ID depends on when a part was created and can not be changed. If you want to assign your own unique
  identifiers, or sync parts identifiers with the identifiers of another database you can use this field.

### Stock / Part lot

A part can have many stocks at multiple different locations. This is represented by part lots/stocks, which consists
basically of a storage location (so where the parts of this lot are stored) and an amount (how many parts are there).

### Purchase Information

The purchase information describes where the part can be bought (at which vendors) and at which prices.
The first part (the order information) describes at which supplier the part can be bought and which is the name of the
part under which you can order the part there.
An order information can contain multiple price information, which describes the prices for the part at the supplier
including bulk discount, etc.

### Parameters

Parameters represent various specifications/parameters of a part, like the maximum current of a diode, etc. The
advantage of using parameters instead of just putting the data in the comment field or so, is that you can filter for
parameter's values (including ranges and more) later on.
Parameters can describe numeric values and/or text values for which they can be filtered. This allows
you to define custom fields on a part.

Using the group field as a parameter allows you to group parameters together on the info page later (all parameters with
the same group value will be shown under the same group title).

## Core data

### Category

A category is used to group parts logically by their function (e.g. all NPN transistors would be put in a "
NPN-Transistors" category).
Categories are hierarchical structures meaning that you can create logical trees to group categories together. A
possible category tree could look like this:

* Active Components
    * Transistors
        * BJTs
            * NPN
            * PNP
    * ICs
        * Logic ICs
        * MCUs
* Passive Components
    * Capacitors
    * Resistors

### Supplier

A Supplier is a vendor/distributor where you can buy/order parts. Price information of parts is associated with a
supplier.

### Manufacturer

A manufacturer represents the company that manufacturers/builds various parts (not necessarily sell them). If the
manufacturer also sells the parts, you have to create a supplier for that.

### Storage location

A storage location represents a place where parts can be stored. This could be a box, a shelf, or other things (like the
SMD feeder of a machine or so).

Storage locations are hierarchical to represent storage locations contained in each other.
An example tree could look like this:

* Shelf 1
    * Box 1
    * Box 2
        * Box shelf A1
        * Box shelf A2
        * Box shelf B1
        * Box shelf B2
* Shelf 2
* Cupboard

Storage locations should be defined down to the smallest possible location, to make finding the part again easy.

### Footprint

In electronics, many components have one of the common components cases/footprints. The footprint entity describes such
common footprints, which can be assigned to parts.
You can assign an image (and a 3D model) as an attachment to a footprint, which will be used as preview for parts with
this footprint, even if the parts do not have an explicitly assigned preview image.

Footprints are hierarchically which allows you to build logically sorted trees. An example tree could look like this:

* Through-Hole components
    * DIP
        * DIP-8
        * DIP-28
        * DIP-28W
    * TO
        * TO-92
* SMD components
    * SOIC
        * SO-8
    * Resistors
        * 0805
        * 0603

### Measurement Unit

By default, part in stock is counted in number of individual parts, which is fine for things like electronic components,
which exist only in integer quantities. However, if you have things with fractional units like the length of a wire or
the volume of a liquid, you have to define a measurement unit.
The measurement unit represents a physical quantity like mass, volume, or length.

You can define a short unit for it (like m for Meters, or g for grams) which will be shown when a quantity of a part
with this unit is shown.

In order to cover wider use cases and allow you to define measurement units further, it is possible to define parameters
associated to a measurement unit. These parameters are distinct from a part's parameters and are not inherited.

### Currency

By default, all prices are set in the base currency configured for the instance (by default euros). If you want to use
multiple currencies together (e.g. vendors use foreign currencies for their price, and you do not want to update the
prices for every exchange rate change), you have to define these currencies here.

You can set an exchange rate here in terms of the base currency (or fetch it from the internet if configured). The
exchange rate will be used to show users the prices in their preferred currency.

## Attachments

### Attachment

An attachment is a file that can be associated with another entity (like a Part, location, User, etc.). This could
for example be a datasheet in a Part, the logo of a vendor or some CAD drawing of a footprint.

An attachment has an attachment type (see below), which groups the attachments logically (and optionally restricts the
allowed file types), a name describing the attachment and a file. The file can either be uploaded to the server and
stored there, or given as a link to a file on another web path. If configured in the settings, it is also possible that
the web server downloads the file from the supplied website and stores it locally on the server.

By default, all uploaded files, are accessible for everyone (even non-logged-in users), if the link is known. If your
Part-DB instance is publicly available, and you want to store private/sensitive files on it, you should mark the
attachment as "Private attachment". Private attachments are only accessible to users, which has permission to access
private attachments.
Please note, that no thumbnails are generated for private attachments, which can have a performance impact.

Part-DB ships some preview images for various common footprints like DIP-8 and others, as internal resources. These can
be accessed/searched by typing the keyword in the URL field of a part and choosing one of the choices from the dropdown.

### Preview image/attachment

Most entities with attachments allow you to select one of the defined attachments as "Preview image". You can select an
image attachment here, that previews the entity, this could be a picture of a Part, the logo of a manufacturer or
supplier, the schematic symbol of a category or the image of a footprint.
The preview image will be shown in various locations together with the entity's name.

Please note that as long as the picture is not secret, it should be stored on the Part-DB instance (by uploading, or
letting Part-DB download the file) and *not* be marked as a private attachment, so that thumbnails can be generated for
the picture (which improves performance).

### Attachment types

Attachment types define logical groups of attachments. For example, you could define an attachment group "Datasheets"
where all datasheets of Parts, Footprints, etc. belong in, "Pictures" for preview images and more.
You can define file type restrictions, and which file types and extensions are allowed for files with that attachment type.

## User System

### User

Each person who should be able to use Part-DB (by logging in) is represented by a user entity, which defines things
like access rights, the password, and other things. For security reasons, every person who will use Part-DB should use
their own personal account with a secret password. This allows to track activity of the users via the log.

There is a special user called `anonymous`, whose access rights are used to determine what a non-logged-in user can do.
Normally the anonymous user should be the most restricted user.

For simplification of access management users can be assigned to groups.

### Group

A group is an entity, to which users can be assigned to. This can be used to logically group users by for example
organizational structures and to simplify permissions management, as you can define groups with access rights for common
use cases and then just assign users to them, without the need to change every permission on the users individually.

## Labels

### Label profiles

A label profile represents a template for a label (for a storage location, a part or part lot). It consists of a size, a
barcode type and the content. There are various placeholders that can be inserted in the text content and which will be 
replaced with data for the actual thing.

You do not have to define a label profile to generate labels (you can just set the settings on the fly in the label
dialog), however, if you want to generate many labels, it is recommended to save the settings as a label profile, to save
it for later usage. This ensures that all generated labels look the same.
