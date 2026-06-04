<?php

namespace App\Domain\Tenant\BusinessExpenses\Controllers;

use App\Actions\Tenant\BusinessExpense\CreateBusinessExpenseAction;
use App\Actions\Tenant\BusinessExpense\DeleteBusinessExpenseAction;
use App\Actions\Tenant\BusinessExpense\UpdateBusinessExpenseAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StoreBusinessExpenseRequest;
use App\Http\Requests\Payment\UpdateBusinessExpenseRequest;
use App\Models\BusinessExpense;
use App\Models\Order;
use App\Models\Supplier;
use App\Services\Tenant\BusinessExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BusinessExpenseController extends Controller
{
    public function __construct(
        private readonly BusinessExpenseService $businessExpenseService,
        private readonly CreateBusinessExpenseAction $createBusinessExpenseAction,
        private readonly UpdateBusinessExpenseAction $updateBusinessExpenseAction,
        private readonly DeleteBusinessExpenseAction $deleteBusinessExpenseAction,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', BusinessExpense::class);

        return Inertia::render('tenant/business-expenses/Index', [
            'expenses' => $this->businessExpenseService->list($request->user())->map(
                fn (BusinessExpense $expense) => $this->toPayload($expense)
            )->values(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', BusinessExpense::class);

        return Inertia::render('tenant/business-expenses/Form', [
            'mode' => 'create',
            'expense' => null,
            'orderOptions' => $this->orderOptions($request),
            'supplierOptions' => $this->supplierOptions($request),
        ]);
    }

    public function store(StoreBusinessExpenseRequest $request): RedirectResponse
    {
        $this->authorize('create', BusinessExpense::class);

        $this->createBusinessExpenseAction->execute($request->user(), $request->validated());

        return redirect()->route('tenant.business-expenses.index')->with('success', 'Tạo chi phí doanh nghiệp thành công.');
    }

    public function show(BusinessExpense $businessExpense): Response
    {
        $this->authorize('view', $businessExpense);
        $businessExpense->load([
            'order:id,code',
            'supplier:id,code,name',
            'creator:id,name',
            'approvedByUser:id,name',
        ]);

        return Inertia::render('tenant/business-expenses/Show', [
            'expense' => $this->toPayload($businessExpense),
        ]);
    }

    public function edit(Request $request, BusinessExpense $businessExpense): Response
    {
        $this->authorize('update', $businessExpense);

        return Inertia::render('tenant/business-expenses/Form', [
            'mode' => 'edit',
            'expense' => $this->toPayload($businessExpense),
            'orderOptions' => $this->orderOptions($request),
            'supplierOptions' => $this->supplierOptions($request),
        ]);
    }

    public function update(UpdateBusinessExpenseRequest $request, BusinessExpense $businessExpense): RedirectResponse
    {
        $this->authorize('update', $businessExpense);

        $this->updateBusinessExpenseAction->execute($request->user(), $businessExpense, $request->validated());

        return redirect()->route('tenant.business-expenses.index')->with('success', 'Cập nhật chi phí doanh nghiệp thành công.');
    }

    public function destroy(Request $request, BusinessExpense $businessExpense): RedirectResponse
    {
        $this->authorize('delete', $businessExpense);

        $this->deleteBusinessExpenseAction->execute($request->user(), $businessExpense);

        return redirect()->route('tenant.business-expenses.index')->with('success', 'Xóa chi phí doanh nghiệp thành công.');
    }

    private function orderOptions(Request $request)
    {
        return Order::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderByDesc('id')
            ->limit(300)
            ->get(['id', 'code'])
            ->values();
    }

    private function supplierOptions(Request $request)
    {
        return Supplier::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->values();
    }

    private function toPayload(BusinessExpense $expense): array
    {
        return [
            'id' => $expense->id,
            'code' => $expense->code,
            'name' => $expense->name,
            'description' => $expense->description,
            'category' => $expense->category,
            'amount' => (float) $expense->amount,
            'order_id' => $expense->order_id,
            'supplier_id' => $expense->supplier_id,
            'payment_method' => $expense->payment_method,
            'payment_status' => $expense->payment_status,
            'paid_at' => optional($expense->paid_at)?->toDateTimeString(),
            'expense_date' => optional($expense->expense_date)?->format('Y-m-d'),
            'note' => $expense->note,
            'order_code' => $expense->order?->code,
            'supplier_name' => $expense->supplier?->name,
            'supplier_code' => $expense->supplier?->code,
            'created_by_name' => $expense->creator?->name,
            'approved_by_name' => $expense->approvedByUser?->name,
        ];
    }
}
