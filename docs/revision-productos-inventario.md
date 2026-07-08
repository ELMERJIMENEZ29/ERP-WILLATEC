# Revision de productos e inventario

## Objetivo

Preparar `productos` como inventario maestro del ERP para una futura integracion con WooCommerce. WooCommerce sera un canal de venta que debe leer o recibir stock desde ERP-WILLATEC, sin convertirse en la fuente principal del inventario.

No se crearon pantallas nuevas. La pantalla actual de productos internos sigue siendo la entrada operativa para registrar productos de almacen.

## Tablas actuales relacionadas

### productos

Tabla principal de productos internos. Antes tenia campos comerciales basicos como `nombre`, `marca`, `modelo`, `codigo`, `descripcion`, `precio_referencial`, `unidad_medida`, `activo`, `stock`, `categoria_id`, `imagen` y `estado`.

Se preparo con campos de inventario y WooCommerce:

- `sku`: identificador principal para integracion. Es unico y requerido por validacion cuando `controla_stock = true`.
- `codigo_barras`: identificador fisico opcional.
- `tipo_producto`: soporta `stock`, `servicio`, `externo`, `personalizado`.
- `controla_stock`: define si el producto mueve inventario.
- `stock_actual`: stock fisico real.
- `stock_reservado`: stock separado por operaciones pendientes.
- `stock_disponible`: stock vendible, calculado como `stock_actual - stock_reservado`.
- `stock_minimo`: umbral operativo para alertas futuras.
- `costo_unitario`: costo interno.
- `precio_venta`: precio sugerido de venta.
- `moneda_id`: moneda asociada al precio.
- `ultima_sincronizacion`: fecha de ultima sincronizacion con WooCommerce.

El campo legado `stock` se conserva por compatibilidad con frontend y flujos existentes. El servicio de inventario lo sincroniza con `stock_actual`.

### categorias

Catalogo de categorias de productos internos. Se actualizo a:

- LAPTOPS
- ACCESORIOS
- PERIFERICOS
- COMPUTADORAS
- LICENCIAS
- SERVIDORES
- GADGETS
- SUMINISTROS
- REDES
- SEGURIDAD
- COMPONENTES
- ALMACENAMIENTO

La migracion mantiene los IDs 1-12 para no romper productos ya relacionados.

### productos_externos

Catalogo separado para items externos historicos o usados en cotizaciones. Tiene `fingerprint` unico, datos descriptivos, proveedor, costo referencial y stock propio. No se modifico su flujo para evitar mezclarlo con inventario maestro.

### cotizacion_items

Guarda snapshot de cada item cotizado: descripcion, marca, codigo, unidad, costos, precio, imagen y stock. Puede apuntar a `producto_id` o `producto_externo_id`, pero conserva datos propios para no depender de cambios posteriores del catalogo.

### inventario_movimientos

Nueva tabla para auditar cambios de inventario:

- `producto_id`
- `tipo_movimiento`: entrada, salida, reserva, liberacion_reserva, devolucion, ajuste_manual, sincronizacion_woocommerce.
- `cantidad`
- `stock_antes`
- `stock_despues`
- `referencia_tipo`, `referencia_id`
- `origen`
- `idempotency_key`
- `observacion`
- `created_by`

`idempotency_key` evita descuentos dobles.

### woocommerce_productos

Nueva tabla de mapeo ERP - WooCommerce:

- `producto_id`
- `woocommerce_store_id`
- `woo_product_id`
- `woo_variation_id`
- `woo_parent_id`
- `woo_sku`
- `manage_stock`
- ultimos stocks enviados/recibidos
- estado/error de ultima sincronizacion

Soporta productos simples y variaciones.

### woocommerce_sync_logs

Nueva tabla de logs para auditoria de sincronizacion:

- `tipo`
- `direccion`
- `endpoint`
- `payload`
- `response`
- `status_code`
- `estado`
- `mensaje_error`
- `referencia_tipo`, `referencia_id`

## Uso actual en cotizaciones

Las cotizaciones usan `cotizacion_items` como snapshot. Esto es correcto y no debe romperse: una cotizacion aprobada debe conservar descripcion, precio, marca, codigo e imagen aunque el producto cambie luego.

Los productos internos se vinculan por `producto_id`. Los externos se vinculan por `producto_externo_id`.

No se implemento reserva automatica al aprobar cotizaciones porque aun no hay una regla funcional final. Queda recomendado definir si una cotizacion aprobada debe reservar stock o si la salida debe ocurrir al generar OC.

## Cambios realizados

- Se preparo `productos` con campos de inventario maestro.
- Se agrego `sku` unico y validado.
- Se agrego `InventarioService` para centralizar entradas, salidas, reservas, liberaciones y ajustes.
- Se reemplazo el descuento directo de stock en `OrdenCompraService` por `InventarioService::registrarSalida`.
- Se agrego `inventario_movimientos`.
- Se agrego mapeo WooCommerce en `woocommerce_productos`.
- Se agrego log de sincronizacion en `woocommerce_sync_logs`.
- Se agrego servicio base `WooCommerceService`.
- Se agrego webhook inicial `POST /api/woocommerce/webhook/orders`.
- Se agregaron endpoints internos de inventario y WooCommerce.
- Se mantuvo `productos_externos` sin cambios.
- Se mantuvo el frontend en la pantalla actual, sin pantallas nuevas.

## Endpoints preparados

- `GET /api/productos/{producto}/inventario`
- `GET /api/productos/{producto}/movimientos`
- `POST /api/productos/{producto}/ajustar-stock`
- `POST /api/woocommerce/productos/mapear`
- `POST /api/woocommerce/productos/{producto}/sync-stock`
- `GET /api/woocommerce/sync-logs`
- `POST /api/woocommerce/webhook/orders`

## Reglas de stock

- Si `controla_stock = false`, no se descuenta inventario.
- No se permite stock negativo.
- `stock_disponible = stock_actual - stock_reservado`.
- Todo cambio hecho por `InventarioService` registra movimiento.
- `idempotency_key` evita duplicar movimientos.
- El campo legado `stock` se actualiza junto a `stock_actual`.

## Configuracion WooCommerce

Variables agregadas a `.env.example`:

- `WOOCOMMERCE_URL`
- `WOOCOMMERCE_CONSUMER_KEY`
- `WOOCOMMERCE_CONSUMER_SECRET`
- `WOOCOMMERCE_WEBHOOK_SECRET`

Tambien se agrego la seccion `woocommerce` en `config/services.php`.

## Problemas detectados

- Antes, `OrdenCompraService` descontaba `productos.stock` directamente sin movimiento, sin idempotencia y sin bloqueo especifico del producto.
- `codigo` no era suficiente como SKU porque no tenia unicidad ni validacion.
- No habia tabla de mapeo WooCommerce.
- No habia logs de sincronizacion.
- No habia servicio centralizado para inventario.
- No existe aun decision funcional sobre reserva de stock por cotizacion aprobada.

## Pendientes recomendados

- Definir si una cotizacion aprobada reserva stock.
- Definir si WooCommerce debe descontar en `on-hold`, `processing` o solo confirmar en `completed`.
- Implementar procesamiento real del webhook de pedidos.
- Crear reconciliacion programada ERP vs WooCommerce.
- Agregar alertas por `stock_minimo`.
- Evaluar multi-tienda si habra mas de un WooCommerce.

## Verificaciones

Comandos recomendados:

```bash
php artisan migrate
php artisan test tests/Feature/InventarioServiceTest.php
php -l app/Services/InventarioService.php
php -l app/Services/WooCommerce/WooCommerceService.php
vendor/bin/pint --dirty --format agent
npx tsc --noEmit
```
