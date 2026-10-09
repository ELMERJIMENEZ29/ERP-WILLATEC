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
        Schema::table('proveedores', function (Blueprint $table) {
            $table->boolean('tiene_credito')->default(false)->after('activo');
            $table->unsignedSmallInteger('dias_credito')->default(0)->after('tiene_credito');
            $table->decimal('limite_credito', 14, 2)->nullable()->after('dias_credito');
            $table->foreignId('moneda_credito_id')->nullable()->after('limite_credito')->constrained('monedas')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('moneda_credito_id');
            $table->dropColumn(['tiene_credito', 'dias_credito', 'limite_credito']);
        });
    }
};
