<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oc_recibidas', function (Blueprint $table): void {
            if (! Schema::hasColumn('oc_recibidas', 'estado_comercial')) {
                $table->string('estado_comercial', 40)->default('registrada')->after('estado');
            }

            if (! Schema::hasColumn('oc_recibidas', 'estado_logistico')) {
                $table->string('estado_logistico', 40)->default('pendiente')->after('estado_comercial');
            }

            if (! Schema::hasColumn('oc_recibidas', 'estado_documental')) {
                $table->string('estado_documental', 40)->default('pendiente')->after('estado_logistico');
            }

            if (! Schema::hasColumn('oc_recibidas', 'estado_financiero')) {
                $table->string('estado_financiero', 40)->default('pendiente')->after('estado_documental');
            }
        });
    }

    public function down(): void
    {
        Schema::table('oc_recibidas', function (Blueprint $table): void {
            foreach (['estado_financiero', 'estado_documental', 'estado_logistico', 'estado_comercial'] as $column) {
                if (Schema::hasColumn('oc_recibidas', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
