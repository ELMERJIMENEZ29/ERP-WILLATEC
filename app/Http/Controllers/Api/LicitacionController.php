<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cotizacion;
use App\Models\CotizacionModificacion;
use App\Models\Licitacion;
use App\Models\LicitacionArchivo;
use App\Models\LicitacionComentario;
use App\Models\LicitacionCotizacion;
use App\Models\LicitacionHistorial;
use App\Models\LicitacionVista;
use App\Models\User;
use App\Notifications\LicitacionCotizacionListaNotification;
use App\Notifications\NuevaOportunidadDisponibleNotification;
use App\Notifications\OportunidadAtendidaNotification;
use App\Notifications\OportunidadComentarioNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LicitacionController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:150',
            'tipo' => ['nullable', Rule::in(['licitacion', 'privado', 'wherex'])],
            'estado' => ['nullable', Rule::in($this->estados())],
            'categoria' => 'nullable|string|max:120',
            'ejecutivo_id' => 'nullable|integer|exists:users,id',
            'asignado_a' => 'nullable|integer|exists:users,id',
            'vigencia_desde' => 'nullable|date',
            'vigencia_hasta' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
            'lite' => 'nullable|boolean',
        ]);

        $lite = $request->boolean('lite');

        $query = Licitacion::with($this->relations($lite))
            ->orderBy('vigencia')
            ->orderByDesc('id');

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($query) use ($search): void {
                $query->where('empresa', 'like', "%{$search}%")
                    ->orWhere('requerimiento', 'like', "%{$search}%")
                    ->orWhere('categoria', 'like', "%{$search}%")
                    ->orWhere('ejecutivo_nombre', 'like', "%{$search}%")
                    ->orWhere('wherex_id', 'like', "%{$search}%");
            });
        }

        foreach (['tipo', 'estado', 'categoria', 'ejecutivo_id', 'asignado_a'] as $field) {
            if (! empty($validated[$field])) {
                $query->where($field, $validated[$field]);
            }
        }

        if (! empty($validated['vigencia_desde'])) {
            $query->whereDate('vigencia', '>=', $validated['vigencia_desde']);
        }

        if (! empty($validated['vigencia_hasta'])) {
            $query->whereDate('vigencia', '<=', $validated['vigencia_hasta']);
        }

        if ($request->filled('per_page')) {
            $items = $query
                ->paginate($request->integer('per_page', 10))
                ->through(fn (Licitacion $licitacion) => $this->serialize($licitacion, ! $lite));

            return response()->json($items);
        }

        return response()->json(
            $query->get()->map(fn (Licitacion $licitacion) => $this->serialize($licitacion, ! $lite))->values()
        );
    }

    public function store(Request $request)
    {
        $payload = $this->validatePayload($request);
        $payload['created_by'] = $request->user()?->id;
        $payload['creado_por'] = $payload['creado_por'] ?? $this->userDisplayName($request->user());
        $payload['creado_en'] = $payload['creado_en'] ?? now('America/Lima');
        $payload['modificado_en'] = $payload['modificado_en'] ?? $payload['creado_en'];

        $licitacion = DB::transaction(function () use ($request, $payload): Licitacion {
            $licitacion = Licitacion::create($payload);
            $this->syncNestedData($licitacion, $request);

            return $licitacion;
        });

        $this->notifyNewOpportunity($licitacion, $request->user());

        return response()->json($this->serialize($this->loadRelations($licitacion)), 201);
    }

    public function show(Request $request, Licitacion $licitacion)
    {
        $this->markAsViewed($licitacion, $request->user());

        return response()->json($this->serialize($this->loadRelations($licitacion)));
    }

    public function update(Request $request, Licitacion $licitacion)
    {
        $payload = $this->validatePayload($request);
        $this->ensureCanUpdate($request, $licitacion, $payload);
        $payload['modificado_en'] = $payload['modificado_en'] ?? now('America/Lima');
        $isPresentationTransition = in_array($licitacion->estado, ['cotizacion_generada', 'vencida'], true)
            && ($payload['estado'] ?? null) === 'atendido';
        $canSyncNestedData = $this->isCreator($request, $licitacion) && ! $isPresentationTransition;
        $previousEstado = $licitacion->estado;

        DB::transaction(function () use ($request, $licitacion, $payload, $canSyncNestedData, $previousEstado): void {
            $licitacion->update($payload);

            $isProposalPresentation = in_array($previousEstado, ['cotizacion_generada', 'vencida'], true)
                && ($payload['estado'] ?? null) === 'atendido'
                && $this->canPresentProposal($request, $licitacion);

            if ($isProposalPresentation) {
                $evidencia = $request->input('presentacion_evidencia', $request->input('presentacionEvidencia'));

                if (is_array($evidencia)) {
                    $this->createArchivoFromPayload($licitacion, $evidencia, 'adjunto');
                }

                $licitacion->historial()->create([
                    'fecha' => now('America/Lima'),
                    'usuario' => $this->userDisplayName($request->user()),
                    'tipo' => 'estado',
                    'descripcion' => $previousEstado === 'vencida'
                        ? 'Propuesta presentada fuera de registro con evidencia, posterior al vencimiento.'
                        : 'Propuesta presentada/subida con evidencia en la plataforma correspondiente.',
                ]);
            }

            if ($canSyncNestedData) {
                $this->syncNestedData($licitacion, $request);
            }
        });

        if ($previousEstado !== 'atendido' && ($payload['estado'] ?? null) === 'atendido') {
            $licitacion->refresh();
            $this->notifyOpportunityStakeholders(
                new OportunidadAtendidaNotification($licitacion, $request->user()),
                $licitacion
            );
        }

        return response()->json($this->serialize($this->loadRelations($licitacion->refresh())));
    }

    public function destroy(Request $request, Licitacion $licitacion)
    {
        $this->ensureCreator($request, $licitacion);

        $licitacion->delete();

        return response()->json([
            'message' => 'Oportunidad eliminada correctamente',
        ]);
    }

    public function addComentario(Request $request, Licitacion $licitacion)
    {
        $validated = $request->validate([
            'usuario' => 'nullable|string|max:255',
            'comentario' => 'required|string|max:5000',
            'fecha' => 'nullable|date',
        ]);

        $comentario = $licitacion->comentarios()->create([
            'usuario' => $validated['usuario'] ?? $this->userDisplayName($request->user()),
            'comentario' => $validated['comentario'],
            'fecha' => ! empty($validated['fecha']) ? $this->parseLimaDateTime($validated['fecha']) : now('America/Lima'),
        ]);

        $this->notifyOpportunityStakeholders(
            new OportunidadComentarioNotification($licitacion->refresh(), $comentario, $request->user()),
            $licitacion,
            $request->user()?->id
        );

        return response()->json($this->serializeComentario($comentario), 201);
    }

    public function addCotizacion(Request $request, Licitacion $licitacion)
    {
        $this->ensureAssignedExecutive($request, $licitacion);

        $validated = $request->validate([
            'cotizacion_id' => 'required|integer|exists:cotizaciones,id',
            'numero' => 'nullable|string|max:80',
            'estado' => 'nullable|string|max:80',
            'monto' => 'nullable|numeric|min:0',
            'moneda' => 'nullable|string|max:20',
            'userName' => 'nullable|string|max:255',
        ]);

        $cotizacionOrigen = Cotizacion::with(['estadoCotizacion', 'moneda'])->findOrFail($validated['cotizacion_id']);
        $userName = $validated['userName'] ?? $this->userDisplayName($request->user());

        $cotizacion = DB::transaction(function () use ($validated, $licitacion, $cotizacionOrigen, $userName): LicitacionCotizacion {
            $relacion = $licitacion->cotizaciones()->updateOrCreate(
                ['cotizacion_id' => $cotizacionOrigen->id],
                [
                    'numero' => $validated['numero'] ?? $cotizacionOrigen->numero,
                    'estado' => $validated['estado'] ?? $cotizacionOrigen->estadoCotizacion?->nombre ?? 'registrada',
                    'monto' => $validated['monto'] ?? $cotizacionOrigen->total,
                    'moneda' => $validated['moneda'] ?? $cotizacionOrigen->moneda?->codigo,
                    'creado_por' => $userName,
                    'creado_en' => now('America/Lima'),
                ]
            );

            $licitacion->update([
                'estado' => 'cotizacion_generada',
                'modificado_por' => $userName,
                'modificado_en' => now('America/Lima'),
            ]);

            $licitacion->historial()->create([
                'usuario' => $userName,
                'tipo' => 'cotizacion',
                'descripcion' => 'Cotizacion '.$relacion->numero.' vinculada a la oportunidad.',
                'fecha' => now('America/Lima'),
            ]);

            return $relacion;
        });

        if ($this->isApprovedCotizacion($cotizacionOrigen)) {
            $this->notifyLicitacionUsers($licitacion->refresh(), $cotizacionOrigen);
        }

        return response()->json($this->serializeCotizacion($cotizacion), 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        $request->merge([
            'tipo' => $this->nullableTrim($request->input('tipo')) ?? 'licitacion',
            'empresa' => trim((string) $request->input('empresa', '')),
            'requerimiento' => trim((string) $request->input('requerimiento', '')),
            'categoria' => $this->nullableTrim($request->input('categoria')),
            'observacion' => $this->nullableTrim($request->input('observacion')),
            'carpeta_servidor' => $request->input('carpeta_servidor', $request->input('carpetaServidor')),
            'forma_pago' => $request->input('forma_pago', $request->input('formaPago')),
            'destino_entrega' => $request->input('destino_entrega', $request->input('destinoEntrega')),
            'wherex_id' => $request->input('wherex_id', $request->input('wherexId')),
            'wherex_url' => $request->input('wherex_url', $request->input('wherexUrl')),
            'comentarios_generales' => $request->input('comentarios_generales', $request->input('comentariosGenerales')),
            'motivo_cierre' => $request->input('motivo_cierre', $request->input('motivoCierre')),
            'comentario_cierre' => $request->input('comentario_cierre', $request->input('comentarioCierre')),
            'asignado_a' => $request->input('asignado_a', $request->input('asignadoA')),
            'asignado_en' => $request->input('asignado_en', $request->input('asignadoEn')),
            'asignado_por' => $request->input('asignado_por', $request->input('asignadoPor')),
            'es_nueva' => $request->input('es_nueva', $request->input('esNueva', true)),
            'creado_por' => $request->input('creado_por', $request->input('creadoPor')),
            'modificado_por' => $request->input('modificado_por', $request->input('modificadoPor')),
            'creado_en' => $request->input('creado_en', $request->input('creadoEn')),
            'modificado_en' => $request->input('modificado_en', $request->input('modificadoEn')),
            'perdida_info' => $request->input('perdida_info', $request->input('perdidaInfo')),
            'lecciones_aprendidas' => $request->input('lecciones_aprendidas', $request->input('leccionesAprendidas')),
        ]);

        $ejecutivo = $request->input('ejecutivo', []);
        if (is_array($ejecutivo)) {
            $request->merge([
                'ejecutivo_id' => $request->input('ejecutivo_id', $ejecutivo['id'] ?? null),
                'ejecutivo_nombre' => $request->input('ejecutivo_nombre', $ejecutivo['nombre'] ?? null),
                'ejecutivo_email' => $request->input('ejecutivo_email', $ejecutivo['email'] ?? null),
            ]);
        }

        if ((int) $request->input('ejecutivo_id') === 0) {
            $request->merge(['ejecutivo_id' => null]);
        }

        if ((int) $request->input('asignado_a') === 0) {
            $request->merge(['asignado_a' => null]);
        }

        $validated = $request->validate([
            'tipo' => ['required', Rule::in(['licitacion', 'privado', 'wherex'])],
            'empresa' => 'required|string|max:255',
            'requerimiento' => 'required|string|max:255',
            'vigencia' => 'required|date',
            'categoria' => 'nullable|string|max:120',
            'estado' => ['required', Rule::in($this->estados())],
            'observacion' => 'nullable|string|max:5000',
            'ejecutivo_id' => 'nullable|integer|exists:users,id',
            'ejecutivo_nombre' => 'nullable|string|max:255',
            'ejecutivo_email' => 'nullable|email|max:255',
            'asignado_a' => 'nullable|integer|exists:users,id',
            'asignado_en' => 'nullable|date',
            'asignado_por' => 'nullable|string|max:255',
            'es_nueva' => 'nullable|boolean',
            'creado_por' => 'nullable|string|max:255',
            'modificado_por' => 'nullable|string|max:255',
            'creado_en' => 'nullable|date',
            'modificado_en' => 'nullable|date',
            'garantia' => 'nullable|string|max:255',
            'plazo' => 'nullable|string|max:255',
            'carpeta_servidor' => 'nullable|string|max:255',
            'forma_pago' => ['nullable', Rule::in(['credito_15', 'credito_30', 'al_contado'])],
            'destino_entrega' => 'nullable|string|max:255',
            'wherex_id' => 'nullable|string|max:255',
            'wherex_url' => 'nullable|string|max:500',
            'comentarios_generales' => 'nullable|string|max:5000',
            'motivo_cierre' => 'nullable|string|max:255',
            'comentario_cierre' => 'nullable|string|max:5000',
            'perdida_info' => 'nullable|array',
            'lecciones_aprendidas' => 'nullable|array',
        ]);

        $validated['es_nueva'] = filter_var($validated['es_nueva'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $validated['vigencia'] = $this->parseLimaDateTime($validated['vigencia']);

        foreach (['asignado_en', 'creado_en', 'modificado_en'] as $dateField) {
            if (! empty($validated[$dateField])) {
                $validated[$dateField] = $this->parseLimaDateTime($validated[$dateField]);
            }
        }

        return $validated;
    }

    private function syncNestedData(Licitacion $licitacion, Request $request): void
    {
        $licitacion->archivos()->delete();
        $this->createArchivoFromPayload($licitacion, $request->input('tdr'), 'tdr');

        foreach ((array) $request->input('archivos', []) as $archivo) {
            $this->createArchivoFromPayload($licitacion, $archivo, 'adjunto');
        }

        $licitacion->comentarios()->delete();
        foreach ((array) $request->input('comentarios', []) as $comentario) {
            if (is_array($comentario) && ! empty($comentario['comentario'])) {
                $licitacion->comentarios()->create([
                    'usuario' => $comentario['usuario'] ?? null,
                    'comentario' => $comentario['comentario'],
                    'fecha' => ! empty($comentario['fecha']) ? $this->parseLimaDateTime($comentario['fecha']) : now('America/Lima'),
                ]);
            }
        }

        $licitacion->historial()->delete();
        foreach ((array) $request->input('historial', []) as $historial) {
            if (is_array($historial) && ! empty($historial['descripcion'])) {
                $licitacion->historial()->create([
                    'usuario' => $historial['usuario'] ?? null,
                    'tipo' => $historial['tipo'] ?? 'estado',
                    'descripcion' => $historial['descripcion'],
                    'fecha' => ! empty($historial['fecha']) ? $this->parseLimaDateTime($historial['fecha']) : now('America/Lima'),
                ]);
            }
        }

        $licitacion->cotizaciones()->delete();
        foreach ((array) $request->input('cotizaciones', []) as $cotizacion) {
            if (is_array($cotizacion)) {
                $licitacion->cotizaciones()->create([
                    'cotizacion_id' => $cotizacion['cotizacion_id'] ?? $cotizacion['cotizacionId'] ?? null,
                    'numero' => $cotizacion['numero'] ?? null,
                    'estado' => $cotizacion['estado'] ?? null,
                    'monto' => $cotizacion['monto'] ?? null,
                    'moneda' => $cotizacion['moneda'] ?? null,
                    'creado_por' => $cotizacion['creado_por'] ?? $cotizacion['creadoPor'] ?? null,
                    'creado_en' => ! empty($cotizacion['creado_en'] ?? $cotizacion['creadoEn'] ?? null)
                        ? $this->parseLimaDateTime($cotizacion['creado_en'] ?? $cotizacion['creadoEn'])
                        : now('America/Lima'),
                ]);
            }
        }
    }

    private function createArchivoFromPayload(Licitacion $licitacion, mixed $payload, string $tipo): ?LicitacionArchivo
    {
        if (! is_array($payload) || empty($payload['nombre'])) {
            return null;
        }

        return $licitacion->archivos()->create([
            'tipo_archivo' => $tipo,
            'nombre' => $payload['nombre'],
            'mime_type' => $payload['tipo'] ?? $payload['mime_type'] ?? null,
            'tamanio' => $payload['tamanio'] ?? null,
            'data_url' => $payload['dataUrl'] ?? $payload['data_url'] ?? null,
            'path' => $payload['path'] ?? null,
            'creado_por' => $payload['creadoPor'] ?? $payload['creado_por'] ?? null,
            'creado_en' => ! empty($payload['creadoEn'] ?? $payload['creado_en'] ?? null)
                ? $this->parseLimaDateTime($payload['creadoEn'] ?? $payload['creado_en'])
                : now('America/Lima'),
        ]);
    }

    private function loadRelations(Licitacion $licitacion): Licitacion
    {
        return $licitacion->load($this->relations());
    }

    /**
     * @return array<int, string>
     */
    private function relations(bool $lite = false): array
    {
        $relations = [
            'ejecutivo:id,nombres,apellidos,email',
            'creador:id,nombres,apellidos,email',
        ];

        if ($lite) {
            return $relations;
        }

        return [
            ...$relations,
            'comentarios' => fn ($query) => $query->latest('fecha')->latest('id'),
            'historial' => fn ($query) => $query->latest('fecha')->latest('id'),
            'archivos' => fn ($query) => $query->latest('created_at'),
            'cotizaciones' => fn ($query) => $query
                ->with(['cotizacion.modificaciones' => fn ($modificacionQuery) => $modificacionQuery
                    ->where('estado', CotizacionModificacion::ESTADO_EN_REVISION)
                    ->latest('submitted_at')
                    ->latest('id')])
                ->latest('creado_en')
                ->latest('id'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Licitacion $licitacion, bool $withDetails = true): array
    {
        $tdr = null;
        $archivos = collect();

        if ($withDetails) {
            $tdr = $licitacion->archivos->firstWhere('tipo_archivo', 'tdr');
            $archivos = $licitacion->archivos
                ->where('tipo_archivo', '!=', 'tdr')
                ->values()
                ->map(fn (LicitacionArchivo $archivo) => $this->serializeArchivo($archivo));
        }

        return [
            'id' => (string) $licitacion->id,
            'tipo' => $licitacion->tipo,
            'empresa' => $licitacion->empresa,
            'requerimiento' => $licitacion->requerimiento,
            'vigencia' => $this->serializeLimaDateTime($licitacion->vigencia),
            'ejecutivo' => [
                'id' => $licitacion->ejecutivo_id ?? 0,
                'nombre' => $licitacion->ejecutivo ? $this->userDisplayName($licitacion->ejecutivo) : ($licitacion->ejecutivo_nombre ?? 'Sin ejecutivo'),
                'email' => $licitacion->ejecutivo?->email ?? $licitacion->ejecutivo_email,
            ],
            'asignado_a' => $licitacion->asignado_a,
            'asignado_en' => $this->serializeLimaDateTime($licitacion->asignado_en),
            'asignado_por' => $licitacion->asignado_por,
            'es_nueva' => $this->isNewForUser($licitacion, request()->user()),
            'categoria' => $licitacion->categoria,
            'estado' => $licitacion->estado,
            'observacion' => $licitacion->observacion,
            'creado_en' => $this->serializeLimaDateTime($licitacion->creado_en ?? $licitacion->created_at),
            'created_by' => $licitacion->created_by,
            'creado_por' => $licitacion->creador ? $this->userDisplayName($licitacion->creador) : $licitacion->creado_por,
            'modificado_en' => $this->serializeLimaDateTime($licitacion->modificado_en ?? $licitacion->updated_at),
            'modificado_por' => $licitacion->modificado_por,
            'garantia' => $licitacion->garantia,
            'plazo' => $licitacion->plazo,
            'carpeta_servidor' => $licitacion->carpeta_servidor,
            'tdr' => $withDetails && $tdr ? $this->serializeArchivo($tdr) : null,
            'forma_pago' => $licitacion->forma_pago,
            'destino_entrega' => $licitacion->destino_entrega,
            'wherex_id' => $licitacion->wherex_id,
            'wherex_url' => $licitacion->wherex_url,
            'comentarios_generales' => $licitacion->comentarios_generales,
            'motivo_cierre' => $licitacion->motivo_cierre,
            'comentario_cierre' => $licitacion->comentario_cierre,
            'perdida_info' => $licitacion->perdida_info,
            'lecciones_aprendidas' => $licitacion->lecciones_aprendidas,
            'comentarios' => $withDetails ? $licitacion->comentarios->map(fn (LicitacionComentario $comentario) => $this->serializeComentario($comentario))->values() : [],
            'archivos' => $withDetails ? $archivos : [],
            'historial' => $withDetails ? $licitacion->historial->map(fn (LicitacionHistorial $historial) => $this->serializeHistorial($historial))->values() : [],
            'cotizaciones' => $withDetails ? $licitacion->cotizaciones->map(fn (LicitacionCotizacion $cotizacion) => $this->serializeCotizacion($cotizacion))->values() : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeArchivo(LicitacionArchivo $archivo): array
    {
        return [
            'id' => (string) $archivo->id,
            'nombre' => $archivo->nombre,
            'tipo' => $archivo->mime_type,
            'tamanio' => $archivo->tamanio,
            'dataUrl' => $archivo->data_url,
            'path' => $archivo->path,
            'creadoEn' => $this->serializeLimaDateTime($archivo->creado_en ?? $archivo->created_at),
            'creadoPor' => $archivo->creado_por,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeComentario(LicitacionComentario $comentario): array
    {
        return [
            'id' => (string) $comentario->id,
            'usuario' => $comentario->usuario,
            'fecha' => $this->serializeLimaDateTime($comentario->fecha ?? $comentario->created_at),
            'comentario' => $comentario->comentario,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeHistorial(LicitacionHistorial $historial): array
    {
        return [
            'id' => (string) $historial->id,
            'fecha' => $this->serializeLimaDateTime($historial->fecha ?? $historial->created_at),
            'usuario' => $historial->usuario,
            'tipo' => $historial->tipo,
            'descripcion' => $historial->descripcion,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCotizacion(LicitacionCotizacion $cotizacion): array
    {
        $modificacionPendiente = $cotizacion->cotizacion?->modificaciones?->first();

        return [
            'id' => (string) $cotizacion->id,
            'cotizacionId' => $cotizacion->cotizacion_id,
            'numero' => $cotizacion->numero,
            'fecha' => $this->serializeLimaDateTime($cotizacion->creado_en ?? $cotizacion->created_at),
            'estado' => $cotizacion->estado,
            'monto' => $cotizacion->monto,
            'moneda' => $cotizacion->moneda,
            'creadoPor' => $cotizacion->creado_por,
            'creadoEn' => $this->serializeLimaDateTime($cotizacion->creado_en ?? $cotizacion->created_at),
            'tieneModificacionPendiente' => (bool) $modificacionPendiente,
            'modificacionPendiente' => $modificacionPendiente ? [
                'id' => $modificacionPendiente->id,
                'estado' => $modificacionPendiente->estado,
                'submittedAt' => $this->serializeLimaDateTime($modificacionPendiente->submitted_at),
                'motivo' => $modificacionPendiente->motivo,
            ] : null,
        ];
    }

    private function nullableTrim(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function ensureAssignedExecutive(Request $request, Licitacion $licitacion): void
    {
        $userId = (int) $request->user()?->id;
        $assignedId = (int) ($licitacion->asignado_a ?: $licitacion->ejecutivo_id);

        if ($userId > 0 && $assignedId > 0 && $userId === $assignedId) {
            return;
        }

        abort(403, 'Solo el ejecutivo asignado puede generar o vincular cotizaciones para esta oportunidad.');
    }

    private function ensureCreator(Request $request, Licitacion $licitacion): void
    {
        if ($this->isCreator($request, $licitacion)) {
            return;
        }

        abort(403, 'Solo el usuario que subio la oportunidad puede editarla.');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function ensureCanUpdate(Request $request, Licitacion $licitacion, array $payload): void
    {
        $isPresentationTransition = in_array($licitacion->estado, ['cotizacion_generada', 'vencida'], true)
            && ($payload['estado'] ?? null) === 'atendido';

        if ($isPresentationTransition) {
            if (! $this->canPresentProposal($request, $licitacion)) {
                abort(403, 'No tienes permiso para marcar esta oportunidad como presentada.');
            }

            $evidencia = $request->input('presentacion_evidencia', $request->input('presentacionEvidencia'));
            if (! is_array($evidencia)) {
                abort(422, 'Debe adjuntar una evidencia para marcar la oportunidad como presentada.');
            }

            if ($this->contentFieldsAreUnchanged($licitacion, $payload)) {
                return;
            }

            abort(403, 'No se puede modificar el contenido al marcar la oportunidad como presentada.');
        }

        if ($this->isCreator($request, $licitacion)) {
            return;
        }

        $userId = (int) $request->user()?->id;
        $assignedId = (int) ($licitacion->asignado_a ?: $licitacion->ejecutivo_id);
        $nextAssignedId = (int) ($payload['asignado_a'] ?? $payload['ejecutivo_id'] ?? 0);
        $isAssigningSelf = $userId > 0
            && $licitacion->estado === 'sin_atender'
            && $assignedId === 0
            && $nextAssignedId === $userId
            && ($payload['estado'] ?? null) === 'en_atencion';
        $isAssignedWorkflow = $userId > 0 && $assignedId === $userId;
        if (($isAssigningSelf || $isAssignedWorkflow) && $this->contentFieldsAreUnchanged($licitacion, $payload)) {
            return;
        }

        abort(403, 'Solo el usuario que subio la oportunidad puede editarla.');
    }

    private function isCreator(Request $request, Licitacion $licitacion): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        if ($licitacion->created_by && (int) $licitacion->created_by === (int) $user->id) {
            return true;
        }

        if (! $licitacion->created_by) {
            $creator = mb_strtolower(trim((string) $licitacion->creado_por));
            $currentNames = mb_strtolower($this->userDisplayName($user));
            $currentEmail = mb_strtolower((string) $user->email);

            if ($creator !== '' && ($creator === $currentNames || $creator === $currentEmail)) {
                return true;
            }
        }

        return false;
    }

    private function canPresentProposal(Request $request, Licitacion $licitacion): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        if ($licitacion->tipo === 'licitacion') {
            return $user->hasRole('licitacion') || $this->isCreator($request, $licitacion);
        }

        return (int) ($licitacion->asignado_a ?: $licitacion->ejecutivo_id) === (int) $user->id;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function contentFieldsAreUnchanged(Licitacion $licitacion, array $payload): bool
    {
        $fields = [
            'tipo',
            'empresa',
            'requerimiento',
            'categoria',
            'observacion',
            'garantia',
            'plazo',
            'carpeta_servidor',
            'forma_pago',
            'destino_entrega',
            'wherex_id',
            'wherex_url',
            'comentarios_generales',
        ];

        foreach ($fields as $field) {
            if (! $this->sameScalarValue($licitacion->{$field}, $payload[$field] ?? null)) {
                return false;
            }
        }

        if (! $this->sameDateTimeValue($licitacion->vigencia, $payload['vigencia'] ?? null)) {
            return false;
        }

        return true;
    }

    private function sameScalarValue(mixed $current, mixed $next): bool
    {
        return trim((string) ($current ?? '')) === trim((string) ($next ?? ''));
    }

    private function sameDateTimeValue(mixed $current, mixed $next): bool
    {
        if (! $current && ! $next) {
            return true;
        }

        if (! $current || ! $next) {
            return false;
        }

        return $this->parseLimaDateTime($current)->equalTo($this->parseLimaDateTime($next));
    }

    private function parseLimaDateTime(mixed $value): Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->setTimezone('America/Lima');
        }

        $date = trim((string) $value);
        $hasExplicitTimezone = (bool) preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/i', $date);

        return $hasExplicitTimezone
            ? Carbon::parse($date)->setTimezone('America/Lima')
            : Carbon::parse($date, 'America/Lima');
    }

    private function serializeLimaDateTime(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        return $this->parseLimaDateTime($value)->toIso8601String();
    }

    private function userDisplayName(?User $user): string
    {
        if (! $user) {
            return 'Usuario';
        }

        return trim("{$user->nombres} {$user->apellidos}") ?: $user->email;
    }

    private function isApprovedCotizacion(Cotizacion $cotizacion): bool
    {
        $cotizacion->loadMissing('estadoCotizacion');

        return mb_strtolower((string) $cotizacion->estadoCotizacion?->nombre) === 'aprobada';
    }

    private function notifyLicitacionUsers(Licitacion $licitacion, Cotizacion $cotizacion): void
    {
        $this->notifyLicitacionAndSuperadmins(new LicitacionCotizacionListaNotification($licitacion, $cotizacion));
    }

    private function notifyLicitacionAndSuperadmins(object $notification): void
    {
        User::role(['licitacion', 'superadmin'])->get()->each->notify($notification);
    }

    private function notifyOpportunityStakeholders(object $notification, Licitacion $licitacion, ?int $excludeUserId = null): void
    {
        $roleUsers = User::role(['licitacion', 'superadmin'])
            ->where('activo', true)
            ->get();

        $directUserIds = collect([
            $licitacion->asignado_a,
            $licitacion->ejecutivo_id,
            $licitacion->created_by,
        ])
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $directUsers = $directUserIds->isEmpty()
            ? collect()
            : User::query()->whereIn('id', $directUserIds)->where('activo', true)->get();

        $roleUsers
            ->merge($directUsers)
            ->unique('id')
            ->reject(fn (User $user): bool => $excludeUserId !== null && (int) $user->id === $excludeUserId)
            ->each
            ->notify($notification);
    }

    private function notifyNewOpportunity(Licitacion $licitacion, ?User $creator): void
    {
        $notification = new NuevaOportunidadDisponibleNotification($licitacion, $creator);

        User::role(['ventas', 'superadmin'])
            ->where('activo', true)
            ->get()
            ->each
            ->notify($notification);
    }

    private function markAsViewed(Licitacion $licitacion, ?User $user): void
    {
        if (! $user) {
            return;
        }

        LicitacionVista::query()->updateOrCreate(
            [
                'licitacion_id' => $licitacion->id,
                'user_id' => $user->id,
            ],
            ['visto_en' => now('America/Lima')]
        );
    }

    private function isNewForUser(Licitacion $licitacion, ?User $user): bool
    {
        if (! $licitacion->es_nueva || ! $user) {
            return (bool) $licitacion->es_nueva;
        }

        return ! LicitacionVista::query()
            ->where('licitacion_id', $licitacion->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * @return array<int, string>
     */
    private function estados(): array
    {
        return [
            'sin_atender',
            'en_atencion',
            'atendido',
            'cotizacion_generada',
            'ganada',
            'perdida',
            'no_se_realizara',
            'vencida',
        ];
    }
}
