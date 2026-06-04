<?php

return [
    'auto' => [
        'enabled' => (bool) env('AUDIT_AUTO_ENABLED', true),
        'exclude_tables' => [
            'audit_logs',
        ],
    ],
];
