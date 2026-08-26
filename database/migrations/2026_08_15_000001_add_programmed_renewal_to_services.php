<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['licencias', 'hostings'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'renovacion_programada')) {
                    $table->boolean('renovacion_programada')->default(false)->after('fecha_renovacion');
                }

                if (! Schema::hasColumn($tableName, 'renovacion_modo')) {
                    $table->string('renovacion_modo', 20)->nullable()->after('renovacion_programada');
                }

                if (! Schema::hasColumn($tableName, 'renovacion_meses')) {
                    $table->unsignedInteger('renovacion_meses')->nullable()->after('renovacion_modo');
                }

                if (! Schema::hasColumn($tableName, 'renovacion_programada_para')) {
                    $table->date('renovacion_programada_para')->nullable()->after('renovacion_meses');
                }

                if (! Schema::hasColumn($tableName, 'renovacion_programada_at')) {
                    $table->timestamp('renovacion_programada_at')->nullable()->after('renovacion_programada_para');
                }

                if (! Schema::hasColumn($tableName, 'renovacion_programada_por')) {
                    $table->foreignId('renovacion_programada_por')
                        ->nullable()
                        ->after('renovacion_programada_at')
                        ->constrained('users')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['licencias', 'hostings'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (Schema::hasColumn($tableName, 'renovacion_programada_por')) {
                    $table->dropConstrainedForeignId('renovacion_programada_por');
                }

                foreach ([
                    'renovacion_programada_at',
                    'renovacion_programada_para',
                    'renovacion_meses',
                    'renovacion_modo',
                    'renovacion_programada',
                ] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
