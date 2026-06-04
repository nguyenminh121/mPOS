<?php

namespace App\Domain\Tenant\Payments\Controllers;

use App\Actions\Tenant\PaymentMethodConfig\CreatePaymentMethodConfigAction;
use App\Actions\Tenant\PaymentMethodConfig\DeletePaymentMethodConfigAction;
use App\Actions\Tenant\PaymentMethodConfig\UpdatePaymentMethodConfigAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentMethodConfigRequest;
use App\Http\Requests\Payment\UpdatePaymentMethodConfigRequest;
use App\Models\PaymentMethodConfig;
use App\Services\Tenant\PaymentMethodConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentMethodConfigController extends Controller
{
    public function __construct(
        private readonly PaymentMethodConfigService $paymentMethodConfigService,
        private readonly CreatePaymentMethodConfigAction $createPaymentMethodConfigAction,
        private readonly UpdatePaymentMethodConfigAction $updatePaymentMethodConfigAction,
        private readonly DeletePaymentMethodConfigAction $deletePaymentMethodConfigAction,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PaymentMethodConfig::class);

        return response()->json([
            'paymentMethodConfigs' => $this->paymentMethodConfigService->list($request->user())->values(),
        ]);
    }

    public function store(StorePaymentMethodConfigRequest $request): JsonResponse
    {
        $this->authorize('create', PaymentMethodConfig::class);

        $paymentMethodConfig = $this->createPaymentMethodConfigAction->execute($request->user(), $request->validated());

        return response()->json([
            'message' => 'Tạo phương thức thanh toán thành công.',
            'paymentMethodConfig' => $paymentMethodConfig,
        ], 201);
    }

    public function update(UpdatePaymentMethodConfigRequest $request, PaymentMethodConfig $paymentMethodConfig): JsonResponse
    {
        $this->authorize('update', $paymentMethodConfig);

        $updatedPaymentMethodConfig = $this->updatePaymentMethodConfigAction->execute($request->user(), $paymentMethodConfig, $request->validated());

        return response()->json([
            'message' => 'Cập nhật phương thức thanh toán thành công.',
            'paymentMethodConfig' => $updatedPaymentMethodConfig,
        ]);
    }

    public function destroy(Request $request, PaymentMethodConfig $paymentMethodConfig): JsonResponse
    {
        $this->authorize('delete', $paymentMethodConfig);

        $this->deletePaymentMethodConfigAction->execute($request->user(), $paymentMethodConfig);

        return response()->json([
            'message' => 'Xóa phương thức thanh toán thành công.',
        ]);
    }
}
