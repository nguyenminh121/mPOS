<?php

namespace App\Actions\Tenant\PriceList;

use App\Models\PriceList;
use App\Models\User;
use App\Services\Tenant\PriceListService;

class DeletePriceListAction
{
    public function __construct(private readonly PriceListService $priceListService)
    {
    }

    public function execute(User $user, PriceList $priceList): void
    {
        $this->priceListService->delete($user, $priceList);
    }
}
