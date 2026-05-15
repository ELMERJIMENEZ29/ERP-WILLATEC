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
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->timestamps();
        });

        Schema::table('productos', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->unsignedInteger('stock')->default(0)->after('activo');
                $table->foreignId('categoria_id')
                    ->nullable()
                    ->constrained('categorias')
                    ->nullOnDelete()
                    ->after('stock');
            } else {
                $table->unsignedInteger('stock')->default(0);
                $table->foreignId('categoria_id')
                    ->nullable()
                    ->constrained('categorias')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropForeign(['categoria_id']);
            $table->dropColumn(['categoria_id', 'stock']);
        });

        Schema::dropIfExists('categorias');
    }
};
