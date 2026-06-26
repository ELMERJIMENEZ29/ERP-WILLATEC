<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizacion_modificaciones', function (Blueprint $table) {
            $table->text('comentario_reenvio_revision')->nullable()->after('comentario_revision');
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion_modificaciones', function (Blueprint $table) {
            $table->dropColumn('comentario_reenvio_revision');
        });
    }
};
