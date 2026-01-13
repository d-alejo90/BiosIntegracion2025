# Análisis de Problemas en CreateProducts y Script de Reparación

## Problemas Identificados en CreateProducts.php

### 1. **Falta de Manejo de Errores en INSERT** (Línea 144 - ProductRepository.php)

**Problema:**
```php
public function create(Product $product)
{
    $query = "INSERT INTO ctrlCreateProducts...";
    $stmt = $this->db->prepare($query);
    // ... bind params ...
    return $stmt->execute(); // Si falla, no se captura la excepción
}
```

**Impacto:**
- Si el INSERT falla (duplicado, constraint violation, conexión perdida), `execute()` retorna `false` pero no lanza excepción
- En `CreateProducts::saveProductsFromResponses()` no se verifica el resultado del `create()`
- El producto se crea en Shopify exitosamente PERO no se guarda en la base de datos

**Escenarios que causan este problema:**
- Clave duplicada (ya existe SKU + locacion)
- Violación de constraints de SQL Server
- Timeout de conexión a la base de datos
- Falta de permisos de escritura

### 2. **Validación Estricta de Estructura de Respuesta** (Líneas 455-474 - CreateProducts.php)

**Problema:**
```php
private function saveProductsFromResponses(array $shopifyResponses)
{
    foreach ($shopifyResponses as $response) {
        // Solo guarda si la estructura es EXACTAMENTE esta
        if (isset($response['data']['productSet']['product']['variants']['nodes'])) {
            // Guarda nuevos productos
        }
        if (isset($response['data']['productVariantsBulkCreate']['productVariants'])) {
            // Guarda variantes nuevas
        }
        // Si la estructura es diferente, NO SE GUARDA NADA
    }
}
```

**Impacto:**
- Si Shopify cambia la estructura de respuesta, los productos se crean pero no se guardan
- Si hay un error parcial en Shopify (algunos productos creados, otros no), no se guardan los exitosos
- No hay logging de respuestas con estructura inesperada

**Escenarios que causan este problema:**
- Cambios en la API de Shopify
- Respuestas con errores parciales
- Timeouts que resultan en respuestas incompletas

### 3. **Falta de Transaccionalidad**

**Problema:**
- La creación en Shopify y el guardado en BD son operaciones separadas sin rollback
- Si Shopify tiene éxito pero la BD falla, el producto queda "huérfano" en Shopify

**Impacto:**
- Productos creados en Shopify sin registro en ctrlCreateProducts
- Imposibilidad de sincronizar inventario para esos productos
- Cron jobs subsecuentes (UpdateInventory, UpdatePrices) no pueden funcionar correctamente

### 4. **Logging Insuficiente**

**Problema:**
- No se registra cuando un `create()` falla
- No se registra la respuesta completa de Shopify si tiene estructura inesperada
- Dificulta el debugging cuando ocurren problemas

## Script de Reparación

### Ubicación
```
scripts/repair_products_table.php
```

### Funcionalidad

El script realiza las siguientes operaciones:

1. **Consulta Shopify GraphQL** para obtener productos por SKU o todos los productos
2. **Extrae información necesaria**:
   - Product ID (prod_id)
   - Variant ID (vari_id)
   - Inventory Item ID (inve_id)
   - Location IDs (locacion) con nombres
3. **Compara con la base de datos** para identificar registros faltantes
4. **Inserta registros faltantes** en ctrlCreateProducts

### Uso

#### Reparar productos específicos por SKU:
```bash
php scripts/repair_products_table.php --store=campo-azul --skus=ABC123,DEF456,XYZ789
```

#### Reparar todos los productos (usar con precaución):
```bash
php scripts/repair_products_table.php --store=mizooco --all
```

#### Modo dry-run (previsualizar sin hacer cambios):
```bash
php scripts/repair_products_table.php --store=campo-azul --skus=ABC123 --dry-run
```

### Parámetros

- `--store`: Tienda a procesar (`campo-azul` o `mizooco`)
- `--skus`: Lista de SKUs separados por coma
- `--all`: Procesar todos los productos en Shopify
- `--dry-run`: Previsualizar cambios sin aplicarlos

### Ejemplos de Uso

#### Ejemplo 1: Reparar un solo producto
```bash
php scripts/repair_products_table.php --store=campo-azul --skus=PROD001
```

**Salida esperada:**
```
=== Repairing Products by SKUs ===
SKUs: PROD001

Found 1 variants in Shopify

Processing SKU: PROD001
  - Location Barranquilla (64213581870): MISSING - Creating...
  ✓ Successfully created
  - Location Suba-Bogota (60916105262): ALREADY EXISTS

=== SUMMARY ===
Created: 1
Skipped (already exist): 1
Errors: 0

Total processed: 2

Repair completed successfully!
```

#### Ejemplo 2: Verificar múltiples SKUs sin aplicar cambios
```bash
php scripts/repair_products_table.php --store=mizooco --skus=SKU1,SKU2,SKU3 --dry-run
```

#### Ejemplo 3: Reparar todos los productos de una tienda (PRECAUCIÓN)
```bash
# Primero en dry-run para ver qué se va a hacer
php scripts/repair_products_table.php --store=campo-azul --all --dry-run

# Luego aplicar si todo se ve bien
php scripts/repair_products_table.php --store=campo-azul --all
```

### Logging

El script genera logs en:
```
logs/repair_products_table_<store_name>.txt
```

Ejemplo:
```
logs/repair_products_table_campo_azul.txt
logs/repair_products_table_mizooco.txt
```

### Ventajas del Script

1. **No duplica registros**: Verifica existencia antes de insertar
2. **Manejo de errores**: Captura excepciones y registra errores
3. **Modo dry-run**: Permite previsualizar cambios
4. **Paginación automática**: Maneja grandes cantidades de productos
5. **Logging detallado**: Registra todas las operaciones
6. **Soporte multi-location**: Maneja correctamente productos con múltiples ubicaciones

### Limitaciones

1. **No detecta datos incorrectos**: Solo inserta registros faltantes, no corrige datos incorrectos
2. **Requiere acceso a Shopify**: Necesita las credenciales configuradas en `.env`
3. **No maneja productos eliminados**: Si un producto fue eliminado de Shopify pero existe en BD, no lo detecta

## Queries GraphQL Utilizadas

### Query 1: Buscar por SKUs específicos
```graphql
query GetProductVariantsBySKU($query: String!, $cursor: String) {
  productVariants(first: 250, query: $query, after: $cursor) {
    edges {
      node {
        id
        sku
        product {
          id
        }
        inventoryItem {
          id
          inventoryLevels(first: 50) {
            nodes {
              location {
                id
                name
              }
            }
          }
        }
      }
    }
    pageInfo {
      hasNextPage
      endCursor
    }
  }
}
```

**Variables:**
```json
{
  "query": "sku:ABC123 OR sku:DEF456 OR sku:XYZ789"
}
```

### Query 2: Obtener todos los productos con paginación
```graphql
query GetAllProductsWithVariants($cursor: String) {
  products(first: 50, after: $cursor) {
    edges {
      node {
        id
        title
        variants(first: 100) {
          edges {
            node {
              id
              sku
              inventoryItem {
                id
                inventoryLevels(first: 50) {
                  nodes {
                    location {
                      id
                      name
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
    pageInfo {
      hasNextPage
      endCursor
    }
  }
}
```

## Recomendaciones para Prevenir Futuros Problemas

### 1. Mejorar Manejo de Errores en ProductRepository

```php
public function create(Product $product)
{
    try {
        $query = "INSERT INTO ctrlCreateProducts...";
        $stmt = $this->db->prepare($query);
        // ... bind params ...
        $result = $stmt->execute();

        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            throw new \Exception("Failed to insert product: " . json_encode($errorInfo));
        }

        return $result;
    } catch (\PDOException $e) {
        // Log error y re-lanzar
        error_log("Database error creating product: " . $e->getMessage());
        throw $e;
    }
}
```

### 2. Agregar Validación en saveProductsFromResponses

```php
private function saveProductsFromResponses(array $shopifyResponses)
{
    foreach ($shopifyResponses as $response) {
        // Validar estructura
        if (!isset($response['data'])) {
            Logger::log($this->logFile, "WARNING: Unexpected response structure: " . json_encode($response));
            continue;
        }

        // Intentar guardar y capturar errores
        try {
            if (isset($response['data']['productSet']['product']['variants']['nodes'])) {
                foreach ($response['data']['productSet']['product']['variants']['nodes'] as $node) {
                    // ...
                    $result = $this->productRepository->create($product);
                    if (!$result) {
                        Logger::log($this->logFile, "ERROR: Failed to create product in DB: " . $product->sku);
                    }
                }
            }
        } catch (\Exception $e) {
            Logger::log($this->logFile, "EXCEPTION saving product: " . $e->getMessage());
        }
    }
}
```

### 3. Implementar Retry Logic

Agregar reintentos automáticos cuando falla el guardado en BD:

```php
private function createProductWithRetry(Product $product, $maxRetries = 3)
{
    $attempts = 0;
    while ($attempts < $maxRetries) {
        try {
            return $this->productRepository->create($product);
        } catch (\Exception $e) {
            $attempts++;
            if ($attempts >= $maxRetries) {
                throw $e;
            }
            sleep(1); // Wait 1 second before retry
        }
    }
}
```

### 4. Agregar Tabla de Auditoría

Crear tabla para registrar productos creados en Shopify pero no en BD:

```sql
CREATE TABLE ctrlCreateProductsFailures (
    id INT IDENTITY(1,1) PRIMARY KEY,
    sku VARCHAR(255),
    shopify_product_id VARCHAR(50),
    shopify_variant_id VARCHAR(50),
    error_message VARCHAR(MAX),
    shopify_response VARCHAR(MAX),
    created_at DATETIME DEFAULT GETDATE()
);
```

## Verificación Post-Reparación

Después de ejecutar el script de reparación, verificar:

### 1. Contar registros por tienda
```sql
SELECT cia_cod, COUNT(*) as total
FROM ctrlCreateProducts
GROUP BY cia_cod;
```

### 2. Verificar SKUs específicos
```sql
SELECT sku, locacion, prod_id, vari_id, inve_id, nota, audit_date
FROM ctrlCreateProducts
WHERE sku IN ('SKU1', 'SKU2', 'SKU3');
```

### 3. Identificar posibles duplicados
```sql
SELECT sku, locacion, COUNT(*) as duplicates
FROM ctrlCreateProducts
GROUP BY sku, locacion
HAVING COUNT(*) > 1;
```

### 4. Ver productos reparados recientemente
```sql
SELECT TOP 100 *
FROM ctrlCreateProducts
WHERE nota LIKE '%Reparado por script%'
ORDER BY audit_date DESC;
```
