---
title: External part links
layout: default
parent: Usage
---

# External part links

External part links let you define quick links to external services (e.g. a distributor search, a manufacturer's
product page, or a datasheet portal), which are then shown on the part detail page for every part where the link can
be resolved.

## Configuration

External part links are configured under **System settings → External part links**. You need administrator
permissions to edit this setting.

Click "Add link" to add a new row. Each link has the following fields:

| Field | Description |
|-------|--------------|
| Name | The label shown on the link's badge. |
| Source type | `Template` builds the URL from a template with placeholders (see below). `Parameter` reads the complete URL from one of the part's parameters. |
| Source | For `Template`: the URL template, e.g. `https://www.trustedparts.com/de/search/{mpn}`. For `Parameter`: the exact name of the part parameter that holds the URL. |
| Icon | The icon shown next to the link (e.g. search, shopping cart, globe, file). |
| Open in new tab | Whether clicking the link opens it in a new browser tab. |
| Enabled | Whether the rule is active. |

### Template placeholders

A URL template may use the following placeholders, which are replaced with the part's own values (URL-encoded):

- `{mpn}` - the part's manufacturer product number
- `{manufacturer}` - the name of the part's manufacturer
- `{ipn}` - the part's internal part number
- `{name}` - the part's name
- `{category}` - the name of the part's category
- `{footprint}` - the name of the part's footprint
- `{id}` - the ID of the part in this Part-DB instance (useful for deep links from external systems back to a part)

If a template uses a placeholder that has no value for a given part (e.g. `{mpn}` on a part with no manufacturer
product number set), the link is simply not shown for that part - it never links to an incomplete URL.

### Parameter source

With source type `Parameter`, the link's URL is read directly from a part parameter with the exact name given in
"Source". This is useful when you already store a specific URL as a parameter (e.g. from an information provider) and
just want to surface it as a quick link. If no parameter with that name exists, or more than one does, the link is
not shown.

## Security notes

Only `https://` URLs are ever rendered as a link, whether they come from a template or from a parameter. Links that
would resolve to any other scheme (e.g. `javascript:` or `data:`) or to an invalid URL are silently skipped instead of
being shown.
