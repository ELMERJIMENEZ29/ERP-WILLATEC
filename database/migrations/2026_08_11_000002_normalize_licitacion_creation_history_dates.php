<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $historiales = DB::table('licitacion_historial')
            ->join('licitaciones', 'licitacion_historial.licitacion_id', '=', 'licitaciones.id')
            ->where(function ($query): void {
                $query->where('licitacion_historial.tipo', 'creacion')
                    ->orWhere('licitacion_historial.descripcion', 'Oportunidad creada y publicada en disponibles.');
            })
            ->select([
                'licitacion_historial.id',
                'licitaciones.creado_en',
                'licitaciones.created_at',
            ])
            ->get();

        foreach ($historiales as $historial) {
            $fecha = $historial->creado_en ?? $historial->created_at;

            if (! $fecha) {
                continue;
            }

            DB::table('licitacion_historial')
                ->where('id', $historial->id)
                ->update(['fecha' => $fecha]);
        }
    }

    public function down(): void
    {
        //
    }
};
