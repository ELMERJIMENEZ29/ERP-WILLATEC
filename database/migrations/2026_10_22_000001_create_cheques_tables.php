<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bancos', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 120)->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('cheques', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('banco_id')->constrained('bancos');
            $table->string('numero', 60);
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->foreignId('moneda_id')->nullable()->constrained('monedas')->nullOnDelete();
            $table->decimal('monto', 14, 2);
            $table->date('fecha_emision');
            $table->date('fecha_cheque');
            $table->date('fecha_entrega')->nullable();
            $table->date('fecha_cobro')->nullable();
            $table->enum('estado', ['registrado', 'entregado', 'cobrado', 'anulado', 'devuelto'])->default('registrado');
            $table->string('evidencia_path')->nullable();
            $table->string('evidencia_nombre')->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['banco_id', 'numero']);
            $table->index(['estado', 'fecha_cheque']);
        });

        Schema::create('cheque_aplicaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cheque_id')->constrained('cheques')->cascadeOnDelete();
            $table->foreignId('compra_id')->nullable()->constrained('compras')->nullOnDelete();
            $table->foreignId('cuenta_por_pagar_id')->nullable()->constrained('cuentas_por_pagar')->nullOnDelete();
            $table->decimal('monto_aplicado', 14, 2)->nullable();
            $table->timestamps();
            $table->index(['compra_id', 'cuenta_por_pagar_id']);
        });

        foreach (['BCP', 'BBVA', 'Banco de la Nación'] as $nombre) {
            DB::table('bancos')->insert(['nombre' => $nombre, 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
        }

        foreach ([
            'compras.recepciones' => ['admin', 'logistica', 'ventas', 'contabilidad'],
            'contabilidad.cheques' => ['admin', 'contabilidad'],
            'contabilidad.bancos' => ['admin', 'contabilidad'],
        ] as $permissionName => $roles) {
            $permissionId = DB::table('permissions')->where('name', $permissionName)->value('id');
            if (! $permissionId) {
                $permissionId = DB::table('permissions')->insertGetId([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            foreach ($roles as $roleName) {
                $roleId = DB::table('roles')->where('name', $roleName)->value('id');
                if ($roleId) {
                    DB::table('role_has_permissions')->insertOrIgnore([
                        'permission_id' => $permissionId,
                        'role_id' => $roleId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cheque_aplicaciones');
        Schema::dropIfExists('cheques');
        Schema::dropIfExists('bancos');
    }
};
