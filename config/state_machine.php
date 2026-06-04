<?php

return [
    'rollout_mode' => env('STATE_MACHINE_ROLLOUT_MODE', 'legacy'),

    'flags' => [
        'order' => env('STATE_MACHINE_ORDER_ENABLED', false),
        'invoice' => env('STATE_MACHINE_INVOICE_ENABLED', false),
        'return_order' => env('STATE_MACHINE_RETURN_ORDER_ENABLED', false),
        'import_receipt' => env('STATE_MACHINE_IMPORT_RECEIPT_ENABLED', false),
        'stock_take' => env('STATE_MACHINE_STOCK_TAKE_ENABLED', false),
    ],

    'domain_services' => [
        'order' => env('DOMAIN_SERVICE_ORDER_ENABLED', false),
        'invoice' => env('DOMAIN_SERVICE_INVOICE_ENABLED', false),
        'inventory' => env('DOMAIN_SERVICE_INVENTORY_ENABLED', false),
    ],

    'audit' => [
        'enabled' => env('STATE_MACHINE_AUDIT_ENABLED', true),
        'channel' => env('STATE_MACHINE_AUDIT_CHANNEL', env('LOG_CHANNEL', 'stack')),
    ],
];
