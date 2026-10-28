<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oc_emitidas', function (Blueprint $table): void {
            $table->boolean('mostrar_referencia_cotizacion')
                ->default(true)
                ->after('observaciones');
        });
    }

    public function down(): void
    {
        Schema::table('oc_emitidas', function (Blueprint $table): void {
            $table->dropColumn('mostrar_referencia_cotizacion');
        });
    }
};
