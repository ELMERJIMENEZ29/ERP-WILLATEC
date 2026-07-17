<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oc_recibidas', function (Blueprint $table) {
            $table->foreignId('orden_compra_cliente_uploaded_by')->nullable()->after('orden_compra_cliente_path')->constrained('users')->nullOnDelete();
            $table->foreignId('guia_emision_uploaded_by')->nullable()->after('guia_emision_path')->constrained('users')->nullOnDelete();
            $table->foreignId('factura_uploaded_by')->nullable()->after('factura_path')->constrained('users')->nullOnDelete();
        });

        Schema::table('oc_emitidas', function (Blueprint $table) {
            $table->foreignId('factura_uploaded_by')->nullable()->after('factura_path')->constrained('users')->nullOnDelete();
            $table->foreignId('comprobante_pago_uploaded_by')->nullable()->after('comprobante_pago_path')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('oc_recibidas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('orden_compra_cliente_uploaded_by');
            $table->dropConstrainedForeignId('guia_emision_uploaded_by');
            $table->dropConstrainedForeignId('factura_uploaded_by');
        });

        Schema::table('oc_emitidas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('factura_uploaded_by');
            $table->dropConstrainedForeignId('comprobante_pago_uploaded_by');
        });
    }
};
