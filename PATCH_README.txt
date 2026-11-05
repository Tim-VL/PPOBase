PPOBase navigation patch – GitHub main (Shopware 6.7)

Extract the ZIP directly into custom/plugins/PPOBase/ (overwrite files).
Backup the existing files first. Does not include the complete plugin.

Menu:
Purchase orders (top-level)
  Overview
  Purchase Orders
  Goods Receipts
  Suppliers
  Settings
    Activity Logs
    Product Tab Positions
Orders
  Sales Reports
  Manual Orders
Catalogue
  Grouped Products (unchanged; not included in ZIP)

Note: PO History menu shortcut is removed; existing PO History routes remain accessible.
Existing Settings > Extensions registration remains unchanged.

Rebuild in the Shopware root:
  bin/build-administration.sh
  bin/console cache:clear

Syntax-only checks have been run. Not tested in a running Shopware Administration.
The top-level core module registration is an unofficial Shopware workaround.
