<?php

namespace App\Domain\Tenant\Configs\Controllers;

use App\Actions\Tenant\Config\DeleteCodeSequenceConfigAction;
use App\Actions\Tenant\Config\UpsertCodeSequenceConfigAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Config\StoreCodeSequenceConfigRequest;
use App\Http\Requests\Config\UpdateCodeSequenceConfigRequest;
use App\Models\CodeSequenceConfig;
use App\Services\Tenant\CodeSequenceService;
use App\Services\Tenant\InvoiceUsageConfigService;
use App\Services\Tenant\StockPolicyConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantConfigController extends Controller
{
    public function __construct(
        private readonly CodeSequenceService $codeSequenceService,
        private readonly StockPolicyConfigService $stockPolicyConfigService,
        private readonly InvoiceUsageConfigService $invoiceUsageConfigService,
        private readonly UpsertCodeSequenceConfigAction $upsertCodeSequenceConfigAction,
        private readonly DeleteCodeSequenceConfigAction $deleteCodeSequenceConfigAction,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CodeSequenceConfig::class);

        return Inertia::render('tenant/config/Index', [
            'codeSequences' => $this->codeSequenceService->listForTenant($request->user()),
            'stockPolicy' => $this->stockPolicyConfigService->getPolicy($request->user()),
            'invoicePolicy' => $this->invoiceUsageConfigService->getPolicy($request->user()),
        ]);
    }

    public function store(StoreCodeSequenceConfigRequest $request): RedirectResponse
    {
        $this->authorize('create', CodeSequenceConfig::class);

        $this->upsertCodeSequenceConfigAction->create($request->user(), $request->validated());

        return redirect()->route('tenant.config.index')->with('success', 'Code sequence configuration saved.');
    }

    public function update(UpdateCodeSequenceConfigRequest $request, CodeSequenceConfig $config): RedirectResponse
    {
        $this->authorize('update', $config);

        $this->upsertCodeSequenceConfigAction->update($request->user(), $config, $request->validated());

        return redirect()->route('tenant.config.index')->with('success', 'Code sequence configuration updated.');
    }

    public function destroy(Request $request, CodeSequenceConfig $config): RedirectResponse
    {
        $this->authorize('delete', $config);

        $this->deleteCodeSequenceConfigAction->execute($request->user(), $config);

        return redirect()->route('tenant.config.index')->with('success', 'Code sequence configuration deleted.');
    }
}
