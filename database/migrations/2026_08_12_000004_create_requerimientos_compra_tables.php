<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requerimientos_compra', function (Blueprint $table): void {
            $table->id();
            $table->string('numero', 30)->unique();
            $table->string('origen_tipo', 40);
            $table->foreignId('oc_recibida_id')->nullable()->constrained('oc_recibidas')->nullOnDelete();
            $table->string('estado', 40)->default('pendiente');
            $table->string('prioridad', 30)->default('normal');
            $table->foreignId('solicitado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('asignado_a')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['origen_tipo', 'estado']);
            $table->index(['oc_recibida_id', 'estado']);
            $table->index('prioridad');
        });

        Schema::create('requerimiento_compra_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requerimiento_compra_id')->constrained('requerimientos_compra')->cascadeOnDelete();
            $table->foreignId('oc_recibida_item_id')->nullable()->constrained('oc_recibida_items')->nullOnDelete();
            $table->foreignId('cotizacion_item_id')->nullable()->constrained('cotizacion_items')->nullOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->foreignId('producto_externo_id')->nullable()->constrained('productos_externos')->nullOnDelete();
            $table->string('descripcion');
            $table->decimal('cantidad_requerida', 12, 2);
            $table->decimal('cantidad_comprada', 12, 2)->default(0);
            $table->decimal('cantidad_recibida', 12, 2)->default(0);
            $table->string('estado', 40)->default('pendiente');
            $table->timestamps();

            $table->index(['oc_recibida_item_id', 'estado']);
            $table->index(['producto_id', 'estado']);
            $table->index(['producto_externo_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requerimiento_compra_items');
        Schema::dropIfExists('requerimientos_compra');
    }
};
