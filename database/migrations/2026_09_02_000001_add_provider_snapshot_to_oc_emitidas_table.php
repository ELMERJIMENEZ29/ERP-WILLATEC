<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oc_emitidas', function (Blueprint $table): void {
            if (! Schema::hasColumn('oc_emitidas', 'proveedor_id')) {
                $table->foreignId('proveedor_id')
                    ->nullable()
                    ->after('proveedor')
                    ->constrained('proveedores')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('oc_emitidas', 'proveedor_ruc')) {
                $table->string('proveedor_ruc', 20)->nullable()->after('proveedor_id');
            }

            if (! Schema::hasColumn('oc_emitidas', 'proveedor_direccion')) {
                $table->string('proveedor_direccion')->nullable()->after('proveedor_ruc');
            }

            if (! Schema::hasColumn('oc_emitidas', 'proveedor_telefono')) {
                $table->string('proveedor_telefono', 50)->nullable()->after('proveedor_direccion');
            }

            if (! Schema::hasColumn('oc_emitidas', 'proveedor_contacto')) {
                $table->string('proveedor_contacto')->nullable()->after('proveedor_telefono');
            }

            if (! Schema::hasColumn('oc_emitidas', 'proveedor_correo')) {
                $table->string('proveedor_correo')->nullable()->after('proveedor_contacto');
            }
        });
    }

    public function down(): void
    {
        Schema::table('oc_emitidas', function (Blueprint $table): void {
            foreach ([
                'proveedor_correo',
                'proveedor_contacto',
                'proveedor_telefono',
                'proveedor_direccion',
                'proveedor_ruc',
            ] as $column) {
                if (Schema::hasColumn('oc_emitidas', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('oc_emitidas', 'proveedor_id')) {
                $table->dropConstrainedForeignId('proveedor_id');
            }
        });
    }
};
