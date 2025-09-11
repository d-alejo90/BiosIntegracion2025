# Unit Tests para BiosIntegracion2025

Este proyecto incluye un conjunto completo de unit tests usando PHPUnit y Mockery para garantizar la calidad y confiabilidad del código.

## Configuración

### Requisitos
- PHP 8.0+
- Composer
- PHPUnit 10.0+
- Mockery 1.5+

### Instalación de dependencias
```bash
composer install
```

## Estructura de Tests

```
tests/
├── Unit/
│   ├── Services/
│   │   ├── CreateOrderServiceTest.php
│   │   └── CancelOrderServiceTest.php
│   ├── CronJobs/
│   │   └── CreateProductsTest.php
│   └── Repositories/
│       └── ProductRepositoryTest.php
├── Integration/
├── TestCase.php
└── bootstrap.php
```

## Ejecutar Tests

### Ejecutar todos los tests
```bash
./vendor/bin/phpunit
```

### Ejecutar tests específicos
```bash
# Solo tests unitarios
./vendor/bin/phpunit --testsuite=Unit

# Solo tests de integración  
./vendor/bin/phpunit --testsuite=Integration

# Test específico
./vendor/bin/phpunit tests/Unit/Services/CreateOrderServiceTest.php
```

### Con coverage (requiere Xdebug)
```bash
./vendor/bin/phpunit --coverage-html coverage
```

## Tests Incluidos

### CreateOrderServiceTest
- ✅ `testProcessOrderWithValidData()` - Procesa órdenes válidas
- ✅ `testProcessOrderWithInvalidJson()` - Maneja JSON inválido
- ✅ `testProcessOrderWithExistingOrder()` - Detecta órdenes duplicadas
- ✅ `testNormalizeString()` - Normaliza strings con acentos
- ✅ `testIsValidJson()` - Valida formato JSON
- ✅ `testFormatDatetimeForSQLServer()` - Formatea fechas para SQL Server

### CancelOrderServiceTest
- ✅ `testConstructorSetsPropertiesCorrectly()` - Constructor inicializa propiedades
- ✅ `testCancelOrderCallsRepositoryWithCorrectParameters()` - Cancela órdenes correctamente
- ✅ `testCancelOrderWithDifferentOrderId()` - Maneja diferentes IDs de orden
- ✅ `testCancelOrderWithMissingOrderId()` - Maneja datos faltantes
- ✅ `testCancelOrderWithDifferentStoreConfiguration()` - Funciona con diferentes stores

### CreateProductsTest
- ✅ `testRunWithActiveCronJob()` - Ejecuta cuando el cron está activo
- ✅ `testRunWithInactiveCronJob()` - Se detiene cuando el cron está inactivo
- ✅ `testGetSiesaProductsWithoutSkuList()` - Obtiene todos los productos
- ✅ `testGetSiesaProductsWithSkuList()` - Filtra por SKUs específicos
- ✅ `testGroupProductsForMizooco()` - Agrupa productos para Mizooco
- ✅ `testGroupProductsForCampoAzul()` - Agrupa productos para Campo Azul
- ✅ `testFormatValues()` - Formatea valores para Shopify
- ✅ `testGetUniquePresentations()` - Obtiene presentaciones únicas
- ✅ `testExtractId()` - Extrae IDs de GIDs de Shopify
- ✅ `testMapProductFromNode()` - Mapea nodos de respuesta de Shopify

### ProductRepositoryTest
- ✅ `testFindAllReturnsAllProducts()` - Obtiene todos los productos
- ✅ `testFindByCiaWithNormalCode()` - Busca por código de compañía normal
- ✅ `testFindByCiaWithSpecialCode232P()` - Maneja código especial 232P → 20
- ✅ `testFindByIdReturnsProduct()` - Encuentra producto por ID
- ✅ `testFindByIdReturnsNullWhenNotFound()` - Retorna null cuando no encuentra
- ✅ `testFindByGroupIdReturnsShopifyProductId()` - Busca por group_id
- ✅ `testFindBySkuReturnsShopifyProductId()` - Busca por SKU
- ✅ `testCreateProduct()` - Crea nuevo producto
- ✅ `testUpdateProduct()` - Actualiza producto existente
- ✅ `testDeleteProduct()` - Elimina producto

## Patrones de Testing Utilizados

### Mocking
- **Mockery** para crear mocks de dependencias
- Mocks de repositorios, servicios externos (Shopify), y conexiones de base de datos
- Verification que los métodos son llamados con los parámetros correctos

### Test Doubles
- **Stubs** para simular respuestas de APIs externas
- **Mocks** para verificar interacciones
- **Fakes** para objetos de configuración

### Data Providers
- Métodos helper en `TestCase.php` para generar datos de prueba consistentes
- `getSampleOrderData()` - Datos de orden estándar
- `getSampleStoreConfig()` - Configuración de tienda estándar

### Reflection Testing
- Tests de métodos privados usando `ReflectionClass`
- Acceso a propiedades privadas para verificar estado interno

## Beneficios para Refactoring

Estos tests te permitirán:

1. **Refactorizar con confianza** - Los tests detectarán regressions
2. **Documentar comportamiento esperado** - Los tests actúan como documentación viva
3. **Detectar side effects** - Cambios en una parte del código que afecten otra
4. **Validar edge cases** - Casos límite y manejo de errores
5. **Facilitar debugging** - Tests específicos para reproducir bugs

## Comandos útiles

```bash
# Watch mode (requiere inotify-tools en Linux)
find tests src -name "*.php" | entr -c ./vendor/bin/phpunit

# Ejecutar test específico con debug
./vendor/bin/phpunit --debug tests/Unit/Services/CreateOrderServiceTest.php::testProcessOrderWithValidData

# Solo tests que fallan
./vendor/bin/phpunit --stop-on-failure
```

## Próximos pasos

Para expandir la suite de tests, considera agregar:

- Tests de integración para flujos completos
- Tests de los webhooks (CreateOrder, CancelOrder)
- Tests de los cron jobs restantes (UpdateInventory, UpdatePrices)
- Tests de los helpers (ShopifyHelper, Logger)
- Tests de los modelos con validaciones