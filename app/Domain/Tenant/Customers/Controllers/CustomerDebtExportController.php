<?php

namespace App\Domain\Tenant\Customers\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\ExportCustomerDebtRequest;
use App\Models\Customer;
use App\Services\Tenant\CustomerDebtExportService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CustomerDebtExportController extends Controller
{
    public function __construct(
        private readonly CustomerDebtExportService $customerDebtExportService,
    ) {
    }

    public function __invoke(ExportCustomerDebtRequest $request, Customer $customer): BinaryFileResponse
    {
        $this->authorize('view', $customer);

        $result = $this->customerDebtExportService->generate(
            user: $request->user(),
            customer: $customer,
            filters: $request->validated(),
        );

        return response()->download($result['path'], $result['filename'])->deleteFileAfterSend(true);
    }
}

