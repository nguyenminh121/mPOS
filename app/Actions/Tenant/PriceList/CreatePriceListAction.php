<?php

namespace App\Actions\Tenant\PriceList;

use App\Models\PriceList;
use App\Models\User;
use App\Services\Tenant\PriceListService;

class CreatePriceListAction
{
    public function __construct(private readonly PriceListService $priceListService)
    {
    }

    public function execute(User $user, array $data): PriceList
    {
        return $this->priceListService->create($user, $data);
    }
}
