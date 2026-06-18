<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('productos_externos', function (Blueprint $table) {
            $table->id();
            $table->text('descripcion');
            $table->string('marca')->nullable();
            $table->string('codigo')->nullable();
            $table->string('unidad_medida')->nullable()->default('UND');
            $table->string('proveedor')->nullable();
            $table->text('link_proveedor')->nullable();
            $table->decimal('costo_base_referencial', 12, 2)->default(0);
            $table->string('imagen')->nullable();
            $table->smallInteger('garantia_meses')->nullable();
            $table->string('disponibilidad_tipo')->nullable();
            $table->smallInteger('disponibilidad_dias')->nullable();
            $table->integer('stock')->default(0);
            $table->string('fingerprint')->unique()->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productos_externos');
    }
};
