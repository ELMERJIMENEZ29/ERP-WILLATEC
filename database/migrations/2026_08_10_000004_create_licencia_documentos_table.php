<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('licencia_documentos')) {
            return;
        }

        Schema::create('licencia_documentos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('licencia_id')->constrained('licencias')->cascadeOnDelete();
            $table->string('nombre_original');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('licencia_id');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licencia_documentos');
    }
};
