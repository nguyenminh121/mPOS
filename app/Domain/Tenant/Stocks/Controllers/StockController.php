<?php

namespace App\Domain\Tenant\Stocks\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Stock;
use App\Services\Tenant\StockService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    public function __construct(private readonly StockService $stockService)
    {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Stock::class);

        return Inertia::render('tenant/stocks/Index', [
            'stocks' => $this->stockService->list(),
        ]);
    }

    public function show(Stock $stock): Response
    {
        $this->authorize('view', $stock);

        return Inertia::render('tenant/stocks/Show', [
            'stock' => $this->stockService->detail($stock),
        ]);
    }
}
