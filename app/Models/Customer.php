<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'email',
        'phone',
        'address',
        'type',
        'tax_code',
        'company_name',
        'debt_amount',
        'total_spent',
        'credit_limit',
        'default_due_days',
        'description',
        'group_id',
        'price_list_id',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'debt_amount' => 'decimal:2',
            'total_spent' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function getRouteKey(): mixed
    {
        $code = trim((string) ($this->code ?? ''));

        return $code !== '' ? $code : (string) $this->getKey();
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $field = $field ?? $this->getRouteKeyName();

        if ($field !== 'code') {
            return parent::resolveRouteBinding($value, $field);
        }

        $query = $this->newQuery();
        $resolved = $query->where('code', (string) $value)->first();
        if ($resolved instanceof self) {
            return $resolved;
        }

        if (is_numeric($value)) {
            return $this->newQuery()->whereKey((int) $value)->first();
        }

        return null;
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'group_id');
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function returnOrders(): HasMany
    {
        return $this->hasMany(ReturnOrder::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function accountReceivables(): HasMany
    {
        return $this->hasMany(AccountReceivable::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(CustomerAudit::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function defaultAddress(): HasOne
    {
        return $this->hasOne(CustomerAddress::class)
            ->where('default_address', 'YES')
            ->latest('id');
    }
}
