---
title: Barcode Scanner
layout: default
parent: Usage
---

# Barcode scanner

When the user has the correct permission there will be a barcode scanner button in the navbar.
On this page you can either input a barcode code by hand, use an external barcode scanner, or use your devices camera to
scan a barcode.

In info mode (when the "Info" toggle is enabled) you can scan a barcode and Part-DB will parse it and show information 
about it.

Without info mode, the barcode will directly redirect you to the corresponding page.

### Barcode matching

When you scan a barcode, Part-DB will try to match it to an existing part, part lot or storage location first.
For Part-DB generated barcodes, it will use the internal ID of a part.  Alternatively you can also scan a barcode that contains the part's IPN.

You can set a GTIN/EAN code in the part properties and Part-DB will open the part page when you scan the corresponding GTIN/EAN barcode.

On a part lot you can under "Advanced" set a user barcode, that will redirect you to the part lot page when scanned. This allows to reuse
arbitrary existing barcodes that already exist on the part lots (for example, from the manufacturer) and link them to the part lot in Part-DB.

Part-DB can also parse various distributor barcodes (for example from Digikey and Mouser) and will try to redirect you to the corresponding
part page based on the distributor part number in the barcode.

### Part creation from barcodes
For certain barcodes Part-DB can automatically create a new part, when it cannot find a matching part.
Part-DB will try to retrieve the part information from an information provider and redirects you to the part creation page 
with the retrieved information pre-filled.

## Using an external barcode scanner

Part-DB supports the use of external barcode scanners that emulate keyboard input. To use a barcode scanner with Part-DB,
simply connect the scanner to your computer and scan a barcode while the cursor is in a text field in Part-DB.
The scanned barcode will be entered into the text field as if you had typed it on the keyboard.

In scanner fields, it will also try to insert special non-printable characters the scanner send via Alt + key combinations.
This is required for EIGP114 datamatrix codes.

### Automatically redirect on barcode scanning

If you configure your barcode scanner to send a <SOH> (Start of heading, 0x01) non-printable character at the beginning
of the scanned barcode, Part-DB will automatically scan the barcode that comes afterward (and is ended with an enter key)
and redirects you to the corresponding page.
This allows you to quickly scan a barcode from anywhere in Part-DB without the need to first open the scanner page.
If an input field is focused, the barcode will be entered into the field as usual and no redirection will happen.
