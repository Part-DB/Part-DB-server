# KiCad Footprint & Symbol Populate Command

A Symfony console command for Part-DB that bulk-populates KiCad footprint paths on Footprint entities and KiCad symbol paths on Category entities.

## Overview

Part-DB's KiCad EDA integration allows parts to inherit KiCad metadata from their Footprint and Category entities. This command automates populating those fields based on standard KiCad library paths.

**What it does:**
- Maps footprint names (e.g., `SOT-23`, `0805`, `DIP-8`) to KiCad footprint library paths
- Maps category names (e.g., `Resistors`, `Capacitors`, `LED`) to KiCad symbol library paths
- Checks alternative names on entities when the primary name doesn't match
- Only updates empty values by default (use `--force` to overwrite)
- Supports dry-run mode to preview changes
- Supports custom mapping files to override or extend the built-in defaults

## Installation

The command is included with Part-DB. No additional installation steps needed.

### Verify installation

```bash
php bin/console list partdb:kicad
```

You should see:
```
partdb:kicad:populate   Populate KiCad footprint paths and symbol paths for footprints and categories
```

## Usage

### List current values

See what's currently in the database:

```bash
php bin/console partdb:kicad:populate --list
```

### Preview changes (recommended first step)

See what would be updated without making changes:

```bash
php bin/console partdb:kicad:populate --dry-run
```

### Apply changes

Update all empty footprint and category KiCad fields:

```bash
php bin/console partdb:kicad:populate
```

### Options

| Option | Description |
|--------|-------------|
| `--list` | List all footprints and categories with their current KiCad values |
| `--dry-run` | Preview changes without applying them |
| `--footprints` | Only update footprint entities |
| `--categories` | Only update category entities |
| `--force` | Overwrite existing values (default: only fills empty values) |
| `--mapping-file <path>` | Path to a JSON file with custom mappings (merges with built-in defaults) |

### Examples

```bash
# Only update footprints, preview first
php bin/console partdb:kicad:populate --footprints --dry-run

# Only update categories
php bin/console partdb:kicad:populate --categories

# Force overwrite all values (careful!)
php bin/console partdb:kicad:populate --force

# Use a custom mapping file
php bin/console partdb:kicad:populate --mapping-file my_mappings.json
```

## Name Matching

### Footprints (exact match)
Footprint names are matched exactly against the mapping keys. If the primary entity name doesn't match, the command also checks **alternative names** configured on the Footprint entity.

For example, if a Footprint is named "SOT23" but has an alternative name "SOT-23", the mapping for "SOT-23" will be used.

### Categories (pattern match)
Category names are matched using case-insensitive substring matching. A category named "Zener Diodes" will match the pattern "Zener". Order matters — more specific patterns are checked first. Alternative names on Category entities are also checked.

## Custom Mapping Files

You can provide a JSON file with `--mapping-file` to override or extend the built-in defaults. User mappings take priority over built-in ones.

### JSON format

```json
{
    "footprints": {
        "MyCustomPackage": "MyLibrary:MyFootprint",
        "0805": "Capacitor_SMD:C_0805_2012Metric"
    },
    "categories": {
        "Sensor": "Sensor:Sensor_Temperature",
        "MCU": "MCU_Microchip:PIC16F877A"
    }
}
```

Both `footprints` and `categories` keys are optional — you can provide just one.

A reference file with all built-in defaults exported as JSON is available at [`default_mappings.json`](default_mappings.json). You can copy this file as a starting point for your own customizations.

## Built-in Mappings

### Footprints (~100 mappings)

| Package Type | Examples |
|--------------|----------|
| SOT packages | SOT-23, SOT-23-5, SOT-23-6, SOT-223, SOT-89, SOT-323, SOT-363 |
| TO packages | TO-92, TO-220, TO-220AB, TO-247-3, TO-252, TO-263 |
| SOIC/TSSOP/MSOP | SOIC-8, SOIC-16, TSSOP-16, MSOP-16 |
| DIP | DIP-4 through DIP-40 |
| QFN/DFN | QFN-8 through QFN-48, DFN-2, DFN-6, DFN-8 |
| TQFP/LQFP | TQFP-32 through TQFP-100, LQFP variants |
| Chip sizes | 0201, 0402, 0603, 0805, 1206, 1210, 2512, etc. |
| Diode packages | SOD-123, SOD-323, SMA, DO-35, DO-41, etc. |
| Electrolytic caps | SMD (D4-D10mm), Through-hole (D5-D12.5mm) |
| Tantalum caps | Case A through Case E |
| LED packages | 3mm, 5mm, 0603, 0805, WS2812B |
| Crystal packages | HC-49, HC-49/S, HC-49/US |
| Connectors | USB-A/B/Mini/Micro/C, pin headers (1x2 to 2x20) |
| SIP packages | SIP-3 through SIP-5 |

### Categories (~35 mappings)

| Component Type | KiCad Symbol |
|----------------|--------------|
| Resistors | `Device:R` |
| Capacitors | `Device:C` |
| Electrolytic/Tantalum | `Device:C_Polarized` |
| Inductors | `Device:L` |
| Diodes | `Device:D` |
| Zener Diodes | `Device:D_Zener` |
| Schottky Diodes | `Device:D_Schottky` |
| TVS | `Device:D_TVS` |
| LEDs | `Device:LED` |
| NPN Transistors | `Device:Q_NPN_BCE` |
| PNP Transistors | `Device:Q_PNP_BCE` |
| N-MOSFETs | `Device:Q_NMOS_GDS` |
| P-MOSFETs | `Device:Q_PMOS_GDS` |
| Ferrite Beads | `Device:Ferrite_Bead` |
| Crystals | `Device:Crystal` |
| Oscillators | `Oscillator:Oscillator_Crystal` |
| Fuses | `Device:Fuse` |
| Relays | `Relay:Relay_DPDT` |
| Potentiometers | `Device:R_POT` |
| Thermistors | `Device:Thermistor` |
| Varistors | `Device:Varistor` |
| Op-Amps | `Amplifier_Operational:LM358` |
| Comparators | `Comparator:LM393` |
| Voltage Regulators | `Regulator_Linear:LM317_TO-220` |
| LDOs | `Regulator_Linear:AMS1117-3.3` |
| Optocouplers | `Isolator:PC817` |
| Connectors | `Connector:Conn_01x02` |
| Switches/Buttons | `Switch:SW_Push` |
| Transformers | `Device:Transformer_1P_1S` |

## Backup Recommendation

Always backup before running on production:

```bash
php bin/console partdb:backup --database backup.zip
```

## License

Same as Part-DB (AGPL-3.0)
