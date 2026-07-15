<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('producto_series')
            ->where('estado', 'otro')
            ->update(['estado' => 'no_disponible']);
    }

    public function down(): void
    {
        DB::table('producto_series')
            ->where('estado', 'no_disponible')
            ->update(['estado' => 'otro']);
    }
};
