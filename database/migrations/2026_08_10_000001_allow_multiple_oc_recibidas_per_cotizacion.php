<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oc_recibidas', function (Blueprint $table): void {
            try {
                $table->dropUnique(['cotizacion_id']);
            } catch (Throwable) {
                // En algunos entornos el indice ya puede haber sido eliminado.
            }

            try {
                $table->index('cotizacion_id', 'oc_recibidas_cotizacion_id_idx');
            } catch (Throwable) {
                // Mantener idempotente entre PostgreSQL/MySQL/local/produccion.
            }
        });
    }

    public function down(): void
    {
        Schema::table('oc_recibidas', function (Blueprint $table): void {
            try {
                $table->dropIndex('oc_recibidas_cotizacion_id_idx');
            } catch (Throwable) {
                //
            }

            try {
                $table->unique('cotizacion_id');
            } catch (Throwable) {
                // Si ya existen varias OC para una cotizacion, no se puede restaurar el unique.
            }
        });
    }
};
