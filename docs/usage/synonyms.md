---
title: Synonym System
layout: default
parent: Usage
nav_order: 13
---

# Synonym System

Part-DB includes a powerful synonym system that allows you to customize the terminology used throughout the application. This is especially useful when using Part-DB in contexts other than electronics, or when you want to adapt the interface to your organization's specific vocabulary.

1. TOC
{:toc}

## What is the Synonym System?

The synonym system allows you to replace Part-DB's standard terminology with your own preferred terms. For example:
- Change "Part" to "Product", "Component", or "Item"
- Change "Category" to "Group", "Type", or "Class"
- Change "Manufacturer" to "Supplier", "Vendor", or "Brand"

These custom terms (synonyms) are applied throughout the user interface, making Part-DB feel more natural for your specific use case.

## Important Notes

{: .warning-title }
> Experimental Feature
>
> The synonym system is currently **experimental**. While it works in most places throughout Part-DB, there may be some locations where the default terms still appear. The synonym system is being continuously improved.

## Configuring Synonyms

To configure synonyms, you need administrator permissions:

1. Navigate to `Tools -> System -> Settings`
2. Find and click on "Synonyms" in the settings menu
3. You'll see the synonym configuration interface

### Adding a Synonym

To add a new synonym:

1. Click the "Add Entry" button in the synonym settings
2. Select the **Type** (element type) you want to create a synonym for
3. Select the **Language** for which the synonym applies
4. Enter the **Singular** form of your synonym
5. Enter the **Plural** form of your synonym
6. Click "Save" to apply the changes

### Available Element Types

You can create synonyms for the following element types:

| Element Type        | Default Term (EN)     | Example Use Cases                             |
|--------------------|-----------------------|-----------------------------------------------|
| **attachment**     | Attachment            | Document, File, Asset                          |
| **attachment_type**| Attachment Type       | Document Type, File Category                   |
| **category**       | Category              | Group, Class, Type, Collection                 |
| **currency**       | Currency              | Monetary Unit, Money Type                      |
| **device**         | Device                | Assembly, System, Machine                      |
| **footprint**      | Footprint             | Package, Form Factor, Physical Type            |
| **group**          | Group                 | Team, Department, Role                         |
| **label_profile**  | Label Profile         | Label Template, Print Template                 |
| **manufacturer**   | Manufacturer          | Brand, Vendor, Supplier, Maker                 |
| **measurement_unit**| Measurement Unit     | Unit of Measure, Unit, Measurement             |
| **parameter**      | Parameter             | Specification, Property, Attribute             |
| **part**           | Part                  | Component, Item, Product, Article, SKU         |
| **part_lot**       | Part Lot              | Stock Item, Inventory Item, Batch              |
| **project**        | Project               | Assembly, Build, Work Order                    |
| **storelocation**  | Storage Location      | Warehouse, Bin, Location, Place                |
| **supplier**       | Supplier              | Vendor, Distributor, Reseller                  |
| **user**           | User                  | Member, Account, Person                        |

## How Synonyms Work

### Translation Mechanism

The synonym system works by integrating with Part-DB's translation system. When you define a synonym:

1. Part-DB creates translation placeholders for the element type
2. These placeholders are available in both capitalized and lowercase forms
3. The placeholders are used throughout the application where these terms appear

### Placeholder Format

Synonyms use special placeholders in translations:

- `[elementtype]` - Singular, lowercase (e.g., "part" → "item")
- `[Elementtype]` - Singular, capitalized (e.g., "Part" → "Item")
- `[[elementtype]]` - Plural, lowercase (e.g., "parts" → "items")
- `[[Elementtype]]` - Plural, capitalized (e.g., "Parts" → "Items")

### Language-Specific Synonyms

Synonyms are language-specific, meaning you can define different terms for different languages:

- English users see: "Component" and "Components"
- German users see: "Bauteil" and "Bauteile"
- French users see: "Composant" and "Composants"

This allows Part-DB to maintain proper multilingual support even with custom terminology.

## Use Cases and Examples

### Non-Electronics Inventory

**Scenario**: Using Part-DB for a library

**Synonyms**:
- Part → Book
- Category → Genre
- Manufacturer → Publisher
- Supplier → Distributor
- Storage Location → Shelf

**Result**: The interface now speaks library language: "Add a new Book", "Select a Genre", etc.

### Manufacturing Environment

**Scenario**: Managing production inventory

**Synonyms**:
- Part → Material
- Category → Material Type
- Part Lot → Batch
- Storage Location → Warehouse Zone
- Device → Assembly

**Result**: The interface uses manufacturing terminology: "Materials", "Batches", "Warehouse Zones"

### Small Business Retail

**Scenario**: Managing store inventory

**Synonyms**:
- Part → Product
- Category → Department
- Manufacturer → Brand
- Supplier → Vendor
- Part Lot → Stock Item
- Storage Location → Store Location

**Result**: The interface matches retail terminology: "Products", "Departments", "Brands"

### Laboratory Setting

**Scenario**: Managing lab supplies and chemicals

**Synonyms**:
- Part → Reagent
- Category → Substance Type
- Manufacturer → Chemical Supplier
- Storage Location → Cabinet
- Part Lot → Bottle

**Result**: Lab-appropriate language: "Reagents", "Substance Types", "Cabinets"

### Educational Makerspace

**Scenario**: Managing shared tools and components

**Synonyms**:
- Part → Resource
- Category → Resource Type
- Storage Location → Area
- Device → Equipment
- Part Lot → Available Unit

**Result**: Educational context: "Resources", "Resource Types", "Areas"

## Managing Synonyms

### Editing Synonyms

To edit an existing synonym:
1. Find the synonym entry in the list
2. Modify the singular or plural form as needed
3. Click "Save" to apply changes

### Removing Synonyms

To remove a synonym:
1. Find the synonym entry in the list
2. Click the "Remove Entry" button (usually a trash icon)
3. Click "Save" to apply changes

After removal, Part-DB will revert to using the default term for that element type and language.

### Bulk Configuration

If you need to set up many synonyms at once (e.g., for a complete custom terminology set):

1. Define all your synonyms in the settings page
2. Each element type can have synonyms in multiple languages
3. Save once when all entries are configured

### Duplicate Prevention

The system prevents duplicate entries:
- You cannot have multiple synonyms for the same element type and language combination
- If you try to add a duplicate, you'll see a validation error
- Edit the existing entry instead of creating a new one

## Best Practices

### Consistency

- **Use consistent terminology**: If you change "Part" to "Product", consider changing "Part Lot" to "Product Item" or similar
- **Think holistically**: Consider how terms relate to each other in your domain
- **Test thoroughly**: Check various pages to ensure your terms make sense in context

### Singular and Plural Forms

- **Provide both forms**: Always define both singular and plural forms
- **Use proper grammar**: Ensure plurals are grammatically correct
- **Consider irregular plurals**: Some terms have non-standard plurals (e.g., "Box" → "Boxes", not "Boxs")

### Language Considerations

- **Match user expectations**: Use terms your users are familiar with in their language
- **Be culturally appropriate**: Some terms may have different connotations in different languages
- **Maintain professionalism**: Choose terms appropriate for your organizational context

### Planning Your Terminology

Before implementing synonyms:

1. **List all terms**: Identify which Part-DB terms don't fit your context
2. **Define replacements**: Decide on appropriate alternatives
3. **Check relationships**: Ensure related terms work together logically
4. **Get feedback**: Consult with users about proposed terminology
5. **Document decisions**: Keep a record of your synonym choices for future reference

## Limitations

### Not All Locations Covered

As an experimental feature, synonyms may not appear in:
- Some error messages
- Technical logs
- Email templates (depending on configuration)
- API responses
- Some administrative interfaces

The development team is working to expand synonym coverage.

### No Automatic Propagation

Synonyms only affect the user interface:
- Database values remain unchanged
- Export files use original terms
- API endpoints keep original names
- URLs and routes remain the same

### Performance Considerations

The synonym system:
- Caches translations for performance
- Minimal performance impact in normal usage
- Cache is automatically updated when synonyms change

## Technical Details

### Cache Management

Synonyms are cached for performance:
- Cache is automatically cleared when synonyms are saved
- No manual cache clearing needed
- Changes appear immediately after saving

### Translation Priority

When displaying text, Part-DB checks in this order:
1. Synonym (if defined for current language and element type)
2. Standard translation (from translation files)
3. Fallback to English default

### Environment Variables

Currently, synonyms can only be configured through the web interface. Future versions may support environment variable configuration.

## Troubleshooting

### Synonyms Not Appearing

**Problem**: You've configured synonyms but still see original terms.

**Solutions**:
- Clear your browser cache and reload the page
- Check that you've configured the synonym for the correct language
- Verify that you saved the settings after adding the synonym
- Remember this is an experimental feature - some locations may not be covered yet

### Inconsistent Terminology

**Problem**: Some pages show your synonym, others show the original term.

**Solutions**:
- This is expected behavior for the experimental feature
- Check if you've defined both singular and plural forms
- Report inconsistencies to help improve the system

### Wrong Language Displaying

**Problem**: Seeing synonyms from the wrong language.

**Solutions**:
- Check your user language preference in user settings
- Verify you've configured synonyms for the correct language code
- Ensure the language code matches exactly (e.g., "en" not "en_US")

### Synonyms Lost After Update

**Problem**: Synonyms disappeared after updating Part-DB.

**Solutions**:
- Check the settings page - they should still be there
- Database migrations preserve synonym settings
- If truly lost, restore from backup or reconfigure

## Future Enhancements

The synonym system is under active development. Planned improvements include:
- Coverage of more interface elements
- Synonym suggestions based on common use cases
- Import/export of synonym configurations
- Synonym templates for different industries
- More granular control over term usage

## Related Documentation

- [Getting Started]({% link usage/getting_started.md %}) - Initial Part-DB setup
- [Configuration]({% link configuration.md %}) - System configuration options
- [Concepts]({% link concepts.md %}) - Understanding Part-DB terminology

## Feedback

Since the synonym system is experimental, feedback is valuable:
- Report locations where synonyms don't appear
- Suggest new element types that should support synonyms
- Share your use cases to help improve the system
- Report bugs or unexpected behavior

You can provide feedback through:
- GitHub issues on the Part-DB repository
- Community forums and discussions
- Direct contact with the development team
