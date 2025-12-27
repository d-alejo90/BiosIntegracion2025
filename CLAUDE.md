# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Shopify-SIESA integration system (BiosIntegracion2025) written in PHP that synchronizes e-commerce data between Shopify stores and SIESA inventory management system. The system handles order creation, fulfillment processing, inventory updates, and price synchronization for two stores: Campo Azul and Mizooco.

## Development Commands

### Testing
```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test suites
./vendor/bin/phpunit --testsuite=Unit
./vendor/bin/phpunit --testsuite=Integration

# Run single test file
./vendor/bin/phpunit tests/Unit/Services/CreateOrderServiceTest.php

# Run with coverage (requires Xdebug)
./vendor/bin/phpunit --coverage-html coverage
```

### Dependency Management
```bash
# Install dependencies
composer install

# Update dependencies
composer update
```

## Architecture Overview

### Core Components

**Webhooks** (`src/Webhooks/`)
- `BaseWebhook`: Abstract class for HMAC-SHA256 verification of Shopify webhooks
- `BaseManualWebhook`: Abstract class for manual/API-triggered webhooks (no HMAC verification)
- `CreateOrder`: Handles automatic order creation from Shopify webhooks
- `CancelOrder`: Handles order cancellation webhooks
- `CreateManualOrder`: Manual order creation (extends BaseManualWebhook)

**Services** (`src/Services/`)
- `CreateOrderService`: Core business logic for processing Shopify orders into SIESA format
  - Handles discount allocations (including Buy X Get Y promotions)
  - Maps Shopify line items to SIESA order details
  - Processes customer data and city/ZIP code mapping
- `CancelOrderService`: Handles order cancellation logic

**CronJobs** (`src/CronJobs/`)
- `CreateProducts`: Syncs products from SIESA to Shopify (supports optional SKU filtering via GET params)
- `UpdateInventory`: Updates inventory levels from SIESA to Shopify (optimized with batching)
- `UpdatePrices`: Syncs price changes from SIESA to Shopify
- `ProcessFulfillments`: Processes order fulfillments from SIESA to Shopify

**Repositories** (`src/Repositories/`)
- Repository pattern for database operations
- Each model has corresponding repository (Customer, Order, Product, etc.)

### Configuration

**Store Configuration** (`src/Helpers/StoreConfigFactory.php`)
- Multi-store support for Campo Azul and Mizooco
- Store-specific Shopify API credentials and SIESA company codes
- ZIP code mappings for each store

**Database** (`src/Config/Database.php`)
- Singleton pattern for SQL Server connections
- Uses PDO with SQL Server driver (ext-sqlsrv)

**Constants** (`src/Config/Constants.php`)
- ZIP code mappings for both stores
- Warehouse configurations (BODEGAS)
- Default values like birth dates

### Key Patterns

1. **Multi-Store Architecture**: Single codebase supports multiple Shopify stores with different configurations
2. **Repository Pattern**: Data access abstracted through repositories
3. **Service Layer**: Business logic separated from controllers/webhooks
4. **Factory Pattern**: StoreConfigFactory provides store-specific configurations
5. **Webhook Verification**: HMAC-SHA256 verification for all incoming webhooks

### Public Endpoints

**Webhooks** (`public/WebHooks/`)
- `CreateOrder.php`: Receives Shopify order creation webhooks
- `CancelOrder.php`: Receives Shopify order cancellation webhooks
- `CreateManualOrder.php`: Manual order creation endpoint (no HMAC verification)

**CronJobs** (`public/CronJobs/`)
Each cron job has store-specific entry points:
- Campo Azul endpoints: `CreateProductsCampoAzul.php`, `UpdateInventoryCampoAzul.php`, `UpdatePricesCampoAzul.php`, `ProcessFulfillmentsCampoAzul.php`
- Mizooco endpoints: `CreateProductsMizooco.php`, `UpdateInventoryMizooco.php`, `UpdatePricesMizooco.php`, `ProcessFulfillmentsMizooco.php`
- Entry points pass store URL to corresponding CronJob class in `src/CronJobs/`

### Location Filtering in Cron Jobs

All cron jobs support optional location filtering via GET parameter. This allows processing inventory, products, and prices for specific warehouses/bodegas instead of all locations.

**Usage:**
```bash
# Process all locations (default behavior - backward compatible)
/UpdateInventoryCampoAzul.php

# Process specific location by ID
/UpdateInventoryCampoAzul.php?location=64213581870

# Process specific location by name (case-insensitive)
/UpdateInventoryCampoAzul.php?location=Barranquilla

# Combine with SKU filtering (CreateProducts only)
/CreateProductsCampoAzul.php?skus=ABC123,DEF456&location=Suba-Bogota
```

**Valid Locations:**

*Campo Azul (7 locations):*
- `64213581870` → Barranquilla
- `60916105262` → Suba-Bogota
- `61816963118` → Bucaramanga-Concordia
- `60906635310` → Rionegro
- `60916072494` → Minorista
- `61816995886` → Eje-Cafetero-Pinares
- `65620377646` → Campo-Azul-Vegas

*Mizooco (6 locations):*
- `89995608360` → Barranquilla
- `89918046504` → Bogotá
- `102061080872` → Bucaramanga
- `91807318312` → Cali
- `89917882664` → Medellín
- `102061048104` → Pereira

**Supported Cron Jobs:**
- ✅ `UpdateInventory` - Filters inventory updates by location
- ✅ `CreateProducts` - Filters product creation/updates by location
- ✅ `UpdatePrices` - Filters price updates by location

**Note:** `ProcessFulfillments` does not support location filtering as fulfillments are processed per complete order, not per warehouse.

**Implementation Details:**
- Location parameter accepts both ID and name (normalized internally)
- Invalid location terminates execution with error message listing valid options
- SQL-level filtering for optimal performance (reduces data transfer and API calls)
- 100% backward compatible - omitting parameter processes all locations

**Example Use Cases:**
```bash
# Update inventory for high-traffic warehouse only
/UpdateInventoryCampoAzul.php?location=Minorista

# Sync products for new warehouse
/CreateProductsMizooco.php?location=Cali

# Update prices for specific SKUs in one location
/CreateProductsCampoAzul.php?skus=SKU1,SKU2&location=64213581870
```

### Database Integration

- Uses SQL Server with PDO
- Models represent database entities (Customer, OrderHead, OrderDetail, Product, etc.)
- No ORM - direct SQL queries through repositories

### Testing

The project has comprehensive PHPUnit tests with Mockery for mocking:
- Unit tests for services, repositories, and cron jobs
- Test coverage includes edge cases and error handling
- Uses reflection for testing private methods
- Mock objects for external dependencies (Shopify API, database)

### Environment Configuration

Requires `.env` file with:
- Database credentials (SQL Server)
- Shopify API credentials for each store
- Webhook signing secrets
- Store URLs and access tokens

### Logging

Uses custom `Logger` helper (`src/Helpers/Logger.php`) for file-based logging with store-specific log files.

### Important Business Logic

**Discount Handling**
- Supports Shopify's discount allocations including Buy X Get Y (BXGY) promotions
- Discount amounts extracted from `line_item['discount_allocations'][0]['amount']`
- Applied at the line item level in OrderDetail records

**Store-Specific Mappings**
- Each store has unique company codes (codigoCia): Campo Azul uses '232P', Mizooco uses '232'
- ZIP code mappings in Constants.php map city names to SIESA warehouse codes
- Warehouse (BODEGA) mappings stored in Constants for fulfillment routing

**Special Company Code Handling**
- ProductRepository translates '232P' to '20' when querying SIESA database
- This allows Campo Azul to use code 232P in Shopify while SIESA uses code 20