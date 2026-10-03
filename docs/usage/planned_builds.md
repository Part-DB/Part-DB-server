---
layout: default
title: Planned builds
parent: Usage
---

# Planned builds

A **planned build** reserves the parts that a project needs for a future build, without
removing them from your inventory yet. This way you can see which stock is already earmarked for something, and other
withdrawals or builds can not use it up by accident.

## Planning a build

Open a project and use the **Plan build** tab next to **Build**. Enter the number of builds you want to plan for and
confirm the form on the next page. There you can choose from which stock lot of every part the stock should be reserved,
and in which amount. By default, the stock is reserved from the lots in the order they are listed.

Planning always works, even if there is not enough stock right now: in that case only what is available gets reserved
(partially), and the rest is taken from stock when the build is made. A part that has no stock lot at all (yet) is listed
too, with nothing reserved for it.

Reserving stock never changes any amount. It only changes what is **available**:

* **Total** is the amount that is physically in stock (as before).
* **Reserved** is the amount that is earmarked for planned builds.
* **Available** is Total minus Reserved. Only this amount can be withdrawn, moved or used by a normal build.

You can see these numbers for every lot on the part page, and they can be shown as additional columns in the parts
tables.

## Planned builds list

All planned builds are listed in the **Planned builds** entry of the *Projects* sidebar. Opening one shows the parts it
needs, how much of each is needed, reserved (or used, once built) and in stock, and from which lots. Click a column
header to sort by it. Rows are highlighted if a part is not (fully) reserved, or can currently not be provided at all.

Under *Missing parts* you find all planned builds that can currently not be built because some of their parts are
missing, so you know what you have to order.

## Building or cancelling

* **Build** withdraws the parts the project needs from the stock, adds the builds to the build part of the project (if
  configured) and marks the planned build as *Built*. The planned build is kept as a record: you can see who planned
  and who built it, when this happened, and which parts were used. If a part was not (fully) reserved, the missing
  amount is taken from the stock that is available at that time, so a plan that fell short while planning can be built
  once the part was restocked. If there is still not enough stock, the build is refused.
* **Cancel plan** releases all reservations again. The stock was never touched, so it is simply available again.

Parts, lots and BOM entries that are used by a planned build which is still *planned* can not be deleted. Cancel or
build the planned build first.

## Permissions

* Planned builds have their own permission group (`Planned builds`) with the usual read / edit / create / delete
  operations. Planning a build needs *create*, building or cancelling needs *edit*.
* Reserving stock needs `Reserve parts for planned builds`, cancelling needs `Release reservations of planned builds`
  (both in the *Parts stock* group), and building needs the permission to withdraw parts.
