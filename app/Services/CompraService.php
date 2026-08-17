<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\CompraItem;
use App\Models\OcEmitida;
use App\Models\Proveedor;
use App\Models\RequerimientoCompra;
use App\Models\RequerimientoCompraItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CompraService
{
    /**
     * Crear una compra en estado borrador.
     *
     * IMPORTANTE:
     * Crear una compra NO modifica cantidad_comprada,
     * stock ni Kardex.
     */
    public function crear(array $data, ?int $userId = null): Compra
    {
        return DB::transaction(function () use ($data, $userId) {

            $this->validarModalidad(
                $data['modalidad'] ?? Compra::MODALIDAD_DIRECTA,
                $data['oc_emitida_id'] ?? null,
                $data['proveedor_id']
            );

            $compra = Compra::create([
                // Número temporal para evitar carreras con MAX(numero).
                'numero' => 'TMP-'.Str::uuid(),
                'proveedor_id' => $data['proveedor_id'],
                'oc_emitida_id' => $data['oc_emitida_id'] ?? null,
                'modalidad' => $data['modalidad'] ?? Compra::MODALIDAD_DIRECTA,
                'estado' => Compra::ESTADO_BORRADOR,
                'fecha_compra' => $data['fecha_compra'] ?? null,
                'moneda_id' => $data['moneda_id'] ?? null,
                'observacion' => $data['observacion'] ?? null,
                'creado_por' => $userId,
            ]);

            // Ya tenemos un ID único.
            $compra->numero = sprintf('CMP-%06d', $compra->id);
            $compra->save();

            $requerimientosAfectados = [];

            foreach ($data['items'] as $itemData) {

                $requerimientoItem = null;

                if (! empty($itemData['requerimiento_compra_item_id'])) {

                    $requerimientoItem = RequerimientoCompraItem::query()
                        ->with('requerimiento')
                        ->lockForUpdate()
                        ->findOrFail(
                            $itemData['requerimiento_compra_item_id']
                        );

                    if (
                        $requerimientoItem->requerimiento &&
                        $requerimientoItem->requerimiento->estado === 'cancelado'
                    ) {
                        throw ValidationException::withMessages([
                            'items' => 'No se puede comprar desde un requerimiento cancelado.',
                        ]);
                    }

                    $this->validarCantidadDisponibleParaCompra(
                        $requerimientoItem,
                        (float) $itemData['cantidad']
                    );

                    $requerimientosAfectados[] =
                        $requerimientoItem->requerimiento_compra_id;
                }

                $descripcion = $itemData['descripcion']
                    ?? $requerimientoItem?->descripcion;

                if (! $descripcion) {
                    throw ValidationException::withMessages([
                        'items' => 'Cada item debe tener una descripción.',
                    ]);
                }

                CompraItem::create([
                    'compra_id' => $compra->id,

                    'requerimiento_compra_item_id' => $requerimientoItem?->id,

                    'oc_emitida_item_id' => $itemData['oc_emitida_item_id'] ?? null,

                    'producto_id' => $itemData['producto_id']
                        ?? $requerimientoItem?->producto_id,

                    'producto_externo_id' => $itemData['producto_externo_id']
                        ?? $requerimientoItem?->producto_externo_id,

                    'descripcion' => $descripcion,

                    'cantidad' => $itemData['cantidad'],

                    'cantidad_recibida' => 0,

                    'costo_unitario_estimado' => $itemData['costo_unitario_estimado'] ?? null,

                    'moneda_id' => $itemData['moneda_id']
                        ?? $data['moneda_id']
                        ?? null,

                    'estado' => CompraItem::ESTADO_PENDIENTE,
                ]);
            }

            $this->recalcularTotales($compra);

            foreach (array_unique($requerimientosAfectados) as $id) {
                $this->recalcularEstadoRequerimiento($id);
            }

            return $compra->fresh([
                'items',
                'proveedor',
                'moneda',
                'ocEmitida',
                'creadoPor',
            ]);
        });
    }

    /**
     * Confirmar que la compra realmente fue realizada.
     *
     * Aquí recién afecta cantidad_comprada.
     * NO afecta stock.
     */
    public function confirmar(Compra $compra): Compra
    {
        return DB::transaction(function () use ($compra) {

            $compra = Compra::query()
                ->lockForUpdate()
                ->findOrFail($compra->id);

            // Idempotencia.
            if (in_array($compra->estado, [
                Compra::ESTADO_CONFIRMADA,
                Compra::ESTADO_PARCIALMENTE_RECIBIDA,
                Compra::ESTADO_RECIBIDA,
            ], true)) {
                return $compra->fresh(['items']);
            }

            if ($compra->estado === Compra::ESTADO_CANCELADA) {
                throw ValidationException::withMessages([
                    'compra' => 'Una compra cancelada no puede confirmarse.',
                ]);
            }

            $this->validarModalidad(
                $compra->modalidad,
                $compra->oc_emitida_id,
                $compra->proveedor_id
            );

            $items = $compra->items()->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'La compra no contiene items.',
                ]);
            }

            /*
             * Agrupamos porque una misma compra podría,
             * accidental o intencionalmente, tener más de un item
             * vinculado al mismo requerimiento_compra_item.
             */
            $agrupados = $items
                ->filter(fn ($item) => $item->requerimiento_compra_item_id)
                ->groupBy('requerimiento_compra_item_id');

            foreach ($agrupados as $requerimientoItemId => $compraItems) {

                $requerimientoItem = RequerimientoCompraItem::query()
                    ->lockForUpdate()
                    ->findOrFail($requerimientoItemId);

                $requerimiento = RequerimientoCompra::query()
                    ->lockForUpdate()
                    ->findOrFail($requerimientoItem->requerimiento_compra_id);

                if ($requerimiento->estado === 'cancelado') {
                    throw ValidationException::withMessages([
                        'items' => 'No se puede confirmar una compra asociada a un requerimiento cancelado.',
                    ]);
                }

                $cantidadEstaCompra = (float) $compraItems->sum(
                    fn ($item) => (float) $item->cantidad
                );

                $compradoAnteriormente = $this
                    ->cantidadCompradaConfirmada(
                        $requerimientoItem->id,
                        $compra->id
                    );

                $cantidadRequerida =
                    (float) $requerimientoItem->cantidad_requerida;

                if (
                    $compradoAnteriormente + $cantidadEstaCompra
                    > $cantidadRequerida + 0.00001
                ) {
                    throw ValidationException::withMessages([
                        'items' => "La compra supera la cantidad pendiente del requerimiento item {$requerimientoItem->id}.",
                    ]);
                }
            }

            $compra->estado = Compra::ESTADO_CONFIRMADA;

            if (! $compra->fecha_compra) {
                $compra->fecha_compra = now()->toDateString();
            }

            $compra->save();

            $requerimientosAfectados = [];

            foreach ($agrupados as $requerimientoItemId => $compraItems) {

                $requerimientoItem = RequerimientoCompraItem::query()
                    ->lockForUpdate()
                    ->findOrFail($requerimientoItemId);

                $this->recalcularCantidadComprada($requerimientoItem);

                $requerimientosAfectados[] =
                    $requerimientoItem->requerimiento_compra_id;
            }

            foreach (array_unique($requerimientosAfectados) as $id) {
                $this->recalcularEstadoRequerimiento($id);
            }

            return $compra->fresh([
                'items',
                'proveedor',
                'moneda',
                'ocEmitida',
                'creadoPor',
            ]);
        });
    }

    /**
     * Cancelar compra.
     *
     * Si estaba confirmada, cantidad_comprada se recalcula.
     * NO toca stock.
     */
    public function cancelar(Compra $compra): Compra
    {
        return DB::transaction(function () use ($compra) {

            $compra = Compra::query()
                ->lockForUpdate()
                ->findOrFail($compra->id);

            if ($compra->estado === Compra::ESTADO_CANCELADA) {
                return $compra->fresh(['items']);
            }

            /*
             * Desde Fase 5, si ya existen recepciones confirmadas,
             * aquí deberá bloquearse la cancelación.
             */
            if (in_array($compra->estado, [
                Compra::ESTADO_PARCIALMENTE_RECIBIDA,
                Compra::ESTADO_RECIBIDA,
            ], true)) {
                throw ValidationException::withMessages([
                    'compra' => 'No se puede cancelar una compra que ya tiene recepción de productos.',
                ]);
            }

            $requerimientoItems = $compra->items()
                ->whereNotNull('requerimiento_compra_item_id')
                ->pluck('requerimiento_compra_item_id')
                ->unique();

            $compra->estado = Compra::ESTADO_CANCELADA;
            $compra->save();

            $requerimientosAfectados = [];

            foreach ($requerimientoItems as $id) {

                $requerimientoItem = RequerimientoCompraItem::query()
                    ->lockForUpdate()
                    ->findOrFail($id);

                $this->recalcularCantidadComprada($requerimientoItem);

                $requerimientosAfectados[] =
                    $requerimientoItem->requerimiento_compra_id;
            }

            foreach (array_unique($requerimientosAfectados) as $id) {
                $this->recalcularEstadoRequerimiento($id);
            }

            return $compra->fresh([
                'items',
                'proveedor',
                'moneda',
                'ocEmitida',
            ]);
        });
    }

    private function validarModalidad(
        string $modalidad,
        ?int $ocEmitidaId,
        ?int $proveedorId
    ): void {
        if (! in_array($modalidad, [
            Compra::MODALIDAD_DIRECTA,
            Compra::MODALIDAD_OC_PROVEEDOR,
        ], true)) {
            throw ValidationException::withMessages([
                'modalidad' => 'Modalidad de compra inválida.',
            ]);
        }

        /*
     * COMPRA DIRECTA
     *
     * No debe tener OC emitida.
     */
        if ($modalidad === Compra::MODALIDAD_DIRECTA) {
            if ($ocEmitidaId) {
                throw ValidationException::withMessages([
                    'oc_emitida_id' => 'Una compra directa no debe tener OC emitida.',
                ]);
            }

            return;
        }

        /*
     * COMPRA CON OC A PROVEEDOR
     *
     * Debe existir una OC emitida.
     */
        if (! $ocEmitidaId) {
            throw ValidationException::withMessages([
                'oc_emitida_id' => 'La compra con OC proveedor requiere una OC emitida.',
            ]);
        }

        if (! $proveedorId) {
            throw ValidationException::withMessages([
                'proveedor_id' => 'La compra requiere un proveedor.',
            ]);
        }

        $ocEmitida = OcEmitida::query()
            ->find($ocEmitidaId);

        if (! $ocEmitida) {
            throw ValidationException::withMessages([
                'oc_emitida_id' => 'La OC emitida seleccionada no existe.',
            ]);
        }

        $proveedor = Proveedor::query()
            ->find($proveedorId);

        if (! $proveedor) {
            throw ValidationException::withMessages([
                'proveedor_id' => 'El proveedor seleccionado no existe.',
            ]);
        }

        /*
     * Actualmente oc_emitidas mantiene el proveedor
     * como texto legacy, por lo que comparamos
     * contra proveedores.nombre.
     *
     * Normalizamos mayúsculas, minúsculas y espacios
     * para evitar falsos negativos.
     */
        $nombreProveedorOc = $this->normalizarNombreProveedor(
            $ocEmitida->proveedor
        );

        $nombreProveedorCompra = $this->normalizarNombreProveedor(
            $proveedor->nombre
        );

        if (
            $nombreProveedorOc === '' ||
            $nombreProveedorCompra === '' ||
            $nombreProveedorOc !== $nombreProveedorCompra
        ) {
            throw ValidationException::withMessages([
                'oc_emitida_id' => 'La OC emitida seleccionada no corresponde al proveedor de la compra.',
            ]);
        }
    }

    private function normalizarNombreProveedor(?string $nombre): string
    {
        $nombre = trim((string) $nombre);

        if ($nombre === '') {
            return '';
        }

        $nombre = preg_replace('/\s+/u', ' ', $nombre) ?? $nombre;

        return mb_strtolower($nombre, 'UTF-8');
    }

    /**
     * Controla borradores y compras confirmadas.
     *
     * De esta forma tampoco podemos crear varios borradores
     * que juntos superen el requerimiento.
     */
    private function validarCantidadDisponibleParaCompra(
        RequerimientoCompraItem $item,
        float $cantidadNueva
    ): void {
        if ($cantidadNueva <= 0) {
            throw ValidationException::withMessages([
                'cantidad' => 'La cantidad de compra debe ser mayor que cero.',
            ]);
        }

        $cantidadComprometida = (float) CompraItem::query()
            ->where(
                'requerimiento_compra_item_id',
                $item->id
            )
            ->whereHas('compra', function ($query) {
                $query->whereIn('estado', [
                    Compra::ESTADO_BORRADOR,
                    Compra::ESTADO_CONFIRMADA,
                    Compra::ESTADO_PARCIALMENTE_RECIBIDA,
                    Compra::ESTADO_RECIBIDA,
                ]);
            })
            ->sum('cantidad');

        $cantidadRequerida =
            (float) $item->cantidad_requerida;

        if (
            $cantidadComprometida + $cantidadNueva
            > $cantidadRequerida + 0.00001
        ) {
            throw ValidationException::withMessages([
                'cantidad' => 'La cantidad supera el saldo disponible del requerimiento.',
            ]);
        }
    }

    private function cantidadCompradaConfirmada(
        int $requerimientoItemId,
        ?int $excluirCompraId = null
    ): float {
        $query = CompraItem::query()
            ->where(
                'requerimiento_compra_item_id',
                $requerimientoItemId
            )
            ->whereHas('compra', function ($query) use ($excluirCompraId) {

                $query->whereIn('estado', [
                    Compra::ESTADO_CONFIRMADA,
                    Compra::ESTADO_PARCIALMENTE_RECIBIDA,
                    Compra::ESTADO_RECIBIDA,
                ]);

                if ($excluirCompraId) {
                    $query->where('id', '!=', $excluirCompraId);
                }
            });

        return (float) $query->sum('cantidad');
    }

    private function recalcularCantidadComprada(
        RequerimientoCompraItem $item
    ): void {
        $cantidadComprada =
            $this->cantidadCompradaConfirmada($item->id);

        $cantidadRequerida =
            (float) $item->cantidad_requerida;

        $item->cantidad_comprada = $cantidadComprada;

        if (
            $cantidadComprada >=
            $cantidadRequerida - 0.00001
        ) {
            $item->estado = 'comprado';
        } elseif ($cantidadComprada > 0) {
            $item->estado = 'parcialmente_comprado';
        } else {
            $item->estado = 'pendiente';
        }

        $item->save();
    }

    private function recalcularEstadoRequerimiento(
        int $requerimientoId
    ): void {
        $requerimiento = RequerimientoCompra::query()
            ->with('items')
            ->lockForUpdate()
            ->findOrFail($requerimientoId);

        if ($requerimiento->estado === 'cancelado') {
            return;
        }

        if ($requerimiento->items->isEmpty()) {
            return;
        }

        $todosComprados = $requerimiento->items->every(
            fn ($item) => (float) $item->cantidad_comprada >=
                (float) $item->cantidad_requerida - 0.00001
        );

        if ($todosComprados) {
            $requerimiento->estado = 'comprado';
            $requerimiento->save();

            return;
        }

        $hayCompraConfirmada = $requerimiento->items->contains(
            fn ($item) => (float) $item->cantidad_comprada > 0
        );

        if ($hayCompraConfirmada) {
            $requerimiento->estado = 'parcialmente_comprado';
            $requerimiento->save();

            return;
        }

        $itemIds = $requerimiento->items->pluck('id');

        $hayCompraBorrador = CompraItem::query()
            ->whereIn(
                'requerimiento_compra_item_id',
                $itemIds
            )
            ->whereHas('compra', function ($query) {
                $query->where(
                    'estado',
                    Compra::ESTADO_BORRADOR
                );
            })
            ->exists();

        $requerimiento->estado = $hayCompraBorrador
            ? 'en_gestion'
            : 'pendiente';

        $requerimiento->save();
    }

    private function recalcularTotales(
        Compra $compra
    ): void {
        $items = $compra->items()->get();

        if ($items->isEmpty()) {
            $compra->subtotal_estimado = null;
            $compra->total_estimado = null;
            $compra->save();

            return;
        }

        /*
         * No mostramos un total engañoso si algún item
         * todavía no tiene costo estimado.
         */
        $todosTienenCosto = $items->every(
            fn ($item) => $item->costo_unitario_estimado !== null
        );

        if (! $todosTienenCosto) {
            $compra->subtotal_estimado = null;
            $compra->total_estimado = null;
            $compra->save();

            return;
        }

        $total = $items->sum(function ($item) {
            return (float) $item->cantidad
                * (float) $item->costo_unitario_estimado;
        });

        $compra->subtotal_estimado = round($total, 2);

        /*
         * Todavía no calculamos IGV contable aquí.
         * El comprobante real llegará posteriormente.
         */
        $compra->total_estimado = round($total, 2);

        $compra->save();
    }
}
