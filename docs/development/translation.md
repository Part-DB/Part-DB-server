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

In Twig (short syntax):
```twig
{{ 'part.info.title'|trans }}
```

In Twig (block syntax for longer text):
```twig
{% trans %}part.info.title{% endtrans %}
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

**Note for inflected languages**: In languages like German where words are inflected (e.g., case declensions), synonyms should be defined in the nominative case (the standard/dictionary form). The placeholders will be substituted as-is, so translations need to be written to work with the nominative form.

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

**Example 1**: `"Click here to create a new [Category]"`
- Default: "Click here to create a new Category"
- With synonym (Category → "Product Type"): "Click here to create a new Product Type"

**Example 2**: `"You have 5 [[part]] in 3 [[category]]"`
- Default: "You have 5 parts in 3 categories"
- With synonyms (Part → "Component", Category → "Group"): "You have 5 components in 3 groups"

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

### Guidelines for Using Synonyms

When writing translation strings:
- Use placeholders for entity types (✅ `"Delete this [category]?"` ❌ `"Delete this category?"`)
- Match case to context: capitalized (`[Type]`, `[[Type]]`) at sentence start, lowercase (`[type]`, `[[type]]`) mid-sentence
- Use singular for single items, plural for multiple items
- Only use for actual entity type names, not for actions or feature names

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
