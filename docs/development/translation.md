---
title: Translation System
layout: default
parent: Development
nav_order: 1
---

# Translation System for Contributors

This document explains how Part-DB's translation system works and how contributors can effectively use it.

## Overview

Part-DB uses Symfony's translation system with XLIFF format files. Translations are managed through [Crowdin](https://part-db.crowdin.com/part-db), but understanding the system's features is important for contributors working on code or translations.

## Basic Translation Approach

Part-DB uses translation keys rather than translating English strings directly. For example:

- Translation key: `part.info.title`
- English: "Part Information"
- German: "Bauteil-Informationen"

This approach has several advantages:
- Keys remain stable even if the English text changes
- The same key can have different translations in different contexts
- Keys are organized hierarchically by feature/context

### Using Translations in Code

In PHP/Twig:
```twig
{{ 'part.info.title'|trans }}
```

In PHP:
```php
$translator->trans('part.info.title')
```

## Type Synonyms and Placeholders

Part-DB includes a powerful synonym system that allows customizing the names of entity types (like "Category", "Part", "Manufacturer") throughout the interface. This is particularly useful for organizations that prefer different terminology.

### How Synonyms Work

Administrators can define custom names for entity types in the settings (Settings → Synonyms). These custom names are then automatically substituted throughout the application using translation placeholders.

### Placeholder Syntax

The synonym system uses special placeholders in translation strings that get replaced with the appropriate entity type name:

| Placeholder | Meaning | Example Output (Default) | Example Output (with synonym) |
|------------|---------|-------------------------|------------------------------|
| `[Type]` | Singular, capitalized | "Category" | "Product Group" |
| `[[Type]]` | Plural, capitalized | "Categories" | "Product Groups" |
| `[type]` | Singular, lowercase | "category" | "product group" |
| `[[type]]` | Plural, lowercase | "categories" | "product groups" |

Where `Type` is the element type name (e.g., `category`, `part`, `manufacturer`, etc.).

### Available Element Types

The following element types support synonyms:

- `category` - Part categories
- `part` - Electronic parts/components
- `manufacturer` - Component manufacturers
- `supplier` - Component suppliers
- `storage_location` - Physical storage locations (also called `storelocation`)
- `footprint` - PCB footprints
- `attachment_type` - File attachment types
- `measurement_unit` - Units of measurement
- `currency` - Currencies
- `project` - Projects
- And many others (see `ElementTypes` enum in code)

### Examples

#### Basic Usage

Translation string:
```
"Click here to create a new [Category]"
```

Default output:
```
"Click here to create a new Category"
```

With custom synonym (Category → "Product Type"):
```
"Click here to create a new Product Type"
```

#### Multiple Placeholders

Translation string:
```
"This [part] belongs to [category] 'Electronics'"
```

Default output:
```
"This part belongs to category 'Electronics'"
```

With custom synonyms:
```
"This component belongs to product group 'Electronics'"
```

#### Plural Usage

Translation string:
```
"You have 5 [[part]] in 3 [[category]]"
```

Default output:
```
"You have 5 parts in 3 categories"
```

With custom synonyms (Part → "Component", Category → "Group"):
```
"You have 5 components in 3 groups"
```

#### Case Variations

Translation string:
```
"Select a [Category] to view its [[part]]"
```

This demonstrates:
- `[Category]` - Capitalized singular (starts sentence or emphasizes)
- `[[part]]` - Lowercase plural (mid-sentence)

### Technical Implementation

The synonym system is implemented through several components:

1. **SynonymSettings** (`src/Settings/SynonymSettings.php`): Stores user-defined synonyms
2. **ElementTypeNameGenerator** (`src/Services/ElementTypeNameGenerator.php`): Generates localized labels
3. **RegisterSynonymsAsTranslationParametersListener** (`src/EventListener/RegisterSynonymsAsTranslationParametersListener.php`): Registers placeholders globally

The system automatically:
- Generates placeholders for all element types at application startup
- Handles capitalization properly for different languages
- Falls back to default translations if no synonym is defined
- Caches placeholder values for performance

### Guidelines for Using Synonyms in Translations

When writing or updating translation strings:

1. **Use synonyms for entity type references**: When referring to entity types like categories, parts, manufacturers, etc., use the synonym placeholders instead of hardcoding the type name.

   ✅ Good: `"Delete this [category]?"`
   
   ❌ Bad: `"Delete this category?"`

2. **Match the case to context**:
   - Use capitalized forms (`[Type]`, `[[Type]]`) at the start of sentences or for emphasis
   - Use lowercase forms (`[type]`, `[[type]]`) in the middle of sentences

3. **Choose singular vs. plural appropriately**:
   - Use singular for single items: `"Create new [part]"`
   - Use plural for multiple items or lists: `"Available [[part]]"`

4. **Consistency**: Be consistent with placeholder usage across similar translation strings

5. **Don't overuse**: Only use placeholders for actual entity type names. Don't use them for:
   - Action verbs (use regular translations)
   - Specific feature names
   - UI element names that aren't entity types

### Testing Synonyms

To test how your translations work with synonyms:

1. Go to Settings → Synonyms in Part-DB
2. Define custom synonyms for the types you're testing
3. Navigate to pages that use your translation strings
4. Verify the synonyms appear correctly with proper capitalization and plurality

## Translation Parameters

In addition to synonym placeholders, Part-DB uses standard Symfony translation parameters for dynamic values:

```
"You have %count% parts selected"
```

Parameters are passed when calling the translation:
```php
$translator->trans('parts.selected', ['%count%' => 5])
```

Important: Parameters use `%paramName%` syntax, while synonym placeholders use `[Type]` or `[[Type]]` syntax.

## Translation Files

Translation files are located in the `translations/` directory and use XLIFF format:

- `messages.en.xlf` - English translations
- `messages.de.xlf` - German translations
- `messages.{locale}.xlf` - Other languages

The XLIFF format includes:
- Source key (translation key)
- Target (translated text)
- Notes (file locations where the key is used)

## Best Practices

1. **Use translation keys, not hardcoded text**: Always use translation keys for any user-facing text
2. **Organize keys hierarchically**: Use dots to namespace keys (e.g., `part.info.title`)
3. **Use synonym placeholders for entity types**: This gives administrators flexibility
4. **Test with different synonym configurations**: Ensure your text works with custom names
5. **Be consistent**: Follow existing patterns for similar functionality
6. **Check other languages**: Look at how similar keys are translated in other languages (via Crowdin's "Other languages" dropdown)

## Resources

- [Crowdin Part-DB Project](https://part-db.crowdin.com/part-db)
- [Symfony Translation Documentation](https://symfony.com/doc/current/translation.html)
- [Contributing Guide](../../CONTRIBUTING.md)
