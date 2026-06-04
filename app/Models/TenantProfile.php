<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantProfile extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'company_name',
        'company_address',
        'company_phone',
        'company_email',
        'tax_code',
        'logo_path',
    ];
}
