---
layout: default
title: Component image generator
parent: Usage
---

# Component image generator

Part-DB can **draw schematic-style pictures of passive components** (resistors, SMD resistors and
ceramic capacitors) from their value, and attach them to your parts. This is handy when you import a
bulk assortment (for example a resistor or capacitor kit) that arrives with no pictures and only a
value in the name — instead of blank thumbnails you get a clean, consistent illustration for every part.

There are two ways to use it:

* the **Value calculator** tool, to draw a single component interactively, and
* the **Generate component images** bulk action, to illustrate a whole selection of parts at once.

Both produce a lightweight, transparent **SVG** that is attached as the part's picture (so it stays
crisp at any size). The images are illustrations, not photographs — they show the colour-band code,
capacitor code, or SMD marking together with dimension callouts.

## Value calculator

Open **Tools → Value calculator** (requires the `Value calculator` permission). It has three tabs:

* **Resistor** – enter a resistance (or pick the colour bands) and get a 4/5/6-band axial resistor.
  The tolerance, power rating and temperature coefficient (ppm) are reflected in the bands and body size.
* **SMD resistor** – enter a resistance and package (0402, 0603, 0805 …) to get a chip resistor with
  the 3-digit, 4-digit or EIA-96 marking.
* **Capacitor** – enter a capacitance to get a radial disc (or MLCC blob) ceramic capacitor with the
  printed code, optional voltage line and tolerance letter.

Every field is linked: editing the value updates the code (and vice-versa), and the picture redraws
live. You can change the body colour, size, lead length and other appearance options.

### Attaching to a part

When a part has no picture, its info page shows a **Generate image** button in the picture area.
This opens the calculator in a dialog, pre-filled from the part's value. Click **Attach** and the
drawing is saved as the part's picture without leaving the page.

Every tab also has a **Download SVG** button, which saves the currently shown picture as a standalone
`.svg` file — useful if you just want the image itself (for a datasheet, label, or other document)
without attaching it to a part.

## Bulk "Generate component images"

To illustrate many parts at once, select them in any parts table and choose
**Actions → Generate component images**.

Part-DB classifies each selected part as a resistor, SMD resistor or capacitor, reads its value and
other properties, and shows a review table with a **live preview** for every part. You can adjust any
value before writing, then:

* **Attach pictures** – saves the generated image as each checked part's picture, or
* **Write KiCad settings** – writes the suggested KiCad symbol / footprint / reference prefix to each
  checked part (see [EDA / KiCad integration](eda_integration.md)).

Only parts **without a picture** are listed by default. If some of your selection already have a
picture, a notice offers to **re-generate / overwrite** them (see [Overwriting](#overwriting-existing-pictures)).

### What is auto-detected — and how to get the best results

Each property is read from the part's **parameters** first, then from its **name and description**.
A part is only listed if it has no picture yet and a value can be read from it. To improve detection,
add any of the following (a plain CSV import usually only fills the name/description, so putting the
value in the name is the most reliable option):

| Property | Add a parameter named… | …or write in the name / description |
|----------|------------------------|-------------------------------------|
| **Value** (required) | `Resistance` / `Capacitance` (unit Ω or F) | `10nF`, `0.1µF`, `4n7` · `4k7`, `470R`, `10k`, `1M` |
| **Rated voltage** (capacitors) | `Voltage` | `50V`, `100V` |
| **Tolerance** | `Tolerance` | `±5%`, `1%`, `0.1%` |
| **Power** (through-hole resistors) | — | `0.25W`, `1/4W`, `1W` |
| **Temp. coefficient** (resistors) | — | `50ppm`, `±25 ppm/°C` |
| **Lead pitch** (capacitors) | `Pitch` / `RM` / `Lead spacing` | `pitch 5mm`, `RM5` |
| **Body diameter** (capacitors) | `Diameter` | `⌀5mm` |
| **SMD size** (resistors) | the assigned footprint | `0402`, `0603`, `0805`, `1206`, … |
| **Body colour** | — | `blue body`, `beige`, `green`, … |

How the properties are drawn depends on the component type, matching real-world conventions:

* **Capacitor** – tolerance shows as the letter after the code (`104K` = ±10%); voltage as a printed line.
* **Through-hole resistor** – tolerance and temperature coefficient are colour bands; power sets the body size.
* **SMD resistor** – tolerance is expressed by the marking system (4-digit for 1 %, 3-digit for looser);
  power/voltage are not printed on a chip, so its size comes from the package instead.

### Overwriting existing pictures

By default, parts that already have a picture are skipped. Use the **Re-generate / overwrite** button
to include them anyway. When you then attach a picture to such a part, the new image becomes the
part's preview and replaces any **previously generated** image — manually uploaded photos are kept.
