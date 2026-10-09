<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmarCompraRequest;
use App\Http\Requests\StoreCompraRequest;
use App\Models\Compra;
use App\Services\CompraService;
use App\Services\WorkflowNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompraController extends Controller
{
    public function __construct(
        private readonly CompraService $compraService,
        private readonly WorkflowNotificationService $notifications
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Compra::query()
            ->with([
                'proveedor',
                'moneda',
                'ocEmitida',
                'creadoPor',
            ])
            ->withCount('items')
            ->latest('id');

        if ($request->filled('estado')) {
            $query->where(
                'estado',
                $request->string('estado')
            );
        }

        if ($request->filled('modalidad')) {
            $query->where(
                'modalidad',
                $request->string('modalidad')
            );
        }

        if ($request->filled('proveedor_id')) {
            $query->where(
                'proveedor_id',
                $request->integer('proveedor_id')
            );
        }

        if ($request->filled('buscar')) {
            $buscar = trim(
                (string) $request->input('buscar')
            );

            $query->where(function ($query) use ($buscar) {
                $query
                    ->where('numero', 'like', "%{$buscar}%")
                    ->orWhereHas(
                        'proveedor',
                        fn ($q) => $q->where(
                            'nombre',
                            'like',
                            "%{$buscar}%"
                        )
                    );
            });
        }

        return response()->json(
            $query->paginate(
                min(
                    max(
                        (int) $request->input('per_page', 20),
                        1
                    ),
                    100
                )
            )
        );
    }

    public function store(
        StoreCompraRequest $request
    ): JsonResponse {
        $compra = $this->compraService->crear(
            $request->validated(),
            $request->user()?->id
        );
        $this->notifications->compras('Nueva compra registrada', "Se registró la compra {$compra->numero} para {$compra->proveedor?->nombre}.", '/compras', 'compra_creada', ['compra_id' => $compra->id]);

        return response()->json(
            $compra,
            201
        );
    }

    public function show(
        Compra $compra
    ): JsonResponse {
        return response()->json(
            $compra->load([
                'proveedor',
                'moneda',
                'ocEmitida',
                'creadoPor',
                'items.requerimientoCompraItem.requerimiento',
                'items.producto',
                'items.productoExterno',
                'items.moneda',
                'items.ocEmitidaItem',
            ])
        );
    }

    public function confirmar(
        ConfirmarCompraRequest $request,
        Compra $compra
    ): JsonResponse {
        $debeNotificar = $compra->estado === Compra::ESTADO_BORRADOR;
        $recepcionesAntes = $compra->recepciones()->pluck('id');
        $compra = $this->compraService->confirmar($compra, $request->user()?->id);
        if ($debeNotificar) {
            $this->notifications->compras('Compra confirmada', "La compra {$compra->numero} fue confirmada y está pendiente de recepción.", '/compras/recepciones', 'compra_confirmada', ['compra_id' => $compra->id]);
            $recepcion = $compra->recepciones->first(fn ($item) => ! $recepcionesAntes->contains($item->id));
            if ($recepcion) {
                $this->notifications->compras('Recepción pendiente generada', "Se generó la recepción {$recepcion->numero} para la compra {$compra->numero}.", '/compras/recepciones', 'recepcion_creada', ['recepcion_id' => $recepcion->id, 'compra_id' => $compra->id]);
            }
        }

        return response()->json($compra);
    }

    public function cancelar(
        Request $request,
        Compra $compra
    ): JsonResponse {
        return response()->json(
            $this->compraService->cancelar($compra)
        );
    }
}
