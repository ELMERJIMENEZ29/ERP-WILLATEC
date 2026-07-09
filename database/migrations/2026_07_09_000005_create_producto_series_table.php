<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('producto_series')) {
            Schema::create('producto_series', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
                $table->string('serie', 150)->nullable();
                $table->string('factura_numero', 100)->nullable();
                $table->string('documento_path')->nullable();
                $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
                $table->decimal('costo_unitario', 12, 4)->nullable();
                $table->foreignId('moneda_id')->nullable()->constrained('monedas')->nullOnDelete();
                $table->date('fecha_ingreso')->nullable();
                $table->string('estado', 30)->default('disponible')->index();
                $table->foreignId('oc_recibida_id')->nullable()->constrained('oc_recibidas')->nullOnDelete();
                $table->foreignId('cotizacion_item_id')->nullable()->constrained('cotizacion_items')->nullOnDelete();
                $table->date('fecha_salida')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['producto_id', 'serie']);
                $table->index(['producto_id', 'estado']);
                $table->index('factura_numero');
            });
        }

        if (Schema::hasTable('producto_series')) {
            DB::table('productos')
                ->select([
                    'id',
                    'serie',
                    'factura_numero',
                    'costo_unitario',
                    'moneda_id',
                    'created_at',
                    'updated_at',
                ])
                ->where(function ($query): void {
                    $query->whereNotNull('serie')
                        ->orWhereNotNull('factura_numero');
                })
                ->orderBy('id')
                ->chunkById(100, function ($productos): void {
                    foreach ($productos as $producto) {
                        $exists = DB::table('producto_series')
                            ->where('producto_id', $producto->id)
                            ->where('serie', $producto->serie)
                            ->exists();

                        if ($exists) {
                            continue;
                        }

                        DB::table('producto_series')->insert([
                            'producto_id' => $producto->id,
                            'serie' => $producto->serie,
                            'factura_numero' => $producto->factura_numero,
                            'costo_unitario' => $producto->costo_unitario,
                            'moneda_id' => $producto->moneda_id,
                            'fecha_ingreso' => null,
                            'estado' => 'disponible',
                            'created_at' => $producto->created_at ?? now(),
                            'updated_at' => $producto->updated_at ?? now(),
                        ]);
                    }
                });
        }

        Schema::table('inventario_movimientos', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventario_movimientos', 'producto_serie_id')) {
                $table->foreignId('producto_serie_id')
                    ->nullable()
                    ->after('producto_id')
                    ->constrained('producto_series')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventario_movimientos', function (Blueprint $table): void {
            if (Schema::hasColumn('inventario_movimientos', 'producto_serie_id')) {
                $table->dropConstrainedForeignId('producto_serie_id');
            }
        });

        Schema::dropIfExists('producto_series');
    }
};
