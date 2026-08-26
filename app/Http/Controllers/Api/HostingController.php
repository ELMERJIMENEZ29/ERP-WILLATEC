<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Hosting;
use App\Models\HostingDocumento;
use App\Models\User;
use App\Notifications\ServicioRenovacionNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class HostingController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:150',
            'estado' => ['nullable', Rule::in(['VIGENTE', 'POR VENCER', 'VENCIDO'])],
            'suscripcion' => ['nullable', Rule::in(['ANUAL', 'MENSUAL'])],
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Hosting::with([
            'clienteRelacionado:id,nombre,ruc,correo',
            'moneda:id,codigo,simbolo',
            'documentos',
            'alertasEnviadas' => fn ($query) => $query->latest('sent_at'),
        ])
            ->withCount('alertasEnviadas')
            ->withMax('alertasEnviadas', 'sent_at')
            ->orderBy('fecha_renovacion')
            ->orderBy('empresa');

        if (! empty($validated['search'])) {
            $search = $validated['search'];

            $query->where(function ($query) use ($search): void {
                $query->where('empresa', 'like', "%{$search}%")
                    ->orWhere('ruc', 'like', "%{$search}%")
                    ->orWhere('dominio', 'like', "%{$search}%")
                    ->orWhere('plan', 'like', "%{$search}%")
                    ->orWhere('contacto', 'like', "%{$search}%")
                    ->orWhere('cliente', 'like', "%{$search}%")
                    ->orWhere('correo_hosting', 'like', "%{$search}%")
                    ->orWhereHas('clienteRelacionado', function ($clienteQuery) use ($search): void {
                        $clienteQuery->where('nombre', 'like', "%{$search}%")
                            ->orWhere('ruc', 'like', "%{$search}%")
                            ->orWhere('correo', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($validated['suscripcion'])) {
            $query->where('suscripcion', $validated['suscripcion']);
        }

        if (! empty($validated['estado'])) {
            $today = Carbon::today();

            match ($validated['estado']) {
                'VENCIDO' => $query->whereDate('fecha_renovacion', '<', $today),
                'POR VENCER' => $query
                    ->whereDate('fecha_renovacion', '>=', $today)
                    ->whereDate('fecha_renovacion', '<=', $today->copy()->addDays(30)),
                'VIGENTE' => $query->whereDate('fecha_renovacion', '>', $today->copy()->addDays(30)),
            };
        }

        return response()->json(
            $query->paginate($request->integer('per_page', 10))
        );
    }

    public function store(Request $request)
    {
        $payload = $this->validatePayload($request);
        $payload['fecha_renovacion'] = $this->calculateFechaRenovacion(
            $payload['fecha_inicio'],
            $payload['suscripcion']
        );

        $hosting = Hosting::create($payload);

        return response()->json([
            'message' => 'Hosting registrado correctamente',
            'hosting' => $this->loadHostingRelations($hosting),
        ], 201);
    }

    public function show(Hosting $hosting)
    {
        return response()->json(
            $this->loadHostingRelations($hosting)
        );
    }

    public function update(Request $request, Hosting $hosting)
    {
        $payload = $this->validatePayload($request);
        $payload['fecha_renovacion'] = $this->calculateFechaRenovacion(
            $payload['fecha_inicio'],
            $payload['suscripcion']
        );

        $hosting->update($payload);

        return response()->json([
            'message' => 'Hosting actualizado correctamente',
            'hosting' => $this->loadHostingRelations($hosting->refresh()),
        ]);
    }

    public function renovar(Request $request, Hosting $hosting)
    {
        $payload = $this->validateRenovacionPayload($request);
        $modo = $payload['modo'];
        $meses = $modo === 'ANUAL' ? 12 : (int) $payload['meses'];
        $inicioRenovacion = Carbon::parse($hosting->fecha_renovacion)->addDay();

        if ($inicioRenovacion->lte(Carbon::today('America/Lima'))) {
            $this->aplicarRenovacion($hosting, $inicioRenovacion, $modo, $meses);

            $message = 'Hosting renovado correctamente.';
            $this->notifyAdmins(new ServicioRenovacionNotification(
                'hosting',
                $hosting->id,
                'Hosting renovado',
                "Se renovo el hosting {$hosting->dominio} de {$hosting->empresa}.",
                '/servicios/hosting',
                [
                    'fecha_inicio' => $inicioRenovacion->toDateString(),
                    'fecha_renovacion' => $hosting->fresh()->fecha_renovacion?->toDateString(),
                    'renovacion_meses' => $meses,
                ]
            ));
        } else {
            $hosting->update([
                'renovacion_programada' => true,
                'renovacion_modo' => $modo,
                'renovacion_meses' => $meses,
                'renovacion_programada_para' => $inicioRenovacion->toDateString(),
                'renovacion_programada_at' => now('America/Lima'),
                'renovacion_programada_por' => $request->user()?->id,
            ]);

            $message = 'Se programo la renovacion automatica para cuando venza el hosting.';
            $this->notifyAdmins(new ServicioRenovacionNotification(
                'hosting',
                $hosting->id,
                'Renovacion de hosting programada',
                "Se programo la renovacion automatica de {$hosting->dominio} de {$hosting->empresa} para el {$inicioRenovacion->format('d/m/Y')}.",
                '/servicios/hosting',
                [
                    'renovacion_programada_para' => $inicioRenovacion->toDateString(),
                    'renovacion_meses' => $meses,
                ]
            ));
        }

        return response()->json([
            'message' => $message,
            'hosting' => $this->loadHostingRelations($hosting->refresh()),
        ]);
    }

    public function destroy(Hosting $hosting)
    {
        $hosting->documentos()->each(function (HostingDocumento $documento): void {
            Storage::disk('public')->delete($documento->path);
            $documento->delete();
        });

        $hosting->delete();

        return response()->json([
            'message' => 'Hosting eliminado correctamente',
        ]);
    }

    public function documentos(Request $request, Hosting $hosting)
    {
        $validated = $request->validate([
            'documentos' => 'required|array|min:1|max:10',
            'documentos.*' => 'file|mimes:pdf|max:10240',
        ]);

        foreach ($validated['documentos'] as $documento) {
            if (! $documento instanceof UploadedFile) {
                continue;
            }

            $path = $documento->store('hostings/cotizaciones-referenciales', 'public');

            $hosting->documentos()->create([
                'nombre_original' => $documento->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $documento->getClientMimeType(),
                'size' => $documento->getSize(),
                'created_by' => $request->user()?->id,
            ]);
        }

        return response()->json([
            'message' => 'Documentos subidos correctamente',
            'hosting' => $this->loadHostingRelations($hosting->refresh()),
        ]);
    }

    public function eliminarDocumento(Hosting $hosting, HostingDocumento $documento)
    {
        abort_if($documento->hosting_id !== $hosting->id, 404);

        Storage::disk('public')->delete($documento->path);
        $documento->delete();

        return response()->json([
            'message' => 'Documento eliminado correctamente',
            'hosting' => $this->loadHostingRelations($hosting->refresh()),
        ]);
    }

    public function previewImport(Request $request)
    {
        $validated = $request->validate([
            'rows' => 'required|array|min:1|max:1000',
            'rows.*' => 'array',
        ]);

        return response()->json($this->buildImportPreview($validated['rows']));
    }

    public function confirmImport(Request $request)
    {
        $validated = $request->validate([
            'rows' => 'required|array|min:1|max:1000',
            'rows.*' => 'array',
        ]);

        $preview = $this->buildImportPreview($validated['rows']);
        $invalidRows = collect($preview['rows'])->where('valid', false)->values();

        if ($invalidRows->isNotEmpty()) {
            return response()->json([
                'message' => 'El archivo tiene filas con errores. Corrige o elimina esas filas antes de importar.',
                'summary' => $preview['summary'],
                'rows' => $preview['rows'],
            ], 422);
        }

        $created = [];

        foreach ($preview['rows'] as $row) {
            $created[] = $this->loadHostingRelations(Hosting::create($row['data']));
        }

        return response()->json([
            'message' => count($created).' hosting(s) importado(s) correctamente',
            'created' => count($created),
            'hostings' => $created,
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        foreach (['empresa', 'ruc', 'dominio', 'plan', 'contacto', 'cliente', 'correo_hosting'] as $field) {
            $request->merge([
                $field => $this->nullableTrim($request->input($field)),
            ]);
        }

        $request->merge([
            'precio_sin_igv' => $request->input('precio_sin_igv') === '' ? null : $request->input('precio_sin_igv'),
        ]);

        return $request->validate([
            'cliente_id' => 'nullable|integer|exists:clientes,id',
            'empresa' => 'required|string|max:255',
            'ruc' => 'nullable|string|max:20',
            'dominio' => 'required|string|max:255',
            'plan' => 'required|string|max:255',
            'precio_sin_igv' => 'nullable|numeric|min:0',
            'moneda_id' => 'nullable|integer|exists:monedas,id',
            'suscripcion' => ['required', Rule::in(['ANUAL', 'MENSUAL'])],
            'fecha_inicio' => 'required|date',
            'contacto' => 'nullable|string|max:255',
            'cliente' => 'nullable|string|max:255',
            'correo_hosting' => 'nullable|email|max:255',
        ]);
    }

    private function loadHostingRelations(Hosting $hosting): Hosting
    {
        return $hosting->load([
            'clienteRelacionado:id,nombre,ruc,correo',
            'moneda:id,codigo,simbolo',
            'documentos',
            'alertasEnviadas' => fn ($query) => $query->latest('sent_at'),
        ])
            ->loadCount('alertasEnviadas')
            ->loadMax('alertasEnviadas', 'sent_at');
    }

    private function nullableTrim(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function calculateFechaRenovacion(string $fechaInicio, string $suscripcion): string
    {
        $fecha = Carbon::parse($fechaInicio);

        return ($suscripcion === 'MENSUAL'
            ? $fecha->addMonthNoOverflow()
            : $fecha->addYearNoOverflow()
        )
            ->subDay()
            ->toDateString();
    }

    private function calculateFechaRenovacionMeses(string $fechaInicio, int $meses): string
    {
        return Carbon::parse($fechaInicio)
            ->addMonthsNoOverflow($meses)
            ->subDay()
            ->toDateString();
    }

    /**
     * @return array{modo:string, meses?:int}
     */
    private function validateRenovacionPayload(Request $request): array
    {
        return $request->validate([
            'modo' => ['required', Rule::in(['ANUAL', 'MENSUAL'])],
            'meses' => 'required_if:modo,MENSUAL|nullable|integer|min:1|max:240',
        ]);
    }

    private function aplicarRenovacion(Hosting $hosting, Carbon $fechaInicio, string $modo, int $meses): void
    {
        $hosting->update([
            'fecha_inicio' => $fechaInicio->toDateString(),
            'fecha_renovacion' => $this->calculateFechaRenovacionMeses($fechaInicio->toDateString(), $meses),
            'suscripcion' => $modo,
            'renovacion_programada' => false,
            'renovacion_modo' => null,
            'renovacion_meses' => null,
            'renovacion_programada_para' => null,
            'renovacion_programada_at' => null,
            'renovacion_programada_por' => null,
        ]);

        $hosting->alertasEnviadas()->delete();
    }

    private function notifyAdmins(ServicioRenovacionNotification $notification): void
    {
        User::role(['superadmin', 'admin'])->get()->each->notify($notification);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function buildImportPreview(array $rows): array
    {
        $seen = [];
        $previewRows = [];

        foreach ($rows as $index => $rawRow) {
            $rowNumber = $index + 2;
            $normalized = $this->normalizeImportRow($rawRow);
            $errors = [];
            $warnings = [];

            $validator = Validator::make($normalized, [
                'cliente_id' => 'nullable|integer|exists:clientes,id',
                'empresa' => 'required|string|max:255',
                'ruc' => 'nullable|string|max:20',
                'dominio' => 'required|string|max:255',
                'plan' => 'required|string|max:255',
                'suscripcion' => ['required', Rule::in(['ANUAL', 'MENSUAL'])],
                'fecha_inicio' => 'required|date_format:Y-m-d',
                'contacto' => 'nullable|string|max:255',
                'cliente' => 'nullable|string|max:255',
                'correo_hosting' => 'nullable|email|max:255',
            ], [], [
                'cliente_id' => 'cliente',
                'empresa' => 'empresa',
                'ruc' => 'ruc',
                'dominio' => 'dominio',
                'plan' => 'plan',
                'suscripcion' => 'suscripcion',
                'fecha_inicio' => 'fecha inicio',
                'contacto' => 'contacto',
                'cliente' => 'cliente',
                'correo_hosting' => 'correo hosting',
            ]);

            if ($validator->fails()) {
                $errors = $validator->errors()->all();
            }

            if (empty($normalized['cliente_id']) && ! empty($normalized['empresa'])) {
                $cliente = Cliente::query()
                    ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($normalized['empresa'])])
                    ->first(['id', 'nombre', 'ruc', 'correo']);

                if ($cliente) {
                    $normalized['cliente_id'] = $cliente->id;
                    $normalized['empresa'] = $cliente->nombre;
                    $normalized['ruc'] = $normalized['ruc'] ?: $cliente->ruc;
                    $normalized['cliente'] = $normalized['cliente'] ?: $cliente->nombre;
                    $normalized['correo_hosting'] = $normalized['correo_hosting'] ?: $cliente->correo;
                } else {
                    $warnings[] = 'No se encontro un cliente registrado con ese nombre. Se importara como empresa escrita.';
                }
            }

            if (empty($errors)) {
                $normalized['fecha_renovacion'] = $this->calculateFechaRenovacion(
                    $normalized['fecha_inicio'],
                    $normalized['suscripcion']
                );

                $key = mb_strtolower($normalized['empresa'].'|'.$normalized['dominio'].'|'.$normalized['fecha_inicio']);

                if (isset($seen[$key])) {
                    $warnings[] = 'Posible duplicado dentro del archivo con la fila '.$seen[$key].'.';
                } else {
                    $seen[$key] = $rowNumber;
                }

                $exists = Hosting::query()
                    ->where('empresa', $normalized['empresa'])
                    ->where('dominio', $normalized['dominio'])
                    ->whereDate('fecha_inicio', $normalized['fecha_inicio'])
                    ->exists();

                if ($exists) {
                    $warnings[] = 'Ya existe un hosting similar en el sistema.';
                }
            }

            $previewRows[] = [
                'row' => $rowNumber,
                'valid' => empty($errors),
                'errors' => $errors,
                'warnings' => $warnings,
                'data' => $normalized,
            ];
        }

        return [
            'summary' => [
                'total' => count($previewRows),
                'valid' => collect($previewRows)->where('valid', true)->count(),
                'invalid' => collect($previewRows)->where('valid', false)->count(),
                'warnings' => collect($previewRows)->filter(fn ($row) => count($row['warnings']) > 0)->count(),
            ],
            'rows' => $previewRows,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeImportRow(array $row): array
    {
        $fechaInicio = $this->normalizeImportDate($row['fecha_inicio'] ?? null);
        $suscripcion = strtoupper(trim((string) ($row['suscripcion'] ?? '')));

        return [
            'cliente_id' => $this->nullableInteger($row['cliente_id'] ?? null),
            'empresa' => trim((string) ($row['empresa'] ?? '')),
            'ruc' => $this->nullableTrim($row['ruc'] ?? null),
            'dominio' => trim((string) ($row['dominio'] ?? '')),
            'plan' => trim((string) ($row['plan'] ?? '')),
            'suscripcion' => $suscripcion,
            'fecha_inicio' => $fechaInicio,
            'contacto' => $this->nullableTrim($row['contacto'] ?? null),
            'cliente' => $this->nullableTrim($row['cliente'] ?? null),
            'correo_hosting' => $this->nullableTrim($row['correo_hosting'] ?? null),
        ];
    }

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function normalizeImportDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse(trim($value))->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
