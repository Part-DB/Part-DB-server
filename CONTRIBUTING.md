# How to contribute

Thank you for considering contributing to Part-DB!
Please read the text below, so your contributed content can be incorporated into Part-DB easily.

You can contribute to Part-DB in various ways:
* Report bugs and request new features via [issues](https://github.com/Part-DB/Part-DB-server/issues)
* Improve translations (via https://part-db.crowdin.com/part-db)
* Improve code (either PHP, Javascript or HTML templates) by creating a [pull request](https://github.com/Part-DB/Part-DB-server/pulls)

## Translations

### How the translation system works
Part-DB uses Symfony's built-in [Translation component](https://symfony.com/doc/current/translation.html). Strings are never hardcoded in
templates or PHP code; instead, code references a translation key (e.g. `part.info.title`) and the translator resolves it to the text for
the current locale.

The translation catalogs live in the [`translations`](translations) directory as XLIFF 2.0 files (`.xlf`), named `<domain>.<locale>.xlf`,
for example `messages.de.xlf` or `validators.fr.xlf`. The main domains are:
* `messages`: the bulk of the back-office UI strings
* `frontend`: strings that are also needed in JavaScript (dumped into a JS bundle at build time)
* `security`: login and authentication related strings
* `validators`: validation constraint error messages

A single entry looks like this:
```xml
<unit id="x_wTSQS" name="attachment_type.caption">
  <segment state="translated">
    <source>attachment_type.caption</source>
    <target>File types for attachments</target>
  </segment>
</unit>
```
English (`en`) is the fallback locale: if a key is missing in your language, the English string is shown instead, so you never have to
translate everything at once.

### How to contribute translations
The recommended way to create or improve translations is via the online platform [Crowdin](https://part-db.crowdin.com/part-db).
Register an account there and join the Part-DB team; no coding knowledge or local setup is required. If you want to start translation for a
new language that does not have an entry on Crowdin yet, send a message to `@jbtronics`.

Part-DB uses translation keys (e.g. `part.info.title`) that are sorted by their usage, so you will most likely have to look up how the key
was translated in other languages (this is possible via the "Other languages" dropdown in the translation editor).

If you are working on the code itself and add new strings, add the new key (with an English source string) to the relevant `messages.en.xlf`
(or other domain) file; Crowdin will pick it up for translation into the other languages automatically.

### Placeholders
Translated strings can contain placeholders that are replaced with dynamic values at runtime. They use Symfony's `%name%` syntax, e.g.:
```xml
<target>Do you really want to delete %name%?</target>
```
Keep placeholders exactly as they appear in the source string (same spelling, same `%...%` delimiters) — the code passes values by matching
these placeholder names, so a renamed or missing placeholder will not be substituted.

### Synonym placeholders
Part-DB lets administrators rename core entity types (e.g. call "Storage Location" something else in their organization) via the "Synonyms"
system settings, without needing new translation files. To make this possible, translation strings use a second, special kind of
placeholder — a bracketed entity-type token — instead of hardcoding words like "Part" or "Category":

* `[part]` / `[category]` / `[storage_location]` / … — singular, lowercase (for use mid-sentence)
* `[Part]` / `[Category]` / `[Storage_location]` / … — singular, capitalized (for use at the start of a sentence)
* `[[part]]` / `[[category]]` / … — plural, lowercase
* `[[Part]]` / `[[Category]]` / … — plural, capitalized

These tokens are resolved automatically for every translated string, based on the current synonym settings (falling back to the default
English-derived name if no synonym is configured). When translating, use these tokens instead of the literal word for the entity type, so
that a user who renamed "Part" to something else in their instance still sees a consistent, correctly-cased name everywhere. Do not
translate the text inside the brackets — only the tokens listed below are recognized.

#### How resolution works
The tokens are substituted after the surrounding string has been translated, so the same source string resolves differently depending on
whether the administrator has configured a synonym:

| Source string | Default resolution (no synonym configured) | With `part` renamed to "Component" |
|---|---|---|
| `Create new [part]` | Create new part | Create new component |
| `Do you really want to delete this [part]?` | Do you really want to delete this part? | Do you really want to delete this component? |
| `[[Part]] with [category]` | Parts with category | Components with category |

#### List of available placeholders
Every entry below is generated from the same entity type, following the `[type]` / `[Type]` / `[[type]]` / `[[Type]]` pattern described
above — only the singular/plural base text (default, English) is listed for brevity; the capitalized forms (`[Type]`, `[[Type]]`) are the
same text with just the first letter capitalized, unless noted otherwise.

| Entity type | Singular — `[type]` | Plural — `[[type]]` |
|---|---|---|
| `attachment` | attachment | attachments |
| `attachment_type` | attachment type | attachment types |
| `category` | category | categories |
| `currency` | currency | currencies |
| `footprint` | footprint | footprints |
| `group` | group | groups |
| `label_profile` | label profile | label profiles |
| `manufacturer` | manufacturer | manufacturers |
| `measurement_unit` | measurement unit | measurement units |
| `orderdetail` | order detail | order details |
| `parameter` | parameter | parameters |
| `part` | part | parts |
| `part_association` | part association | part associations |
| `part_custom_state` | custom part state | custom part states |
| `part_lot` | part lot | part lots |
| `pricedetail` | price detail | price details |
| `project` | project | projects |
| `project_bom_entry` | bom entry | bom entries |
| `storage_location` | storage location | storage locations |
| `supplier` | supplier | suppliers |
| `user` | user | users |
| `bulk_info_provider_import_job` | bulk info provider import | bulk info provider imports |
| `bulk_info_provider_import_job_part` | bulk import job part | bulk import job part |

For example, `part_lot` gives you `[part_lot]` → "part lot", `[Part_lot]` → "Part lot", `[[part_lot]]` → "part lots" and
`[[Part_lot]]` → "Part lots".

A couple of entries don't follow the plain "capitalize the first letter" rule and are worth calling out so you don't get confused when you
see them in a source string:
* `[Project_bom_entry]` resolves to "BOM entry" (not "Project bom entry") and `[[Project_bom_entry]]` to "BOM entries".
* `[Bulk_info_provider_import_job_part]` resolves to "Bulk Import Job Part" (title-cased), while its plural form `[[...]]` currently
  resolves to the same singular text ("bulk import job part") rather than an actual plural — this is a known inconsistency, not something
  to replicate when adding new entity types.

## Project structure
Part-DB uses Symfony's recommended [project structure](https://symfony.com/doc/current/best_practices.html).
Interesting folders are:
* `public`: Everything in this directory will be publicly accessible via web. Use this folder to serve static images.
* `assets`: The frontend assets are saved here. You can find the JavaScript and CSS code here.
* `src`: Part-DB's PHP code is saved here. Note that the subdirectories are structured by the classes' purposes (so use `Controller` for Controllers, `Entity` for Database models, etc.)
* `translations`: The translations used in Part-DB are saved here.
* `templates`: The templates (HTML) that are used by Twig to render the different pages. Email templates are also saved here.
* `tests/`: Tests that can be run by PHPUnit.

## Admin CRUD system

Many structural entity types (Category, Footprint, Supplier, …) share a generic Create/Read/Update/Delete
interface. This section explains how the system works and what you need to do to add a new entity type to it.

### Key classes

| Class | Location | Purpose |
|---|---|---|
| `#[EntityConfig]` | `src/Entity/Attribute/EntityConfig.php` | Generic entity metadata: `routeBase` (used to build URLs) and auto-detected attachment/parameter classes (from Doctrine associations). Place on any entity that participates in the CRUD or attachment/parameter systems — including `Part`. |
| `#[AdminCrud]` | `src/Entity/Attribute/AdminCrud.php` | Admin-CRUD overlay: `formClass`, `templatePath`, `showParametersTab`. Place alongside `#[EntityConfig]` on entities managed by the generic admin interface. |
| `EntityConfigRegistry` | `src/Services/EntitySystem/` | Discovers all entities with `#[EntityConfig]` and exposes `EntityConfigData` (routeBase, attachment class, parameter class). Used by `EntityURLGenerator` to derive route names. |
| `AdminCrudRegistry` | `src/Services/AdminPages/` | Filters the above to entities that also have `#[AdminCrud]` and exposes `AdminCrudConfig`. |
| `AdminCrudConfig` | `src/Services/AdminPages/` | Value object combining both attributes for one entity. |
| `AdminPageHandler` | `src/Services/AdminPages/` | Core CRUD service: handles edit, new, and delete flows, calls extensions, renders Twig. |
| `AdminPageController` | `src/Controller/AdminPages/` | Thin controller that delegates to `AdminPageHandler`. Routes are generated dynamically by `AdminPageRouteLoader`. |
| `AdminPageExtensionInterface` | `src/Services/AdminPages/` | Per-entity hook interface. Implement and tag `app.admin_page_extension` to add custom behaviour (flash messages, permission checks, extra template vars, form options). |

### Route naming convention

Routes for admin-managed entities follow the pattern `{routeBase}_{action}`:

| Action | Suffix | Example (`routeBase = category`) |
|---|---|---|
| Edit | `_edit` | `category_edit` |
| New | `_new` | `category_new` |
| Clone | `_clone` | `category_clone` |
| Delete | `_delete` | `category_delete` |

`Part` and `Project` additionally have a `_info` route used as their info/detail page; all other entities fall back to `_edit` for `infoURL`.

### Adding a new admin-managed entity

1. **Add both attributes to the entity class:**
   ```php
   #[EntityConfig(routeBase: 'my_entity')]
   #[AdminCrud(formClass: MyEntityAdminForm::class)]
   class MyEntity extends AbstractStructuralDBElement { … }
   ```
   - `routeBase` drives URL generation and the default template path (`admin/{routeBase}_admin.html.twig`).
   - Attachment and parameter classes are detected automatically from the Doctrine `attachments` / `parameters` associations — no need to declare them in the attribute.

2. **Create the form class** in `src/Form/AdminPages/`. Extend `AbstractType` and return `BaseEntityAdminForm::class` from `getParent()`. Add only the fields specific to this entity; the base form's fields (name, comment, attachments, parameters) are inherited automatically via Symfony's form type inheritance chain.

3. **Create the Twig template** at `templates/admin/{routeBase}_admin.html.twig`. Extend `admin/base_admin.html.twig`.

4. **Clear the cache** so route discovery and entity config discovery pick up the new annotations:
   ```bash
   php bin/console cache:clear
   ```

5. **Optional — add an extension** for custom behaviour: implement `AdminPageExtensionInterface` (extend `AbstractAdminPageExtension` for convenience), tag the service with `app.admin_page_extension`, and set `getEntityClass()` to return your entity class. Available hooks: `beforeEdit`, `beforeNew`, `beforeDelete`, `onEditFormValid`, `onNewFormValid`, `checkDelete`, `formOptions`, `additionalTemplateVars`.

# Development environment
See [DEVELOPMENT.md](DEVELOPMENT.md) for setting up a development environment.

## Coding style
Code should follow the [PSR-12 Standard](https://www.php-fig.org/psr/psr-12/) and Symfony's [coding standards](https://symfony.com/doc/current/contributing/code/standards.html).

Part-DB uses [Easy Coding Standard](https://github.com/symplify/easy-coding-standard) to check and fix coding style violations:
* To check your code for valid code style, run `vendor/bin/ecs check src/`
* To fix violations, run `vendor/bin/ecs check src/ --fix` (please check afterwards if the code is still valid)

## GitHub actions
Part-DB uses GitHub Actions to run various tests and checks on the code:
* Yarn dependencies can compile
* PHPUnit tests run successfully
* Config files, translations, and templates have valid syntax
* Doctrine schema is valid
* No known vulnerable dependencies are used
* Static analysis is successful (phpstan with `--level=2`)

Further, the code coverage of the PHPUnit tests is determined and uploaded to [CodeCov](https://codecov.io/gh/Part-DB/Part-DB-server).
