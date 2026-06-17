# Ordering Helper — Implementation Summary

## What was built

A complete end-to-end ordering workflow for Part-DB, spanning a new **Orders** section in the sidebar and an **Ordering Helper** tool under Tools.

---

## New files

| Path | Purpose |
|---|---|
| `src/Entity/OrderSystem/Order.php` | Top-level order entity (name, notes, timestamps) |
| `src/Entity/OrderSystem/OrderItem.php` | Line item: part (nullable), name, qty, supplier, SKU |
| `src/Entity/OrderSystem/OrderSupplierReference.php` | Per-supplier order number attached to an Order |
| `src/Repository/OrderSystem/OrderRepository.php` | `findAllSortedByDate()` for the sidebar list |
| `src/Security/Voter/OrderVoter.php` | Gates `Order` operations via the `orders` permission group |
| `src/Security/Voter/OrderItemVoter.php` | Delegates `OrderItem` permissions to its parent `Order` |
| `src/Form/OrderSystem/OrderType.php` | Full order edit form (metadata + items + supplier refs) |
| `src/Form/OrderSystem/OrderItemType.php` | Single line item (part, name, qty, supplier, SKU) |
| `src/Form/OrderSystem/OrderSupplierReferenceType.php` | Supplier + order number pair |
| `src/Form/OrderSystem/OrderingHelperType.php` | Multi-project + build-count form with compute/save buttons |
| `src/Form/OrderSystem/ProjectBuildRequestEntryType.php` | One row: project selector + integer build count |
| `src/Form/OrderSystem/ReceiveOrderType.php` | Dynamic form with one `NumberType` field per order item |
| `src/Services/OrderSystem/OrderingHelperService.php` | Core BOM computation + stock subtraction logic |
| `src/Controller/OrderController.php` | 7 routes: list, new, show/edit, delete, receive, export-csv, ordering-helper |
| `templates/orders/list.html.twig` | Order list with delete modals |
| `templates/orders/new.html.twig` | Blank order creation form |
| `templates/orders/show.html.twig` | Order detail/edit with inline item table |
| `templates/orders/receive.html.twig` | Receive flow — editable quantity per line item |
| `templates/orders/ordering_helper.html.twig` | Project selector → preview table → Save as Order |
| `migrations/Version20260617000000.php` | Creates `orders`, `order_items`, `order_supplier_references` tables (MySQL + SQLite + PostgreSQL) |

## Modified files

| Path | Change |
|---|---|
| `config/permissions.yaml` | New `orders` group: `read`, `edit`, `create`, `delete`, `receive` |
| `src/Entity/Base/AbstractDBElement.php` | Added 3 new entities to the Symfony serializer discriminator map |
| `src/Controller/TreeController.php` | New `GET /tree/orders` endpoint serving a flat list of TreeViewNodes |
| `src/Services/Trees/ToolsTreeBuilder.php` | Added "Ordering Helper" link in the Tools section |
| `src/Settings/BehaviorSettings/SidebarItems.php` | Added `ORDERS = "orders"` case |
| `src/Settings/BehaviorSettings/SidebarSettings.php` | Added `ORDERS` to default sidebar items |
| `templates/components/tree_macros.html.twig` | Added `orders` to the sidebar data-source dropdown |
| `translations/messages.en.xlf` | 49 new English translation keys (`order.*`, `perm.orders.receive`) |

---

## Key design decisions

### Orders are stateless
The original design considered a `draft → confirmed → ordered → received` state machine. This was deliberately dropped. The "Receive Order" action is a pure helper — it increments stock without any state tracking on the Order entity. This keeps the model simple and avoids workflow overhead for solo or small-team use.

### OrderItem.part is nullable
Each line item can optionally link to a `Part` in the catalog, but doesn't have to. This supports ad-hoc items (e.g. hardware, PCBs, consumables) that aren't tracked in the parts database. When a Part is linked, the name is auto-populated but can be overridden.

### Supplier + SKU override on OrderItem
Parts already have `Orderdetail` records linking them to suppliers with SKUs. Rather than duplicating that relationship, `OrderItem.supplier` and `OrderItem.supplierPartNr` act as **overrides**. `getEffectiveSupplierPartNr()` resolves the right SKU at render time: explicit override → matching Orderdetail by supplier → first available Orderdetail.

### Stock subtraction is per-part, not per-lot
`OrderingHelperService` uses `Part::getAmountSum()` (total stock across all lots) to compute the deficit. This mirrors the existing `ProjectBuildHelper` philosophy — it doesn't care which lot the parts come from, only whether there's enough total stock.

### Duplicate parts across projects are merged
When the same part appears in multiple selected project BOMs, the service accumulates the quantities (after stock subtraction per project × build count). The result is one order line per distinct part, with a summed deficit quantity.

### Orders sidebar uses a flat tree
The sidebar tree widget (`treeview_sidebar`) is designed for hierarchical data. Orders are flat, so the `/tree/orders` endpoint returns a simple array of `TreeViewNode` objects — one per order. The widget renders them correctly as a flat list.

### CSV export: one file per supplier, ZIP for multiples
DigiKey and Mouser (and other distributors) each have their own CSV upload format. Generating one CSV per supplier with columns `SKU, Quantity, Part Name` is the lowest common denominator. For single-supplier orders the file is downloaded directly; multi-supplier orders are zipped. Items with no supplier assigned go into an `Unassigned.csv`.

### Receive Order creates a new lot if none exists
The receive flow uses `PartLotWithdrawAddHelper::add()` which logs a `PartStockChangedLogEntry`. If a part has no writable lot (all lots have `instock_unknown = true`), a new lot is created on the fly. This avoids a confusing dead-end for parts that have never had stock tracked.

### Legacy `manual_order` system untouched
Parts still have their existing `manual_order` / `order_quantity` flags. The new Order system is completely independent — there is no migration or consolidation of the legacy data.
