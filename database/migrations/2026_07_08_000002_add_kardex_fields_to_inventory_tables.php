<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table): void {
            if (! Schema::hasColumn('productos', 'costo_promedio')) {
                $table->decimal('costo_promedio', 12, 4)->default(0)->after('costo_unitario');
            }

            if (! Schema::hasColumn('productos', 'valor_stock')) {
                $table->decimal('valor_stock', 14, 2)->default(0)->after('costo_promedio');
            }
        });

        Schema::table('inventario_movimientos', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventario_movimientos', 'entrada_cantidad')) {
                $table->decimal('entrada_cantidad', 12, 2)->default(0)->after('cantidad');
            }

            if (! Schema::hasColumn('inventario_movimientos', 'salida_cantidad')) {
                $table->decimal('salida_cantidad', 12, 2)->default(0)->after('entrada_cantidad');
            }

            if (! Schema::hasColumn('inventario_movimientos', 'saldo_cantidad')) {
                $table->decimal('saldo_cantidad', 12, 2)->default(0)->after('stock_despues');
            }

            if (! Schema::hasColumn('inventario_movimientos', 'costo_unitario')) {
                $table->decimal('costo_unitario', 12, 4)->default(0)->after('saldo_cantidad');
            }

            if (! Schema::hasColumn('inventario_movimientos', 'costo_promedio_antes')) {
                $table->decimal('costo_promedio_antes', 12, 4)->default(0)->after('costo_unitario');
            }

            if (! Schema::hasColumn('inventario_movimientos', 'costo_promedio_despues')) {
                $table->decimal('costo_promedio_despues', 12, 4)->default(0)->after('costo_promedio_antes');
            }

            if (! Schema::hasColumn('inventario_movimientos', 'valor_movimiento')) {
                $table->decimal('valor_movimiento', 14, 2)->default(0)->after('costo_promedio_despues');
            }

            if (! Schema::hasColumn('inventario_movimientos', 'valor_stock_despues')) {
                $table->decimal('valor_stock_despues', 14, 2)->default(0)->after('valor_movimiento');
            }

            if (! Schema::hasColumn('inventario_movimientos', 'documento_tipo')) {
                $table->string('documento_tipo', 40)->nullable()->after('observacion');
            }

            if (! Schema::hasColumn('inventario_movimientos', 'documento_numero')) {
                $table->string('documento_numero', 100)->nullable()->after('documento_tipo');
            }

            if (! Schema::hasColumn('inventario_movimientos', 'documento_path')) {
                $table->string('documento_path')->nullable()->after('documento_numero');
            }

            if (! Schema::hasColumn('inventario_movimientos', 'fecha_documento')) {
                $table->date('fecha_documento')->nullable()->after('documento_path');
            }

            if (! Schema::hasColumn('inventario_movimientos', 'proveedor')) {
                $table->string('proveedor')->nullable()->after('fecha_documento');
            }
        });

        Schema::table('oc_recibidas', function (Blueprint $table): void {
            if (! Schema::hasColumn('oc_recibidas', 'factura_numero')) {
                $table->string('factura_numero', 100)->nullable()->after('guia_emision_path');
            }

            if (! Schema::hasColumn('oc_recibidas', 'factura_path')) {
                $table->string('factura_path')->nullable()->after('factura_numero');
            }
        });
    }

    public function down(): void
    {
        Schema::table('oc_recibidas', function (Blueprint $table): void {
            foreach (['factura_path', 'factura_numero'] as $column) {
                if (Schema::hasColumn('oc_recibidas', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('inventario_movimientos', function (Blueprint $table): void {
            foreach ([
                'proveedor',
                'fecha_documento',
                'documento_path',
                'documento_numero',
                'documento_tipo',
                'valor_stock_despues',
                'valor_movimiento',
                'costo_promedio_despues',
                'costo_promedio_antes',
                'costo_unitario',
                'saldo_cantidad',
                'salida_cantidad',
                'entrada_cantidad',
            ] as $column) {
                if (Schema::hasColumn('inventario_movimientos', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('productos', function (Blueprint $table): void {
            foreach (['valor_stock', 'costo_promedio'] as $column) {
                if (Schema::hasColumn('productos', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
