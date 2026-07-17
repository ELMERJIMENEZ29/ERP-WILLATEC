<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oc_documentos_adicionales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('oc_recibida_id')->nullable()->constrained('oc_recibidas')->cascadeOnDelete();
            $table->foreignId('oc_emitida_id')->nullable()->constrained('oc_emitidas')->cascadeOnDelete();
            $table->string('nombre_original');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['oc_recibida_id', 'created_at']);
            $table->index(['oc_emitida_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oc_documentos_adicionales');
    }
};
