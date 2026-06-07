# PPOBase - POBase ERP Module for Shopware 6.6

A simple ERP module for Shopware 6 that provides supplier management, purchase orders, stocktaking, and more.

## Features (V1 - Current)

### Supplier Management
- Full CRUD operations (Create, Read, Update, Delete)
- Comprehensive supplier data:
  - Company details (trade name, official name, VAT, registration numbers)
  - Primary and general contact information
  - Billing and shipping addresses
  - Logistics settings (lead time, delivery days)
  - Commercial terms (currency, payment terms, incoterms, discounts)
- Auto-generated supplier numbers (SUP-0001 format)
- Active/inactive status toggle
- Search and filtering
- Paginated list view

## Requirements

- Shopware 6.6.x
- PHP 8.1 or higher

## Installation

### Via Composer (Recommended)

```bash
composer require ppobase/ppobase
bin/console plugin:refresh
bin/console plugin:install --activate PPOBase
bin/console cache:clear
```

### Manual Installation

1. Download or clone this repository
2. Extract to `custom/plugins/PPOBase`
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
# or
./psh.phar administration:build
```

## Uninstallation

The plugin supports clean uninstallation with optional data retention:

```bash
# Keep data (tables remain)
bin/console plugin:uninstall PPOBase

# Remove all data (drops tables)
bin/console plugin:uninstall PPOBase --keep-user-data=false
```

## API Endpoints

All endpoints require admin API authentication.

### List Suppliers
```
GET /api/ppobase/supplier
```

Query parameters:
- `page` (int): Page number (default: 1)
- `limit` (int): Items per page (default: 25, max: 100)
- `search` (string): Search term
- `active` (bool): Filter by active status
- `sortBy` (string): Field to sort by (default: createdAt)
- `sortOrder` (string): ASC or DESC (default: DESC)

### Get Single Supplier
```
GET /api/ppobase/supplier/{id}
```

### Create Supplier
```
POST /api/ppobase/supplier
Content-Type: application/json

{
    "companyTradeName": "Example Supplier Ltd",
    "active": true,
    "vatNumber": "DE123456789",
    ...
}
```

### Update Supplier
```
PATCH /api/ppobase/supplier/{id}
Content-Type: application/json

{
    "companyTradeName": "Updated Name",
    ...
}
```

### Delete Supplier
```
DELETE /api/ppobase/supplier/{id}
```

## Administration UI

After installation, navigate to:
**Settings → Extensions → POBase Suppliers**

From here you can:
- View all suppliers in a paginated list
- Search suppliers by name, email, or supplier number
- Create new suppliers
- Edit existing suppliers
- Delete suppliers

## Database Tables

The plugin creates the following tables:

### `ppobase_supplier`
Stores all supplier information including:
- Basic info (id, active, supplier_number, items_id_range)
- Company details (trade name, official name, VAT, etc.)
- Contact information (primary and general)
- Addresses (billing and shipping)
- Logistics (lead time, delivery days)
- Commercial terms (currency, payment terms, incoterms, discounts)
- Audit fields (created_by, updated_by, created_at, updated_at)

## Roadmap (V2)

Future planned features:
- Purchase Order Management
- Stocktaking / Receiving of Goods
- Manual Sales Orders
- Product-Supplier Mapping
- Sales Reports & Analytics
- User Rights Management
- Multiple Warehouses

## License

MIT License - see [LICENSE](LICENSE) file for details.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## Support

For issues and feature requests, please use the GitHub issue tracker:
https://github.com/Tim-VL/POBase/issues

## Author

Developed by Priyanshu Nandan
