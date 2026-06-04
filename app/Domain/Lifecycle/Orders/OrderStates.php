<?php

namespace App\Domain\Lifecycle\Orders;

final class OrderStates
{
    public const PREORDER = 'PREORDER';
    public const DRAFT = 'DRAFT';
    public const PROCESSING = 'PROCESSING';
    public const CONFIRMED = 'CONFIRMED';
    public const COMPLETED = 'COMPLETED';
    public const CANCELLED = 'CANCELLED';

    public static function all(): array
    {
        return [
            self::PREORDER,
            self::DRAFT,
            self::PROCESSING,
            self::CONFIRMED,
            self::COMPLETED,
            self::CANCELLED,
        ];
    }

    public static function normalize(string $state): string
    {
        return strtoupper(trim($state));
    }
}
