# EIGP 114 Barcode Operations #

## Concept of Operation ##
Module is intended to be as quick and easy as possible once you receive your bag of goodies from
Digikey or Mouser. Steps would be:
  * Grab a bag from the shipment
  * Identify the component type and scan your intended storage box
  * Scan the storage container
  * Scan the bag and put in container
  * Grab the next bag (same component type)
  * Scan the bag and put in container
  * Grab the next bag (different component type)
  * Scan new container
  * Scan the bag and put in container

### Alternatives ###

  * Grab a bag from the shipment
  * Scan the bag
  * Put the bag in the single storage location indicated
  * Scan next bag from shipment...

## Scanner Types ##

Currently only supports an HP 4430 scanner. Code uses WebSerial to communicate with the scanner. For
this to me more widespread would need to add a database of scanner USB device IDs and some data
indicating how each is different

