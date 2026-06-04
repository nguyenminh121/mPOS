<?php

namespace App\Domain\Tenant\Stocks\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Config\UpdateStockPolicyRequest;
use App\Models\CodeSequenceConfig;
use App\Services\Tenant\StockPolicyConfigService;
use Illuminate\Http\RedirectResponse;

class StockPolicyConfigController extends Controller
{
    public function __construct(private readonly StockPolicyConfigService $stockPolicyConfigService)
    {
    }

    public function update(UpdateStockPolicyRequest $request): RedirectResponse
    {
        $this->authorize('create', CodeSequenceConfig::class);

        $this->stockPolicyConfigService->updatePolicy(
            $request->user(),
            (bool) $request->boolean('allow_negative_stock'),
        );

        return redirect()->route('tenant.config.index')->with('success', 'Stock policy updated.');
    }
}
