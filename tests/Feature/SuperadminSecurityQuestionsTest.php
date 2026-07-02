<?php

use App\Models\User;
use App\Notifications\PasswordResetRequestedNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

test('usuarios normales mantienen el flujo de solicitud al superadmin', function () {
    Notification::fake();
    $this->seed(RoleSeeder::class);

    $superadmin = User::factory()->create();
    $superadmin->assignRole('superadmin');

    $ventas = User::factory()->create();
    $ventas->assignRole('ventas');

    $this->postJson('/api/forgot-password', [
        'email' => $ventas->email,
    ])
        ->assertOk()
        ->assertJsonMissingPath('recovery_type');

    Notification::assertSentTo($superadmin, PasswordResetRequestedNotification::class);
});

test('superadmin configura preguntas y restablece su propia contrasena', function () {
    Notification::fake();
    $this->seed(RoleSeeder::class);

    $superadmin = User::factory()->create([
        'password' => Hash::make('clave-actual'),
    ]);
    $superadmin->assignRole('superadmin');

    Sanctum::actingAs($superadmin);

    $this->putJson('/api/superadmin/security-questions', [
        'current_password' => 'clave-actual',
        'questions' => [
            ['answer' => 'Firulais'],
            ['answer' => 'Lucho'],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('configured', true)
        ->assertJsonPath('questions.0.question', '¿Cual es el nombre de tu primera mascota?')
        ->assertJsonMissingPath('questions.0.answer_hash');

    $this->postJson('/api/forgot-password', [
        'email' => $superadmin->email,
    ])
        ->assertOk()
        ->assertJsonPath('recovery_type', 'security_questions')
        ->assertJsonPath('requires_2fa', false)
        ->assertJsonPath('questions.1.question', '¿Cual fue tu apodo en la universidad?')
        ->assertJsonMissingPath('questions.1.answer_hash');

    $this->postJson('/api/superadmin/security-question-reset', [
        'email' => $superadmin->email,
        'answers' => ['firulais', 'lucho'],
        'password' => 'nueva-clave',
        'password_confirmation' => 'nueva-clave',
    ])->assertOk();

    expect(Hash::check('nueva-clave', $superadmin->refresh()->password))->toBeTrue();
});

test('superadmin con 2fa activo debe validar codigo para restablecer contrasena', function () {
    Notification::fake();
    $this->seed(RoleSeeder::class);

    $google2fa = new Google2FA;
    $secret = $google2fa->generateSecretKey();
    $code = $google2fa->getCurrentOtp($secret);

    $superadmin = User::factory()->create([
        'password' => Hash::make('clave-actual'),
        'security_questions' => [
            [
                'question' => '¿Cual es el nombre de tu primera mascota?',
                'answer_hash' => Hash::make('firulais'),
            ],
            [
                'question' => '¿Cual fue tu apodo en la universidad?',
                'answer_hash' => Hash::make('lucho'),
            ],
        ],
        'two_factor_secret' => Crypt::encryptString($secret),
        'two_factor_confirmed_at' => now(),
    ]);
    $superadmin->assignRole('superadmin');

    $this->postJson('/api/forgot-password', [
        'email' => $superadmin->email,
    ])
        ->assertOk()
        ->assertJsonPath('recovery_type', 'security_questions')
        ->assertJsonPath('requires_2fa', true);

    $this->postJson('/api/superadmin/security-question-reset', [
        'email' => $superadmin->email,
        'answers' => ['firulais', 'lucho'],
        'password' => 'nueva-clave',
        'password_confirmation' => 'nueva-clave',
    ])->assertStatus(422);

    $this->postJson('/api/superadmin/security-question-reset', [
        'email' => $superadmin->email,
        'answers' => ['firulais', 'lucho'],
        'two_factor_code' => $code,
        'password' => 'nueva-clave',
        'password_confirmation' => 'nueva-clave',
    ])->assertOk();
});
