<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuentas_por_pagar', function (Blueprint $table): void {
            $table->foreignId('comprobante_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('cuentas_por_pagar', function (Blueprint $table): void {
            $table->foreignId('comprobante_id')->nullable(false)->change();
        });
    }
};
