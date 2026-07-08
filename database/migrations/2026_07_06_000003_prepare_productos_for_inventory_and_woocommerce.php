<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (! Schema::hasColumn('productos', 'sku')) {
                $table->string('sku')->nullable()->unique();
            }

            if (! Schema::hasColumn('productos', 'codigo_barras')) {
                $table->string('codigo_barras')->nullable()->index();
            }

            if (! Schema::hasColumn('productos', 'tipo_producto')) {
                $table->string('tipo_producto', 30)->default('stock')->index();
            }

            if (! Schema::hasColumn('productos', 'controla_stock')) {
                $table->boolean('controla_stock')->default(true);
            }

            if (! Schema::hasColumn('productos', 'stock_actual')) {
                $table->decimal('stock_actual', 12, 2)->default(0);
            }

            if (! Schema::hasColumn('productos', 'stock_reservado')) {
                $table->decimal('stock_reservado', 12, 2)->default(0);
            }

            if (! Schema::hasColumn('productos', 'stock_disponible')) {
                $table->decimal('stock_disponible', 12, 2)->default(0);
            }

            if (! Schema::hasColumn('productos', 'stock_minimo')) {
                $table->decimal('stock_minimo', 12, 2)->default(0);
            }

            if (! Schema::hasColumn('productos', 'costo_unitario')) {
                $table->decimal('costo_unitario', 12, 2)->default(0);
            }

            if (! Schema::hasColumn('productos', 'precio_venta')) {
                $table->decimal('precio_venta', 12, 2)->default(0);
            }

            if (! Schema::hasColumn('productos', 'moneda_id')) {
                $table->foreignId('moneda_id')->nullable()->constrained('monedas')->nullOnDelete();
            }

            if (! Schema::hasColumn('productos', 'ultima_sincronizacion')) {
                $table->timestamp('ultima_sincronizacion')->nullable();
            }
        });

        DB::table('productos')
            ->select('codigo')
            ->whereNull('sku')
            ->whereNotNull('codigo')
            ->groupBy('codigo')
            ->havingRaw('COUNT(*) = 1')
            ->orderBy('codigo')
            ->chunk(100, function ($codigos): void {
                foreach ($codigos as $codigo) {
                    DB::table('productos')
                        ->whereNull('sku')
                        ->where('codigo', $codigo->codigo)
                        ->update(['sku' => $codigo->codigo]);
                }
            });

        DB::table('productos')->orderBy('id')->chunkById(100, function ($productos): void {
            foreach ($productos as $producto) {
                $stockActual = (float) ($producto->stock_actual ?? $producto->stock ?? 0);
                $stockReservado = (float) ($producto->stock_reservado ?? 0);
                $stockDisponible = max(0, $stockActual - $stockReservado);

                DB::table('productos')
                    ->where('id', $producto->id)
                    ->update([
                        'stock_actual' => $stockActual,
                        'stock_reservado' => $stockReservado,
                        'stock_disponible' => $stockDisponible,
                        'stock' => (int) round($stockActual),
                        'tipo_producto' => $producto->tipo_producto ?? 'stock',
                        'controla_stock' => $producto->controla_stock ?? true,
                    ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            foreach ([
                'sku',
                'codigo_barras',
                'tipo_producto',
                'controla_stock',
                'stock_actual',
                'stock_reservado',
                'stock_disponible',
                'stock_minimo',
                'costo_unitario',
                'precio_venta',
                'ultima_sincronizacion',
            ] as $column) {
                if (Schema::hasColumn('productos', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('productos', 'moneda_id')) {
                $table->dropConstrainedForeignId('moneda_id');
            }
        });
    }
};
