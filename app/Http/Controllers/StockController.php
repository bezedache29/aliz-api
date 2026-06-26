<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockItemRequest;
use App\Http\Requests\UpdateStockItemRequest;
use App\Models\StockItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class StockController extends Controller
{
    public function index(): JsonResponse
    {
        $items = StockItem::orderByRaw('expiry_date IS NULL, expiry_date ASC')
            ->orderBy('food_name')
            ->get();

        return response()->json(['data' => $items]);
    }

    public function store(StoreStockItemRequest $request): JsonResponse
    {
        $item = StockItem::create($request->validated());

        return response()->json(['data' => $item], 201);
    }

    public function update(UpdateStockItemRequest $request, StockItem $stock): JsonResponse
    {
        $stock->update($request->validated());

        return response()->json(['data' => $stock->refresh()]);
    }

    public function destroy(StockItem $stock): Response
    {
        $stock->delete();

        return response()->noContent();
    }
}
