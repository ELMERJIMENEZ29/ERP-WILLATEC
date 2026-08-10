<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Licencia;
use App\Models\LicenciaDocumento;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LicenciaController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:150',
            'estado' => ['nullable', Rule::in(['VIGENTE', 'POR VENCER', 'VENCIDO'])],
            'suscripcion_meses' => 'nullable|integer|min:1|max:240',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Licencia::with([
            'cliente:id,nombre,ruc,correo',
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
                    ->orWhere('producto', 'like', "%{$search}%")
                    ->orWhere('correo_licencia', 'like', "%{$search}%")
                    ->orWhereHas('cliente', function ($clienteQuery) use ($search): void {
                        $clienteQuery->where('nombre', 'like', "%{$search}%")
                            ->orWhere('ruc', 'like', "%{$search}%")
                            ->orWhere('correo', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($validated['suscripcion_meses'])) {
            $query->where('suscripcion_meses', $validated['suscripcion_meses']);
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
            (int) $payload['suscripcion_meses']
        );

        $licencia = Licencia::create($payload);

        return response()->json([
            'message' => 'Licencia registrada correctamente',
            'licencia' => $this->loadLicenciaRelations($licencia),
        ], 201);
    }

    public function show(Licencia $licencia)
    {
        return response()->json(
            $this->loadLicenciaRelations($licencia)
        );
    }

    public function update(Request $request, Licencia $licencia)
    {
        $payload = $this->validatePayload($request);
        $payload['fecha_renovacion'] = $this->calculateFechaRenovacion(
            $payload['fecha_inicio'],
            (int) $payload['suscripcion_meses']
        );

        $licencia->update($payload);

        return response()->json([
            'message' => 'Licencia actualizada correctamente',
            'licencia' => $this->loadLicenciaRelations($licencia->refresh()),
        ]);
    }

    public function destroy(Licencia $licencia)
    {
        $licencia->documentos()->each(function (LicenciaDocumento $documento): void {
            Storage::disk('public')->delete($documento->path);
            $documento->delete();
        });
        $licencia->delete();

        return response()->json([
            'message' => 'Licencia eliminada correctamente',
        ]);
    }

    public function documentos(Request $request, Licencia $licencia)
    {
        $request->validate([
            'documentos' => 'required|array|min:1|max:10',
            'documentos.*' => 'file|mimes:pdf|max:10240',
        ]);

        foreach ($request->file('documentos', []) as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $licencia->documentos()->create([
                'nombre_original' => $file->getClientOriginalName(),
                'path' => $file->store('licencias/cotizaciones-referenciales', 'public'),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'created_by' => $request->user()?->id,
            ]);
        }

        return response()->json([
            'message' => 'Documentos subidos correctamente',
            'licencia' => $this->loadLicenciaRelations($licencia->refresh()),
        ]);
    }

    public function eliminarDocumento(Licencia $licencia, LicenciaDocumento $documento)
    {
        if ((int) $documento->licencia_id !== (int) $licencia->id) {
            abort(404);
        }

        Storage::disk('public')->delete($documento->path);
        $documento->delete();

        return response()->json([
            'message' => 'Documento eliminado correctamente',
            'licencia' => $this->loadLicenciaRelations($licencia->refresh()),
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
            $created[] = $this->loadLicenciaRelations(Licencia::create($row['data']));
        }

        return response()->json([
            'message' => count($created).' licencia(s) importada(s) correctamente',
            'created' => count($created),
            'licencias' => $created,
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        $request->merge([
            'empresa' => trim((string) $request->input('empresa', '')),
            'producto' => trim((string) $request->input('producto', '')),
            'correo_licencia' => $this->nullableTrim($request->input('correo_licencia')),
            'precio_sin_igv' => $request->input('precio_sin_igv') === '' ? null : $request->input('precio_sin_igv'),
        ]);

        return $request->validate([
            'cliente_id' => 'nullable|integer|exists:clientes,id',
            'empresa' => 'required|string|max:255',
            'producto' => 'required|string|max:255',
            'cantidad' => 'required|integer|min:1|max:100000',
            'precio_sin_igv' => 'nullable|numeric|min:0',
            'moneda_id' => 'nullable|integer|exists:monedas,id',
            'suscripcion_meses' => 'required|integer|min:1|max:240',
            'correo_licencia' => 'nullable|email|max:255',
            'fecha_inicio' => 'required|date',
        ]);
    }

    private function loadLicenciaRelations(Licencia $licencia): Licencia
    {
        return $licencia
            ->load([
                'cliente:id,nombre,ruc,correo',
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

    private function calculateFechaRenovacion(string $fechaInicio, int $meses): string
    {
        return Carbon::parse($fechaInicio)
            ->addMonthsNoOverflow($meses)
            ->subDay()
            ->toDateString();
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
                'producto' => 'required|string|max:255',
                'cantidad' => 'required|integer|min:1|max:100000',
                'suscripcion_meses' => 'required|integer|min:1|max:240',
                'correo_licencia' => 'nullable|email|max:255',
                'fecha_inicio' => 'required|date_format:Y-m-d',
            ], [], [
                'cliente_id' => 'cliente',
                'empresa' => 'empresa',
                'producto' => 'producto',
                'cantidad' => 'cantidad',
                'suscripcion_meses' => 'suscripcion en meses',
                'correo_licencia' => 'correo licencia',
                'fecha_inicio' => 'fecha inicio',
            ]);

            if ($validator->fails()) {
                $errors = $validator->errors()->all();
            }

            if (empty($normalized['cliente_id']) && ! empty($normalized['empresa'])) {
                $cliente = Cliente::query()
                    ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($normalized['empresa'])])
                    ->first(['id', 'nombre', 'correo']);

                if ($cliente) {
                    $normalized['cliente_id'] = $cliente->id;
                    $normalized['empresa'] = $cliente->nombre;
                    $normalized['correo_licencia'] = $normalized['correo_licencia'] ?: $cliente->correo;
                } else {
                    $warnings[] = 'No se encontro un cliente registrado con ese nombre. Se importara como empresa escrita.';
                }
            }

            if (empty($errors)) {
                $normalized['fecha_renovacion'] = $this->calculateFechaRenovacion(
                    $normalized['fecha_inicio'],
                    (int) $normalized['suscripcion_meses']
                );

                $key = mb_strtolower($normalized['empresa'].'|'.$normalized['producto'].'|'.$normalized['fecha_inicio']);

                if (isset($seen[$key])) {
                    $warnings[] = 'Posible duplicado dentro del archivo con la fila '.$seen[$key].'.';
                } else {
                    $seen[$key] = $rowNumber;
                }

                $exists = Licencia::query()
                    ->where('empresa', $normalized['empresa'])
                    ->where('producto', $normalized['producto'])
                    ->whereDate('fecha_inicio', $normalized['fecha_inicio'])
                    ->exists();

                if ($exists) {
                    $warnings[] = 'Ya existe una licencia similar en el sistema.';
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

        return [
            'cliente_id' => $this->nullableInteger($row['cliente_id'] ?? null),
            'empresa' => trim((string) ($row['empresa'] ?? '')),
            'producto' => trim((string) ($row['producto'] ?? '')),
            'cantidad' => $this->nullableInteger($row['cantidad'] ?? null),
            'suscripcion_meses' => $this->nullableInteger($row['suscripcion_meses'] ?? null),
            'correo_licencia' => $this->nullableTrim($row['correo_licencia'] ?? null),
            'fecha_inicio' => $fechaInicio,
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
