<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hosting_documentos')) {
            return;
        }

        Schema::create('hosting_documentos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hosting_id')->constrained('hostings')->cascadeOnDelete();
            $table->string('nombre_original');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('hosting_id');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hosting_documentos');
    }
};
