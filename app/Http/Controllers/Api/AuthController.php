<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\PasswordResetRequestedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    private const SUPERADMIN_SECURITY_QUESTIONS = [
        '¿Cual es el nombre de tu primera mascota?',
        '¿Cual fue tu apodo en la universidad?',
    ];

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt([
            'email' => $request->email,
            'password' => $request->password,
            'activo' => true,
        ])) {
            return response()->json([
                'message' => 'Credenciales incorrectas o usuario inactivo',
            ], 401);
        }

        /** @var User $user */
        $user = Auth::user();
        abort_if(! $user, 401);

        $user->forceFill([
            'last_login_at' => now('America/Lima'),
        ])->save();

        if ($user->two_factor_secret && $user->two_factor_confirmed_at) {
            $loginToken = Str::random(60);

            cache()->put(
                '2fa_login_'.$loginToken,
                $user->id,
                now()->addMinutes(5)
            );

            return response()->json([
                'requires_2fa' => true,
                'login_token' => $loginToken,
                'message' => 'Se requiere código 2FA',
            ]);
        }

        $user->tokens()->delete();

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user->load('profile', 'roles'),
            'token' => $token,
            'requires_password_change' => $user->requires_password_change,
            'two_factor_enabled' => ! is_null($user->two_factor_confirmed_at),
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)
            ->where('activo', true)
            ->first();

        if ($user) {
            if ($user->hasRole('superadmin')) {
                $configuredQuestions = $this->configuredSecurityQuestions($user);

                if (count($configuredQuestions) >= 2) {
                    return response()->json([
                        'message' => 'Responde tus preguntas de seguridad para restablecer tu contrasena.',
                        'recovery_type' => 'security_questions',
                        'email' => $user->email,
                        'questions' => $this->publicSecurityQuestions($user),
                        'requires_2fa' => $this->userHasConfirmedTwoFactor($user),
                    ]);
                }

                return response()->json([
                    'message' => 'El superadmin no tiene preguntas de seguridad configuradas. Contacta al responsable tecnico para recuperar el acceso.',
                    'recovery_type' => 'security_questions_unavailable',
                ]);
            }

            $token = Str::random(60);

            DB::table('password_reset_tokens')->upsert([
                [
                    'email' => $user->email,
                    'token' => $token,
                    'created_at' => now(),
                ],
            ], ['email'], ['token', 'created_at']);

            $superadminRole = Role::where('name', 'superadmin')->first();

            if ($superadminRole) {
                Notification::send(
                    $superadminRole->users,
                    new PasswordResetRequestedNotification($user)
                );
            }
        }

        return response()->json([
            'message' => 'Si el correo existe, el superadministrador recibirá la solicitud para generar una contraseña temporal.',
        ]);
    }

    public function securityQuestions(Request $request)
    {
        $questions = $this->publicSecurityQuestions($request->user());

        return response()->json([
            'configured' => count($this->configuredSecurityQuestions($request->user())) >= 2,
            'questions' => $questions,
        ]);
    }

    public function updateSecurityQuestions(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'questions' => 'required|array|size:2',
            'questions.*.answer' => 'required|string|min:2|max:255',
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'La contrasena actual es incorrecta',
            ], 422);
        }

        $questions = collect($validated['questions'])
            ->values()
            ->map(fn (array $item, int $index): array => [
                'question' => self::SUPERADMIN_SECURITY_QUESTIONS[$index],
                'answer_hash' => Hash::make($this->normalizeSecurityAnswer($item['answer'])),
            ])
            ->values()
            ->all();

        $user->forceFill([
            'security_questions' => $questions,
        ])->save();

        return response()->json([
            'message' => 'Preguntas de seguridad actualizadas correctamente.',
            'configured' => true,
            'questions' => $this->publicSecurityQuestions($user->refresh()),
        ]);
    }

    public function resetPasswordWithSecurityQuestions(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'answers' => 'required|array|size:2',
            'answers.*' => 'required|string|min:1|max:255',
            'two_factor_code' => 'nullable|string',
            'password' => 'required|min:6|confirmed',
        ]);

        $user = User::where('email', $validated['email'])
            ->where('activo', true)
            ->first();

        if (! $user || ! $user->hasRole('superadmin')) {
            return response()->json([
                'message' => 'No se pudo validar la recuperacion de superadmin.',
            ], 422);
        }

        $questions = $this->configuredSecurityQuestions($user);

        if (count($questions) < 2) {
            return response()->json([
                'message' => 'El superadmin no tiene preguntas de seguridad configuradas.',
            ], 422);
        }

        if ($this->userHasConfirmedTwoFactor($user)) {
            if (! $request->filled('two_factor_code')) {
                return response()->json([
                    'message' => 'Ingresa el codigo de verificacion de 2 pasos.',
                ], 422);
            }

            $google2fa = new Google2FA;
            $secret = Crypt::decryptString($user->two_factor_secret);

            if (! $google2fa->verifyKey($secret, $validated['two_factor_code'])) {
                return response()->json([
                    'message' => 'El codigo de verificacion de 2 pasos es invalido.',
                ], 422);
            }
        }

        foreach (array_values($questions) as $index => $question) {
            $answer = $validated['answers'][$index] ?? null;
            $answerHash = $question['answer_hash'] ?? null;

            if (! $answer || ! $answerHash || ! Hash::check($this->normalizeSecurityAnswer($answer), $answerHash)) {
                return response()->json([
                    'message' => 'Una o mas respuestas de seguridad son incorrectas.',
                ], 422);
            }
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'requires_password_change' => false,
        ])->save();

        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        $user->tokens()->delete();
        $user->notify(new PasswordChangedNotification($user));

        return response()->json([
            'message' => 'Contrasena restablecida correctamente. Ya puedes iniciar sesion.',
        ]);
    }

    public function resetPassword(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $temporaryPassword = Str::random(10);

        $user->forceFill([
            'password' => Hash::make($temporaryPassword),
            'requires_password_change' => true,
        ])->save();

        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Contraseña temporal generada correctamente. El usuario debe cambiarla en su próximo ingreso.',
            'temporary_password' => $temporaryPassword,
            'user' => $user,
        ]);
    }

    public function refresh(Request $request)
    {
        return response()
            ->json([
                'message' => 'ERP refresh disponible',
                'refreshed_at' => now('America/Lima')->toISOString(),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:6|confirmed',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'La contraseña actual es incorrecta',
            ], 422);
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
            'requires_password_change' => false,
        ])->save();

        $user->tokens()->delete();

        $token = $user->createToken('api-token')->plainTextToken;

        $user->notify(new PasswordChangedNotification($user));

        return response()->json([
            'message' => 'Contraseña cambiada correctamente',
            'user' => $user->load('profile', 'roles'),
            'token' => $token,
        ]);
    }

    public function notifications(Request $request)
    {
        $notifications = $request->user()
            ->notifications()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($notification) => [
                'id' => $notification->id,
                'type' => class_basename($notification->type),
                'data' => $notification->data,
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at,
            ]);

        return response()->json([
            'notifications' => $notifications,
        ]);
    }

    public function user(Request $request)
    {
        return response()->json([
            'user' => $request->user()->load('profile', 'roles'),
        ]);
    }

    public function markNotificationAsRead(Request $request, $id)
    {
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return response()->json([
            'message' => 'Notificación marcada como leída',
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'nombres' => 'required',
            'apellidos' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6|confirmed',
            'role' => 'required|exists:roles,id',
        ]);

        $role = Role::findOrFail($request->role);

        if (! $request->user()->hasRole('superadmin') && in_array($role->name, ['superadmin', 'admin', 'contabilidad'], true)) {
            abort(403, 'Solo superadmin puede crear usuarios administradores.');
        }

        $user = User::create([
            'nombres' => $request->nombres,
            'apellidos' => $request->apellidos,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole($role);

        Profile::create([
            'user_id' => $user->id,
            'telefono' => $request->telefono ?? null,
            'dni' => $request->dni ?? null,
            'cargo' => $request->cargo ?? null,
        ]);

        return response()->json([
            'message' => 'Usuario creado correctamente',
            'user' => $user->loadMissing('profile', 'roles'),
        ]);
    }

    public function logout(Request $request)
    {
        $lastLoginAt = $request->user()->last_login_at;

        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión Cerrada Correctamente',
            'last_login_at' => $lastLoginAt,
        ]);
    }

    public function twoFactorChallenge(Request $request)
    {
        $request->validate([
            'login_token' => 'required|string',
            'code' => 'nullable|string',
            'recovery_code' => 'nullable|string',
        ]);

        $userId = cache()->get('2fa_login_'.$request->login_token);

        if (! $userId) {
            return response()->json(['message' => 'Token temporal expirado'], 422);
        }

        $user = User::with('profile', 'roles')->findOrFail($userId);

        $valid = false;

        if ($request->filled('code')) {
            $google2fa = new Google2FA;
            $secret = Crypt::decryptString($user->two_factor_secret);
            $valid = $google2fa->verifyKey($secret, $request->code);
        }

        if (! $valid && $request->filled('recovery_code')) {
            $codes = json_decode($user->two_factor_recovery_codes, true) ?? [];

            if (in_array($request->recovery_code, $codes, true)) {
                $valid = true;

                $codes = array_values(array_filter(
                    $codes,
                    fn ($code) => $code !== $request->recovery_code
                ));

                $user->forceFill([
                    'two_factor_recovery_codes' => json_encode($codes),
                ])->save();
            }
        }

        if (! $valid) {
            return response()->json(['message' => 'Código inválido'], 422);
        }

        cache()->forget('2fa_login_'.$request->login_token);

        $user->forceFill([
            'last_login_at' => now('America/Lima'),
        ])->save();

        $user->tokens()->delete();

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'requires_password_change' => $user->requires_password_change,
        ]);
    }

    private function publicSecurityQuestions(User $user): array
    {
        $storedQuestions = collect($user->security_questions ?? []);

        return collect(self::SUPERADMIN_SECURITY_QUESTIONS)
            ->map(function (string $question, int $index) use ($storedQuestions): array {
                $storedQuestion = $storedQuestions->first(
                    fn (array $item): bool => ($item['question'] ?? '') === $question
                );

                return [
                    'id' => $index,
                    'question' => $question,
                    'configured' => isset($storedQuestion['answer_hash']),
                ];
            })
            ->values()
            ->all();
    }

    private function configuredSecurityQuestions(User $user): array
    {
        return collect($user->security_questions ?? [])
            ->filter(fn (array $item): bool => in_array($item['question'] ?? '', self::SUPERADMIN_SECURITY_QUESTIONS, true))
            ->sortBy(fn (array $item): int => (int) array_search($item['question'], self::SUPERADMIN_SECURITY_QUESTIONS, true))
            ->values()
            ->all();
    }

    private function userHasConfirmedTwoFactor(User $user): bool
    {
        return filled($user->two_factor_secret) && filled($user->two_factor_confirmed_at);
    }

    private function normalizeSecurityAnswer(string $answer): string
    {
        return Str::lower(preg_replace('/\s+/', ' ', trim($answer)) ?? '');
    }
}
