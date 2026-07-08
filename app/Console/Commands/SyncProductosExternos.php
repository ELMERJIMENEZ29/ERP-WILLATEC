<?php

namespace App\Console\Commands;

use App\Models\CotizacionItem;
use App\Models\ProductoExterno;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncProductosExternos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'productos-externos:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza el catalogo de productos externos desde cotizacion_items historicos';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $created = 0;
        $linked = 0;

        CotizacionItem::query()
            ->with('cotizacion:id,moneda_id,plantilla_id')
            ->whereNull('producto_id')
            ->where(function ($query): void {
                $query->whereNull('producto_externo_id')
                    ->orWhere('producto_externo_id', 0);
            })
            ->orderBy('id')
            ->chunkById(500, function ($items) use (&$created, &$linked): void {
                foreach ($items as $item) {
                    DB::transaction(function () use ($item, &$created, &$linked): void {
                        $fingerprint = ProductoExterno::fingerprintFrom($item->toArray());
                        $productoExterno = ProductoExterno::where('fingerprint', $fingerprint)->first();

                        if (! $productoExterno) {
                            $productoExterno = ProductoExterno::create([
                                'descripcion' => $item->descripcion,
                                'marca' => $item->marca,
                                'codigo' => $item->codigo,
                                'unidad_medida' => $item->unidad_medida ?: 'UND',
                                'proveedor' => $item->proveedor,
                                'link_proveedor' => $item->link_proveedor,
                                'costo_base_referencial' => $item->costo_base ?? 0,
                                'moneda_id' => $item->cotizacion?->moneda_id,
                                'precio_incluye_igv' => $this->plantillaIncluyeIgv($item->cotizacion?->plantilla_id),
                                'plantilla_origen_id' => $item->cotizacion?->plantilla_id,
                                'imagen' => $item->imagen,
                                'garantia_meses' => $item->garantia_meses,
                                'disponibilidad_tipo' => $item->disponibilidad_tipo,
                                'disponibilidad_dias' => $item->disponibilidad_dias,
                                'stock' => $item->stock ?? 0,
                                'fingerprint' => $fingerprint,
                                'activo' => true,
                            ]);
                            $created++;
                        }

                        $item->update(['producto_externo_id' => $productoExterno->id]);
                        $linked++;
                    });
                }
            });

        $this->info("Productos externos creados: {$created}");
        $this->info("Items vinculados: {$linked}");

        return self::SUCCESS;
    }

    private function plantillaIncluyeIgv(?int $plantillaId): ?bool
    {
        if (! $plantillaId) {
            return null;
        }

        if (in_array($plantillaId, [1, 2, 3, 4, 5], true)) {
            return in_array($plantillaId, [3, 5], true);
        }

        return null;
    }
}
