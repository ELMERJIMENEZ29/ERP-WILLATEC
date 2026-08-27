<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductoSkuNormalizationService;
use Illuminate\Http\Request;

class ProductoSkuController extends Controller
{
    public function preview(Request $request, ProductoSkuNormalizationService $service)
    {
        $limit = min(max($request->integer('limit', 25), 1), 100);

        return response()->json($service->preview($limit));
    }

    public function apply(Request $request, ProductoSkuNormalizationService $service)
    {
        $limit = min(max($request->integer('limit', 25), 1), 100);

        return response()->json($service->apply($limit));
    }
}
