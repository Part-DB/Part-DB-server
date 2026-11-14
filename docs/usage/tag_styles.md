---
title: Tag styles
layout: default
parent: Usage
---

# Tag styles

Part-DB shows the tags assigned to a part as colored badges, e.g. in the part list and on the part detail page. By
default, all tags use the same neutral color. With tag styles, an administrator can define rules that render specific
tags or tag prefixes with a semantic color instead, so important or critical tags stand out at a glance.

## Configuration

Tag styles are configured under **System settings → Tag styles**. You need administrator permissions to edit this
setting.

Click "Add rule" to add a new row. Each rule has three fields:

| Field | Description |
|-------|--------------|
| Match type | `exact` matches the whole tag exactly. `prefix` matches every tag that starts with the given pattern. |
| Pattern | The text to match against, e.g. `Risk:Review` (exact) or `Risk:` (prefix). |
| Color | One of the Bootstrap semantic colors: `primary`, `secondary`, `info`, `success`, `warning`, `danger`, `light`, `dark`, or `default` to keep the tag's normal appearance. |

A small preview badge next to each rule shows the resulting color. Save the settings and the rules take effect
everywhere tags are shown as badges.

## How rules are resolved

At most one rule is ever applied to a given tag:

1. An `exact` match always wins over any `prefix` match.
2. Among `prefix` matches, the longest (most specific) matching pattern wins.
3. If multiple rules still tie, the rule listed first wins.

Tags that don't match any rule keep their previous, unstyled appearance. The existing tag filter and search behavior
are not affected by tag styles - they only change how a tag is rendered.
