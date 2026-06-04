<?php

namespace App\Http\Middleware;

use App\Services\Tenant\InvoiceUsageConfigService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'invoicePolicy' => function () use ($request) {
                $user = $request->user();
                if (! $user) {
                    return [
                        'use_invoice_on_sale' => false,
                        'invoice_mode' => 'POS',
                    ];
                }

                return app(InvoiceUsageConfigService::class)->getPolicy($user);
            },
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'created_order_id' => fn () => $request->session()->get('created_order_id'),
                'created_order_code' => fn () => $request->session()->get('created_order_code'),
                'created_mode' => fn () => $request->session()->get('created_mode'),
                'created_preorder_status' => fn () => $request->session()->get('created_preorder_status'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
