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
- `BaseWebhook`: Abstract class for HMAC verification and common webhook functionality
- `CreateOrder`: Handles order creation from Shopify
- `CancelOrder`: Handles order cancellation
- `CreateManualOrder`: Manual order creation webhook

**Services** (`src/Services/`)
- `CreateOrderService`: Core business logic for processing Shopify orders into SIESA format
- `CancelOrderService`: Handles order cancellation logic

**CronJobs** (`src/CronJobs/`)
- `CreateProducts`: Syncs products from SIESA to Shopify
- `UpdateInventory`: Updates inventory levels from SIESA to Shopify
- `UpdatePrices`: Syncs price changes from SIESA to Shopify
- `ProcessFulfillments`: Processes order fulfillments

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
- Shopify webhook endpoints for order events

**CronJobs** (`public/CronJobs/`)
- Store-specific cron job endpoints (e.g., `CreateProductsCampoAzul.php`, `UpdateInventoryMizooco.php`)

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