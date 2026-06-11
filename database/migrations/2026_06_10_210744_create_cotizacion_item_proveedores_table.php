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
        Schema::create('cotizacion_item_proveedores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_item_id')
                ->constrained('cotizacion_items')
                ->cascadeOnDelete();
            $table->string('nombre');
            $table->text('link')->nullable();
            $table->decimal('precio', 12, 2)->nullable();
            $table->text('notas')->nullable();
            $table->unsignedInteger('orden')->default(1);
            $table->timestamps();

            $table->index(['cotizacion_item_id', 'orden']);
        });

        DB::table('cotizacion_items')
            ->whereNotNull('proveedor')
            ->where('proveedor', '!=', '')
            ->orderBy('id')
            ->select(['id', 'proveedor', 'link_proveedor'])
            ->chunkById(500, function ($items): void {
                $now = now();

                DB::table('cotizacion_item_proveedores')->insert(
                    $items->map(fn ($item): array => [
                        'cotizacion_item_id' => $item->id,
                        'nombre' => $item->proveedor,
                        'link' => $item->link_proveedor,
                        'precio' => null,
                        'notas' => null,
                        'orden' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all()
                );
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotizacion_item_proveedores');
    }
};
