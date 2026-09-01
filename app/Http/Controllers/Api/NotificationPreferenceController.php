<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserNotificationPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NotificationPreferenceController extends Controller
{
    private const MODULES = [
        'cotizaciones',
        'oportunidades',
        'ordenes',
        'inventario',
        'servicios',
        'usuarios',
        'general',
    ];

    public function show(Request $request)
    {
        return response()->json($this->buildResponse($request->user()));
    }

    public function update(Request $request)
    {
        $allowedModules = $this->allowedModulesForUser($request->user());
        $preference = $request->user()->notificationPreference;
        $previousUrl = $preference?->custom_sound_url;

        $data = $request->validate([
            'system_enabled' => ['required', 'boolean'],
            'email_enabled' => ['required', 'boolean'],
            'sound_enabled' => ['required', 'boolean'],
            'browser_enabled' => ['required', 'boolean'],
            'custom_sound_url' => ['nullable', 'string', 'max:500'],
            'modules' => ['required', 'array'],
            'modules.*' => ['boolean'],
        ]);

        $requestedModules = $request->input('modules', []);
        $modules = [];

        foreach ($allowedModules as $module) {
            $modules[$module] = array_key_exists($module, $requestedModules)
                ? (bool) $requestedModules[$module]
                : true;
        }

        UserNotificationPreference::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'system_enabled' => (bool) $data['system_enabled'],
                'email_enabled' => (bool) $data['email_enabled'],
                'sound_enabled' => (bool) $data['sound_enabled'],
                'browser_enabled' => (bool) $data['browser_enabled'],
                'custom_sound_url' => $data['custom_sound_url'] ?? null,
                'modules' => $modules,
            ]
        );

        if (empty($data['custom_sound_url'])) {
            $this->deletePreviousUploadedSound($previousUrl);
        }

        return response()->json($this->buildResponse($request->user()->fresh()));
    }

    public function uploadSound(Request $request)
    {
        $data = $request->validate([
            'sound' => ['required', 'file', 'mimes:mp3', 'max:5120'],
        ]);

        $preference = $request->user()->notificationPreference;
        $previousUrl = $preference?->custom_sound_url;

        $file = $data['sound'];
        $filename = 'user-'.$request->user()->id.'-'.Str::uuid().'.mp3';
        $path = $file->storeAs('notification-sounds', $filename, 'public');
        $url = Storage::disk('public')->url($path);

        UserNotificationPreference::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'system_enabled' => $preference?->system_enabled ?? true,
                'email_enabled' => $preference?->email_enabled ?? true,
                'sound_enabled' => true,
                'browser_enabled' => $preference?->browser_enabled ?? false,
                'modules' => is_array($preference?->modules) ? $preference->modules : null,
                'custom_sound_url' => $url,
            ]
        );

        $this->deletePreviousUploadedSound($previousUrl);

        return response()->json($this->buildResponse($request->user()->fresh()));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResponse(User $user): array
    {
        $allowedModules = $this->allowedModulesForUser($user);
        $preference = $user->notificationPreference;
        $savedModules = is_array($preference?->modules) ? $preference->modules : [];
        $modules = [];

        foreach ($allowedModules as $module) {
            $modules[$module] = array_key_exists($module, $savedModules)
                ? (bool) $savedModules[$module]
                : true;
        }

        return [
            'system_enabled' => $preference?->system_enabled ?? true,
            'email_enabled' => $preference?->email_enabled ?? true,
            'sound_enabled' => $preference?->sound_enabled ?? true,
            'browser_enabled' => $preference?->browser_enabled ?? false,
            'custom_sound_url' => $preference?->custom_sound_url,
            'modules' => $modules,
            'allowed_modules' => $allowedModules,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function allowedModulesForUser(User $user): array
    {
        if ($user->hasRole(['superadmin', 'admin'])) {
            return self::MODULES;
        }

        if ($user->hasRole(['ventas'])) {
            return ['cotizaciones', 'oportunidades', 'ordenes', 'general'];
        }

        if ($user->hasRole(['logistica'])) {
            return ['inventario', 'ordenes', 'general'];
        }

        if ($user->hasRole(['contabilidad'])) {
            return ['ordenes', 'general'];
        }

        if ($user->hasRole(['licitacion', 'licitaciones'])) {
            return ['oportunidades', 'cotizaciones', 'general'];
        }

        if ($user->hasRole(['soporte'])) {
            return ['inventario', 'general'];
        }

        return ['general'];
    }

    private function deletePreviousUploadedSound(?string $url): void
    {
        if (! $url) {
            return;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $marker = '/storage/notification-sounds/';
        $position = strpos($path, $marker);

        if ($position === false) {
            return;
        }

        $relativePath = substr($path, $position + strlen('/storage/'));

        if ($relativePath) {
            Storage::disk('public')->delete($relativePath);
        }
    }
}
