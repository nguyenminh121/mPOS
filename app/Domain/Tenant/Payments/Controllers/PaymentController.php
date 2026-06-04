<?php

namespace App\Domain\Tenant\Payments\Controllers;

use App\Actions\Tenant\Payment\CreateBatchDebtPaymentAction;
use App\Actions\Tenant\Payment\CreatePaymentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StoreBatchDebtPaymentRequest;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(
        private readonly CreatePaymentAction $createPaymentAction,
        private readonly CreateBatchDebtPaymentAction $createBatchDebtPaymentAction,
    ) {
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $this->authorize('create', Payment::class);

        $payment = $this->createPaymentAction->execute($request->user(), $request->validated());

        return response()->json([
            'message' => 'Ghi nhận thanh toán thành công.',
            'payment' => $payment,
        ], 201);
    }

    public function storeBatchDebtPayment(StoreBatchDebtPaymentRequest $request): JsonResponse
    {
        $this->authorize('create', Payment::class);

        $result = $this->createBatchDebtPaymentAction->execute($request->user(), $request->validated());

        return response()->json([
            'message' => 'Ghi nhận thu công nợ FIFO thành công.',
            ...$result,
        ], 201);
    }
}
