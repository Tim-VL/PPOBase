# PPOBase Client Manual

**Shopware 6.7.x ERP and purchasing plugin**  

---

## Introduction

PPOBase is an minimal ERP and purchasing plugin for Shopware.

The plugin adds ERP-style tools to the Shopware Administration and helps manage supplier purchasing, stock changes, goods receipts, manual orders, grouped products, activity logs, and sales reports.

This manual explains how the PPOBase plugin is used inside the Shopware Administration. The goal is to make each feature easy to understand, with clear steps for everyday work.

---

## Version

| Item | Value |
|---|---|
| Plugin version | 1.1.2 |
| Plugin name | PPOBase |
| Supported Shopware version | Shopware 6.7.x |

---

## Requirements

Before installing or using PPOBase, make sure the following requirements are met:

| Requirement | Version / Notes |
|---|---|
| Shopware | 6.7.x |
| PHP | 8.2 or higher |
| Shopware Administration access | Required |
| Plugin permissions | User must be allowed to manage products, orders, settings, and plugins |
| Mailer configuration | Required for sending purchase order emails |
| Shop email address | Must be configured in Shopware Basic Information |

---

## Table of Contents

1. [Overview](#overview)
2. [Installation and Access](#installation-and-access)
3. [Purchase Order Template Settings](#purchase-order-template-settings)
4. [Suppliers](#suppliers)
5. [Product Detail PPOBase Tabs](#product-detail-ppobase-tabs)
6. [Purchase Orders](#purchase-orders)
7. [Goods Receipts](#goods-receipts)
8. [Grouped Products](#grouped-products)
9. [Manual Orders](#manual-orders)
10. [Sales Reports](#sales-reports)
11. [Activity Logs and PO History](#activity-logs-and-po-history)
12. [Product Tab Positions](#product-tab-positions)
13. [Recommended Workflow](#recommended-workflow)
14. [Notes and Important Rules](#notes-and-important-rules)
15. [Database Tables](#database-tables)
16. [Roadmap](#roadmap)
17. [License](#license)
18. [Contributing](#contributing)
19. [Support](#support)

---

## Overview

PPOBase adds base ERP-style tools to Shopware.

It is used to:

- Manage suppliers
- Link products to suppliers
- Create purchase orders
- Receive goods (from purchase orders)
- Update stock
- Create manual sales orders
- View activity logs
- Review sales reports

### Main Plugin Areas

- Suppliers
- Purchase Orders
- Goods Receipts
- Activity Logs and PO History
- Grouped Products
- Product tabs for stock, purchase, packaging, stock history, and grouped products
- Sales Reports
- Manual Orders
- Product Tab Positions (set product tab order)
- Purchase Order template settings (via plugin setting)

---

## Installation and Access

### Installation

Install and activate the PPOBase plugin from the Shopware plugin area or by the server installation process.

After activation, rebuild the Shopware Administration so the PPOBase menu items and product tabs appear.

Clear the Shopware cache after installation or after any administration asset rebuild.

### Via Composer

```bash
composer require ppobase/ppobase
bin/console plugin:refresh
bin/console plugin:install --activate PPOBase
bin/console cache:clear
````

### Manual Installation

1. Download or clone this repository.
2. Extract the plugin to:

```text
custom/plugins/PPOBase
```

3. Run the following commands:

```bash
bin/console plugin:refresh
bin/console plugin:install --activate PPOBase
bin/console cache:clear
```

### Build Administration Assets

After installation, rebuild the administration:

```bash
bin/build-administration.sh
```

Or:

```bash
./psh.phar administration:build
```

### Access in Shopware Administration

1. Log in to the Shopware Administration with a user that has permission to manage products, orders, settings, and plugins.
2. Open the main navigation and look for the PPOBase related entries.
3. Depending on the Shopware menu location, PPOBase features appear under **Catalogues**, **Orders**, **Settings**, or the **PPOBase** menu group.

---

## Purchase Order Template Settings

Open:

```text
Settings > Extensions > PPOBase
```

Use this section to configure the purchase order document template.

### Purchase Order Template

| Setting        | Description                                                                                                                                   |
| -------------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| Primary Colour | Selects the accent colour used in the PO header, table headings, and section titles. Default: `#1E88E5`.                                      |
| Font Family    | Selects the font used in the exported PO document. Available options include DejaVu Sans, Arial, Helvetica, Times New Roman, and Courier New. |

### Template Visibility

| Setting                     | Description                                                   |
| --------------------------- | ------------------------------------------------------------- |
| Show Supplier Address       | Displays the supplier billing address on the PO document.     |
| Show Supplier VAT Number    | Displays the supplier VAT number.                             |
| Show EAN Column             | Adds the EAN column to the item table.                        |
| Show MPN Column             | Adds the manufacturer part number column to the item table.   |
| Show Tax / VAT Row          | Displays the tax or VAT row in the totals section.            |
| Show Notes                  | Displays the notes section on the PO document.                |
| Show Expected Delivery Date | Displays the expected delivery date.                          |
| Show Internal Reference     | Displays the internal reference when it is entered on the PO. |

---

## Suppliers

The Suppliers module is used to maintain supplier master data.

Correct supplier data is important because purchase orders, PO emails, VAT, currency, and product mapping use this information.

### Create a Supplier

1. Open **Suppliers**.
2. Click **Add supplier**.
3. In **Basic Information**, enable **Active** if this supplier should be available in dropdowns.
4. Enter the **Company Trade Name**. This field is required.
5. Enter at least one email address in **Company Email** or **General Email**. This is required.
6. Select the **Currency**. This is required for purchase order creation.
7. Enter **VAT Percentage** if VAT should be calculated on purchase orders.
8. Fill contact, billing address, shipping address, logistics, and commercial terms as needed.
9. Click **Create supplier**.

### Important Supplier Fields

| Field                                                              | Description                                                                                         |
| ------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------- |
| Supplier ID                                                        | Generated automatically by the system.                                                              |
| Manual Supplier Items ID Range                                     | Records the SKU or item range assigned to this supplier.                                            |
| Purchase Order Email                                               | Used as the main recipient when emailing a purchase order. If empty, Company Email is used.         |
| Default CC and Default BCC                                         | Automatically filled in the PO email compose window. Multiple addresses can be separated by commas. |
| Lead Time Days                                                     | Average number of days from order to delivery.                                                      |
| Delivery Days                                                      | Days of the week when the supplier normally delivers.                                               |
| Payment Terms, Incoterms, Minimum Order Value, Discount Agreements | Used as commercial reference information.                                                           |

### Edit or Delete a Supplier

1. Open **Suppliers**.
2. Use search to find the supplier by name, email, or supplier number.
3. Click **Edit** to update the supplier details.
4. Click **Save** after changes.
5. Use **Delete** only when the supplier should be removed from the system.

### Map Products to a Supplier

1. Open **Suppliers**.
2. Choose **Map Products** for the supplier.
3. Click **Add Products**.
4. Select the products that are supplied by this supplier.
5. Click **Add Selected**.

Mapped products become available when creating purchase orders for that supplier.

---

## Product Detail PPOBase Tabs

PPOBase adds several tabs to the Shopware product detail page.

These tabs keep purchasing, stock, packaging, and grouped-product information close to the product.

### Stock Bookings

1. Open a product.
2. Open the **Stock Bookings** tab.
3. Review the current stock level.
4. Enter a signed stock adjustment, for example `+5` or `-2`.
5. Select or enter a reason and add notes if needed.
6. Click **Apply Stock Change**.

> **Important:** Clicking **Save** does not alter the stock. Use **Apply Stock Change** to update stock.

The plugin updates product stock and stores a stock booking history entry.

### Purchase Tab

1. Open a product and go to **Purchase**.
2. Use **Add Supplier** to link the product to a supplier.
3. Enter **Supplier SKU**, **Supplier Price**, and **Currency** if required.
4. Use **Add to Purchase Order** to add the product to an existing PO or create a new draft PO.
5. Review the product's purchase order history in the same tab.

### Packaging Tab

1. Open **Packaging**.
2. Enter carton information such as:

   * Items per carton
   * Cartons per layer
   * Length
   * Height
   * Width
   * Net weight
   * Gross weight
3. Use **Rule Notes** for purchasing rules, minimum quantities, or special ordering notes.
4. Click **Save Packaging Data**.

### Stock History Tab

1. Open **Stock History**.
2. Review manual bookings, goods receipts, and sales order stock movements.
3. Enable **Include cancelled/refunded orders** when those orders should appear in the stock history.

### Grouped Products Tab

1. Open **Grouped Products**.
2. Enable **Grouped Product** when this product should automatically add child products to the cart.
3. Use **Add Product** for one child product or **Bulk Assign** for multiple child products.
4. Set the quantity for each child product.
5. Click **Save quantities**.

When the parent product is added to the storefront cart, the assigned child products are added according to the configured quantities.

---

## Purchase Orders

Purchase Orders are used to order products from suppliers and then track receiving through Goods Receipts.

### Create a Purchase Order

1. Open **Purchase Orders**.
2. Click **Create PO**.
3. Select the **Supplier**.
4. Select the **Sales channel** if required.
5. Click **Create Purchase Order**.

The new PO opens in detail view.

### Add Items

1. In the PO detail page, use **Add Supplier Products** to select mapped products for the supplier.
2. Use **Add Manual Item** when an item is not yet mapped to the supplier.
3. Enter or review:

   * SKU
   * EAN
   * MPN
   * Quantity
   * Unit price
   * VAT
   * Line total
4. Save the PO after adding or changing items.

### PO Information and Status

| Field                      | Description                                                |
| -------------------------- | ---------------------------------------------------------- |
| PO Number                  | Identifies the purchase order.                             |
| Status                     | Can be Draft, Sent, Partial, Received, or Cancelled.       |
| Order Date                 | Records when the PO was created or placed.                 |
| Expected Delivery          | Helps track incoming goods.                                |
| Internal Reference         | Can be used for internal notes or cross-reference numbers. |
| Subtotal, Tax / VAT, Total | Calculated from the items.                                 |

### Export PO

1. Open the PO detail page.
2. Click **Export PDF** to download the purchase order as a PDF.
3. Click **Export HTML** to download an HTML version.

Each export is recorded in the activity log.

### Send PO by Email

1. Open the PO detail page.
2. Click **Send Email**.
3. Review the **To** address.
4. Review or edit **CC**, **BCC**, subject, and email body.
5. The PDF purchase order is attached automatically.
6. Click **Send Email**.

The plugin uses the supplier **Purchase Order Email** first, then **Company Email**.

If the PO was **Draft**, the plugin changes the status to **Sent** after sending.

The email action, recipient, CC, and BCC are recorded in the activity log.

### Receive Goods from a PO

1. Make sure the PO is in **Sent** or **Partial** status.
2. Open the PO detail page.
3. Click **Receive Goods**.
4. The plugin creates a draft Goods Receipt for the remaining quantities.
5. Open the Goods Receipt and enter the received and rejected quantities before booking.

---

## Goods Receipts

Goods Receipts are used to confirm what arrived from a supplier.

Booking a receipt updates stock and the purchase order received quantities.

### Create a Goods Receipt

1. Open a **Sent** or **Partial** purchase order.
2. Click **Receive Goods**.
3. The plugin creates a receipt number such as `GR2026-0001`.
4. The receipt starts in **Draft** status and includes remaining PO quantities.

### Edit a Draft Receipt

1. Open **Goods Receipts**.
2. Open the receipt.
3. Review:

   * PO Number
   * Supplier
   * Receipt Date
   * Invoice Reference
   * Status
4. For each item, review the expected quantity.
5. Enter the received quantity and rejected quantity as needed.
6. Enter booked price if the received price differs from the PO unit price.
7. Add notes if needed.
8. Click **Save**.

### Book a Receipt

1. Open the draft receipt.
2. Click **Book Receipt**.

After booking:

* Product stock is updated by the received quantities.
* Purchase order item received quantities are updated.
* The receipt status changes to **Booked**.
* The related PO changes to **Partial** or **Received**, depending on whether all quantities are complete.
* Stock changes and receipt booking are recorded in the activity log.

### Cancel a Receipt

1. Open the draft receipt.
2. Click **Cancel Receipt**.

Only draft receipts can be cancelled.

The status changes to **Cancelled** and no stock is booked.

---

## Grouped Products

Grouped Products let a parent product automatically add configured child products to the cart.

### Manage from the Product

1. Open the parent product.
2. Open the **Grouped Products** tab.
3. Enable the grouped product toggle.
4. Add child products using **Add Product** or **Bulk Assign**.
5. Set the quantity for each child product.
6. Save quantities.

### Manage from the Grouped Products List

1. Open **Grouped Products**.
2. Review all parent products that have grouped children.
3. Use **Edit in Product** to open the product detail page.
4. Use **Manage Children** to view assigned child products.

### Storefront Behaviour

When the parent product is added to the cart, PPOBase adds the configured child products.

The child quantities follow the quantities configured on the parent product.

Disabling grouped product behaviour stops the automatic cart assignment.

---

## Manual Orders

Manual Orders provide quick order entry from the administration.

This is useful for phone orders, email orders, or internal order creation.

### Create a Manual Order

1. Open **Manual Orders**.
2. Click **Create Order**.
3. Select the **Sales Channel** first.
4. Search and select an existing customer or click **Create New Customer**.
5. If creating a new customer, enter:

   * Salutation
   * First name
   * Last name
   * Email
   * Address
   * Country
6. Save the customer.
7. Select **Shipping Method** and **Shipping Date**.
8. Choose the shipping address or add a new address.
9. Select **Payment Method**.
10. Search products by name or SKU and add them to the order.
11. Use **Add Manual Item** when the item is not a product from the catalogue.
12. Review unit price, quantity, tax rate, subtotal, tax, and grand total.
13. Enter **Order Reference** and **Notes** if needed.
14. Choose whether to send the order confirmation email and whether to open the order after creation.
15. Click **Create Order**.

### Manual Order List

From **Manual Orders**, you can:

* See created manual orders
* Use search or list pagination to find an order
* Open **View Order** to view the Shopware order
* Use **Print Invoice**, **Print Delivery Note**, or **Export to Excel**
* Use **Bulk Export CSV** to export multiple manual orders

### Manual Order Detail

Open a manual order to review:

* Order information
* Addresses
* Items
* Totals
* Order reference
* Notes

Available actions:

* **Save Changes**
* **Print Invoice**
* **Print Delivery Note**
* **Export to Excel**

---

## Sales Reports

Sales Reports use Shopware order data and provide reporting views for customers, items, and order totals.

### Dashboard

1. Open **Sales Reports**.
2. Review the dashboard cards.
3. Use the **Date from** and **Date To** filters to control the reporting period.
4. Click **Apply Filter** to refresh the data.
5. Click **Reset** to return to the default report range.

### Customers by Orders

Review:

* Customer name
* Email
* Company
* Order count
* Total revenue
* Last order

Use the chart to identify the top customers.

### Items by Sales

Review:

* Product
* SKU
* Quantity sold
* Order count
* Total revenue
* Average price

Use the chart to identify high-selling products.

### Sales per Customer

1. Search for a customer by name, email, or company.
2. Select the customer.
3. Review:

   * Purchased products
   * Quantity purchased
   * Total spent
   * Average price
   * Order count

Use the charts for revenue by product and monthly spending trend.

### Sales per Item

1. Search for a product by name or SKU.
2. Select the product.
3. Review:

   * Order number
   * Order date
   * Customer
   * Email
   * Quantity
   * Unit price
   * Total price

Use the charts for monthly sales trend and top buyers.

### Orders Overview

Review:

* Order number
* Date
* Customer
* Email
* Company
* Status
* Currency
* Total

Use the date filters to limit the report period.

---

## Activity Logs and PO History

PPOBase records important actions so changes can be reviewed later.

### Activity Logs

1. Open **Activity Logs**.
2. Use **Entity Type** and **Action** filters to narrow the list.
3. Review:

   * Date
   * Entity
   * Reference
   * Action
   * Description
   * User
   * IP Address
4. Click **Refresh** to reload the latest log entries.

### PO History

1. Open **PO History**.
2. Search by PO number or description.
3. Filter by event type, such as:

   * Created
   * Updated
   * Status Changed
   * Exported
   * Emailed
   * Items Added
   * Items Removed
4. Open **View changes** to review before and after values for an event.

---

## Product Tab Positions

Product Tab Positions lets the client control the order of PPOBase tabs on the product detail page.

### Configure Tab Order

1. Open **Product Tab Positions**.
2. Review:

   * Tab Name
   * Technical Name
   * Default Position
   * Custom Position
   * Effective Position
3. Enter a **Custom Position** to move a tab earlier or later.
4. The **Effective Position** shows the final order used by the administration.
5. Use **Reset to default** for one tab or **Reset all** to restore all default positions.

---

## Recommended Workflow

For best results, use this working order:

1. Create and complete supplier master data first.
2. Map products to suppliers.
3. Add packaging and purchasing notes on the product detail page.
4. Create purchase orders from the Purchase Orders module or from the product Purchase tab.
5. Email or export the purchase order.
6. Receive goods through Goods Receipts when products arrive.
7. Book the receipt to update stock.
8. Use Activity Logs, PO History, Stock History, and Sales Reports to review business activity.

---

## Notes and Important Rules

* Inactive suppliers are not shown in supplier dropdowns.
* A supplier needs Company Trade Name, an email address, and Currency for correctly making a purchase order.
* A PO must be **Sent** or **Partial** before goods can be received.
* Only **Draft** goods receipts can be booked or cancelled.
* Booking a goods receipt changes stock immediately.
* Sending a draft PO by email automatically changes the PO status to **Sent**.
* The shop email address must be configured in Shopware Basic Information before PO emails can be sent.
* Shopware email delivery must be enabled in:

```text
Settings > Mailer
```

---

## Database Tables

The plugin creates the following tables.

### `ppobase_supplier`

Stores all supplier information, including:

* Basic information

  * ID
  * Active status
  * Supplier number
  * Item ID range
* Company details

  * Trade name
  * Official name
  * VAT number
  * Registration numbers
* Contact information

  * Primary contact
  * General contact
* Addresses

  * Billing address
  * Shipping address
* Logistics

  * Lead time
  * Delivery days
* Commercial terms

  * Currency
  * Payment terms
  * Incoterms
  * Discounts
* Audit fields

  * Created by
  * Updated by
  * Created at
  * Updated at

---

## Roadmap

Future planned features:

* User Rights Management
* Document templates per sales channel
* POS function
* Calendar with sync options
* Kanban board
* Knowledge Management / FAQ module
* Price list per customer group
* Multiple warehouses

---

## License

MIT License.

See the [LICENSE](LICENSE) file for details.

---

## Contributing

1. Fork the repository.
2. Create a feature branch.
3. Make your changes.
4. Submit a pull request.

---

## Support

For issues and feature requests, use the GitHub issue tracker:

```text
https://github.com/Tim-VL/POBase/issues
```

```
```
