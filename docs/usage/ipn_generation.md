---
title: Internal Part Number (IPN) Generation
layout: default
parent: Usage
nav_order: 12
---

# Internal Part Number (IPN) Generation

Part-DB supports automatic generation and management of Internal Part Numbers (IPNs) for your parts. IPNs are unique identifiers that help you organize and track your inventory in a structured way.

1. TOC
{:toc}

## What is an IPN?

An Internal Part Number (IPN) is a unique identifier assigned to each part in your inventory. Unlike manufacturer part numbers (MPNs), IPNs are defined and controlled by you, following your own naming conventions and organizational structure.

IPNs are useful for:
- Creating a consistent numbering scheme across your entire inventory
- Organizing parts hierarchically based on categories
- Quickly identifying and locating parts
- Generating barcodes for parts
- Integrating with external systems (like EDA tools)

## Basic Concepts

### IPN Structure

An IPN typically consists of several components:
- **Prefix**: Identifies the category or type of part (e.g., "RES" for resistors, "CAP" for capacitors)
- **Separator**: Divides different parts of the IPN (default is `-`)
- **Number**: A sequential number that makes the IPN unique

Example: `RES-0001`, `CAP-IC-0042`, `MCU-ARM-1234`

### Category-Based IPN Prefixes

Categories in Part-DB can have their own IPN prefix. When creating a new part in a category, Part-DB can automatically suggest IPNs based on the category's prefix.

To set an IPN prefix for a category:
1. Navigate to the category edit page
2. Find the "Part IPN Prefix" field
3. Enter your desired prefix (e.g., "RES", "CAP", "IC")

### Hierarchical Prefixes

Part-DB supports hierarchical IPN generation based on parent categories. For example:
- Parent category "IC" with prefix "IC"
- Child category "Microcontrollers" with prefix "MCU"
- Generated IPN could be: `IC-MCU-0001`

This allows you to create deeply nested categorization schemes while maintaining clear IPNs.

## Configuring IPN Generation

You can configure IPN generation in the system settings under `Tools -> System -> Settings -> Miscellaneous -> IPN Suggest Settings`.

### Available Settings

#### Regex Pattern
Define a regular expression pattern that valid IPNs must match. This helps enforce consistency across your inventory.

Example: `^[A-Za-z0-9]{3,4}(?:-[A-Za-z0-9]{3,4})*-\d{4}$`

This pattern requires:
- 3-4 alphanumeric characters for prefixes
- Optional additional prefix groups separated by `-`
- Ending with a 4-digit number

#### Regex Help Text
Provide custom help text that explains your IPN format to users. This text is shown when users are creating or editing parts.

#### Auto-Append Suffix
When enabled, Part-DB automatically appends a suffix (`_1`, `_2`, etc.) to IPNs that would otherwise be duplicates. This prevents IPN collisions when multiple parts might generate the same IPN.

**Example:**
- First part: `RES-0001`
- Duplicate attempt: automatically becomes `RES-0001_1`
- Next duplicate: automatically becomes `RES-0001_2`

#### Suggest Part Digits
Defines how many digits should be used for the sequential part number (default: 4).
- 4 digits: `0001` to `9999`
- 6 digits: `000001` to `999999`

#### Use Duplicate Description
When enabled, Part-DB will suggest the same IPN for parts with identical descriptions. This is useful when you want to track variants of the same component with the same IPN scheme.

#### Fallback Prefix
The prefix to use when a category has no IPN prefix defined (default: `N.A.`). This ensures all parts can get an IPN suggestion even without category-specific prefixes.

#### Number Separator
The character that separates the prefix from the number (default: `-`).

Example: With separator `-`, you get `RES-0001`. With separator `.`, you get `RES.0001`.

#### Category Separator
The character that separates hierarchical category prefixes (default: `-`).

Example: With separator `-`, you get `IC-MCU-0001`. With separator `.`, you get `IC.MCU.0001`.

#### Global Prefix
An optional prefix that is prepended to all IPNs in your system. Useful if you want to distinguish your inventory from other systems.

Example: With global prefix `ACME`, IPNs become `ACME-RES-0001`, `ACME-CAP-0042`, etc.

## Using IPN Suggestions

### When Creating a New Part

When you create a new part, Part-DB provides IPN suggestions based on:

1. **Global Prefix** (if configured): Suggestions using your global prefix
2. **Description Matching** (if enabled): If another part has the same description, its IPN is suggested
3. **Direct Category Prefix**: The IPN prefix of the part's assigned category
4. **Hierarchical Prefixes**: IPNs combining parent category prefixes with the current category

Each suggestion includes:
- The suggested IPN
- A description of how it was generated
- An auto-incremented version (ending with the next available number)

### IPN Suggestion Types

#### Common Prefixes
These show just the prefix part without a number. Use these as a starting point to manually add your own number.

Example: `RES-` (you then type `RES-1234`)

#### Prefixes with Part Increment
These show complete IPNs with automatically incremented numbers. The system finds the highest existing number with that prefix and suggests the next one.

Example: If `RES-0001` through `RES-0005` exist, the system suggests `RES-0006`.

### Manual IPN Entry

You can always manually enter any IPN you want. If you've configured a regex pattern, Part-DB will validate your IPN against it and show an error if it doesn't match.

## IPN Uniqueness

IPNs must be unique across your entire Part-DB instance. Part-DB enforces this constraint:

- When manually entering an IPN, you'll see an error if it already exists
- When auto-append suffix is enabled, duplicate IPNs are automatically made unique
- Existing parts retain their IPNs even if you change their category

## IPNs in Labels and Barcodes

IPNs can be used in label templates through placeholders:

- `[[IPN]]` - The IPN as text
- `[[IPN_BARCODE_C39]]` - IPN as Code 39 barcode
- `[[IPN_BARCODE_C128]]` - IPN as Code 128 barcode
- `[[IPN_BARCODE_QR]]` - IPN as QR code

See the [Labels documentation]({% link usage/labels.md %}) for more information.

## IPNs in Barcode Scanning

Part-DB can scan barcodes containing IPNs to quickly find parts. When a barcode is scanned, Part-DB:
1. Attempts to parse it as an IPN
2. Searches for the part with that IPN
3. Displays the part information

This enables quick inventory operations using barcode scanners.

## IPNs in EDA Integration

When using Part-DB with EDA tools like KiCad, the IPN is automatically added to the component fields as "Part-DB IPN". This creates a direct link between your schematic components and your Part-DB inventory.

See the [EDA Integration documentation]({% link usage/eda_integration.md %}) for more information.

## Best Practices

### Choosing Prefixes

- **Keep them short**: 2-4 characters work well (e.g., "RES", "CAP", "IC")
- **Make them memorable**: Use abbreviations that are obvious (avoid "XYZ" or "ABC")
- **Be consistent**: Use the same style across all categories (all caps or all lowercase)
- **Avoid ambiguity**: Don't use similar prefixes like "IC" and "1C"

### Numbering Schemes

- **Pad with zeros**: Use leading zeros for cleaner sorting (0001, 0042 instead of 1, 42)
- **Leave room for growth**: If you have 50 parts now, use 4 digits (up to 9999) instead of 2
- **Don't encode information**: Let the prefix and category do the work, not the number
- **Sequential is fine**: You don't need gaps - 0001, 0002, 0003 is perfectly valid

### Hierarchical Categories

- **Limit depth**: 2-3 levels is usually sufficient (IC-MCU vs IC-MCU-ARM-STM32)
- **Balance specificity**: More levels = longer IPNs but more precise categorization
- **Consider searching**: Very specific categories are harder to search across

### Changing Your Scheme

- **Plan ahead**: Changing IPN schemes later is difficult
- **Document your convention**: Add your IPN format to your regex help text
- **Existing parts**: Don't feel obligated to renumber existing parts if you change schemes
- **Migration**: Use import/export to batch-update IPNs if needed

## Common Issues and Solutions

### "IPN already exists"

**Problem**: You're trying to use an IPN that's already assigned to another part.

**Solutions**:
- Choose a different number
- Enable "Auto-Append Suffix" to automatically handle duplicates
- Search for the existing part to see if it's a duplicate you should merge

### "IPN doesn't match regex pattern"

**Problem**: Your IPN doesn't follow the configured format.

**Solutions**:
- Check the regex help text to understand the expected format
- Contact your administrator if the regex is too restrictive
- Use the suggested IPNs which are guaranteed to match

### Suggestions not showing

**Problem**: IPN suggestions are empty or not appearing.

**Solutions**:
- Ensure the part has a category assigned
- Check that the category has an IPN prefix defined
- Verify that a fallback prefix is configured in settings
- Save the part first before getting suggestions (for new parts)

### Wrong prefix being suggested

**Problem**: Part-DB suggests an IPN with the wrong prefix.

**Solutions**:
- Check the part's category - suggestions are based on the assigned category
- Verify parent categories and their prefixes if using hierarchical structure
- Set the correct IPN prefix in the category settings
- Use manual entry with your desired prefix

## Example Scenarios

### Simple Electronic Components Inventory

**Setup**:
- Categories: Resistors, Capacitors, ICs, etc.
- Prefixes: RES, CAP, IC
- 4-digit numbering

**Results**:
- `RES-0001` - 10kΩ resistor
- `CAP-0001` - 100nF capacitor
- `IC-0001` - ATmega328

### Professional Lab with Detailed Categories

**Setup**:
- Hierarchical categories: Components > Passive > Resistors > Surface Mount
- Prefixes: COMP, PAS, RES, SMD
- Global prefix: LAB
- 6-digit numbering

**Results**:
- `LAB-COMP-PAS-RES-SMD-000001` - 0805 10kΩ resistor
- `LAB-COMP-PAS-CAP-SMD-000001` - 0805 100nF capacitor

### Makerspace with Mixed Inventory

**Setup**:
- Categories for electronics, mechanical parts, tools
- Simple prefixes: ELEC, MECH, TOOL
- Fallback prefix for miscellaneous: MISC
- 4-digit numbering

**Results**:
- `ELEC-0001` - Arduino Uno
- `MECH-0001` - M3 screw set
- `TOOL-0001` - Soldering iron
- `MISC-0001` - Cable ties

## Environment Variables

IPN settings can be configured via environment variables (useful for Docker deployments):

- `IPN_SUGGEST_REGEX` - Override the regex pattern
- `IPN_SUGGEST_REGEX_HELP` - Override the regex help text
- `IPN_AUTO_APPEND_SUFFIX` - Enable/disable auto-append suffix (boolean)
- `IPN_SUGGEST_PART_DIGITS` - Number of digits for part numbers (integer)
- `IPN_USE_DUPLICATE_DESCRIPTION` - Enable/disable duplicate description matching (boolean)

Example in docker-compose.yaml:
```yaml
environment:
  IPN_SUGGEST_REGEX: "^[A-Z]{3}-\d{4}$"
  IPN_AUTO_APPEND_SUFFIX: "true"
  IPN_SUGGEST_PART_DIGITS: "4"
```

## Related Documentation

- [Getting Started]({% link usage/getting_started.md %}) - Initial setup guide
- [Concepts]({% link concepts.md %}) - Understanding Part-DB concepts
- [Labels]({% link usage/labels.md %}) - Using IPNs in labels
- [EDA Integration]({% link usage/eda_integration.md %}) - IPNs in electronic design tools
