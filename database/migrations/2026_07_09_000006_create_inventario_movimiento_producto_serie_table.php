<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventario_movimiento_producto_serie')) {
            Schema::create('inventario_movimiento_producto_serie', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('inventario_movimiento_id')
                    ->constrained('inventario_movimientos')
                    ->cascadeOnDelete();
                $table->foreignId('producto_serie_id')
                    ->constrained('producto_series')
                    ->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['inventario_movimiento_id', 'producto_serie_id'], 'inv_mov_prod_serie_unique');
                $table->index('producto_serie_id');
            });
        }

        if (
            Schema::hasTable('inventario_movimiento_producto_serie') &&
            Schema::hasColumn('inventario_movimientos', 'producto_serie_id')
        ) {
            DB::table('inventario_movimientos')
                ->select(['id', 'producto_serie_id'])
                ->whereNotNull('producto_serie_id')
                ->orderBy('id')
                ->chunkById(100, function ($movimientos): void {
                    foreach ($movimientos as $movimiento) {
                        $exists = DB::table('inventario_movimiento_producto_serie')
                            ->where('inventario_movimiento_id', $movimiento->id)
                            ->where('producto_serie_id', $movimiento->producto_serie_id)
                            ->exists();

                        if ($exists) {
                            continue;
                        }

                        DB::table('inventario_movimiento_producto_serie')->insert([
                            'inventario_movimiento_id' => $movimiento->id,
                            'producto_serie_id' => $movimiento->producto_serie_id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_movimiento_producto_serie');
    }
};
