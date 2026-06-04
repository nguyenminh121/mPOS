<?php

namespace App\Actions\Tenant\PriceList;

use App\Models\PriceList;
use App\Models\User;
use App\Services\Tenant\PriceListService;

class UpdatePriceListAction
{
    public function __construct(private readonly PriceListService $priceListService)
    {
    }

    public function execute(User $user, PriceList $priceList, array $data): PriceList
    {
        return $this->priceListService->update($user, $priceList, $data);
    }
}
