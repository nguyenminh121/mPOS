<?php

namespace App\Domain\Tenant\Configs\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Config\UpdateInvoicePolicyRequest;
use App\Models\CodeSequenceConfig;
use App\Services\Tenant\InvoiceUsageConfigService;
use Illuminate\Http\RedirectResponse;

class InvoicePolicyConfigController extends Controller
{
    public function __construct(private readonly InvoiceUsageConfigService $invoiceUsageConfigService)
    {
    }

    public function update(UpdateInvoicePolicyRequest $request): RedirectResponse
    {
        $this->authorize('create', CodeSequenceConfig::class);

        $this->invoiceUsageConfigService->updatePolicyWithMode(
            $request->user(),
            (bool) $request->boolean('use_invoice_on_sale'),
            (string) $request->string('invoice_mode')->toString(),
        );

        return redirect()->route('tenant.config.index')->with('success', 'Invoice policy updated.');
    }
}
