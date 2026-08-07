<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licitaciones', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 30)->default('licitacion');
            $table->string('empresa');
            $table->string('requerimiento');
            $table->dateTime('vigencia');
            $table->string('categoria')->nullable();
            $table->string('estado', 40)->default('sin_atender');
            $table->text('observacion')->nullable();
            $table->foreignId('ejecutivo_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ejecutivo_nombre')->nullable();
            $table->string('ejecutivo_email')->nullable();
            $table->foreignId('asignado_a')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('asignado_en')->nullable();
            $table->string('asignado_por')->nullable();
            $table->boolean('es_nueva')->default(true);
            $table->string('creado_por')->nullable();
            $table->string('modificado_por')->nullable();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('modificado_en')->nullable();
            $table->string('garantia')->nullable();
            $table->string('plazo')->nullable();
            $table->string('carpeta_servidor')->nullable();
            $table->string('forma_pago', 40)->nullable();
            $table->string('destino_entrega')->nullable();
            $table->string('wherex_id')->nullable();
            $table->string('wherex_url')->nullable();
            $table->text('comentarios_generales')->nullable();
            $table->string('motivo_cierre')->nullable();
            $table->text('comentario_cierre')->nullable();
            $table->json('perdida_info')->nullable();
            $table->json('lecciones_aprendidas')->nullable();
            $table->timestamps();

            $table->index(['tipo', 'estado']);
            $table->index('vigencia');
            $table->index('empresa');
            $table->index('ejecutivo_id');
            $table->index('asignado_a');
        });

        Schema::create('licitacion_archivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('licitacion_id')->constrained('licitaciones')->cascadeOnDelete();
            $table->string('tipo_archivo', 40)->default('adjunto');
            $table->string('nombre');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('tamanio')->nullable();
            $table->longText('data_url')->nullable();
            $table->string('path')->nullable();
            $table->string('creado_por')->nullable();
            $table->timestamp('creado_en')->nullable();
            $table->timestamps();

            $table->index(['licitacion_id', 'tipo_archivo']);
        });

        Schema::create('licitacion_comentarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('licitacion_id')->constrained('licitaciones')->cascadeOnDelete();
            $table->string('usuario')->nullable();
            $table->text('comentario');
            $table->timestamp('fecha')->nullable();
            $table->timestamps();

            $table->index('licitacion_id');
        });

        Schema::create('licitacion_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('licitacion_id')->constrained('licitaciones')->cascadeOnDelete();
            $table->string('usuario')->nullable();
            $table->string('tipo', 40)->default('estado');
            $table->text('descripcion');
            $table->timestamp('fecha')->nullable();
            $table->timestamps();

            $table->index(['licitacion_id', 'tipo']);
        });

        Schema::create('licitacion_cotizaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('licitacion_id')->constrained('licitaciones')->cascadeOnDelete();
            $table->foreignId('cotizacion_id')->nullable()->constrained('cotizaciones')->nullOnDelete();
            $table->string('numero')->nullable();
            $table->string('estado')->nullable();
            $table->decimal('monto', 14, 2)->nullable();
            $table->string('moneda', 20)->nullable();
            $table->string('creado_por')->nullable();
            $table->timestamp('creado_en')->nullable();
            $table->timestamps();

            $table->index('licitacion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licitacion_cotizaciones');
        Schema::dropIfExists('licitacion_historial');
        Schema::dropIfExists('licitacion_comentarios');
        Schema::dropIfExists('licitacion_archivos');
        Schema::dropIfExists('licitaciones');
    }
};
