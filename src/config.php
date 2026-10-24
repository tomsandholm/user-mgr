<?php

return [
    'db' => [
        'host'    => getenv('DB_HOST') ?: 'db',
        'name'    => getenv('DB_NAME') ?: 'users',
        'user'    => getenv('DB_USER') ?: 'root',
        'pass'    => getenv('DB_PASS') ?: 'theresa1',
        'charset' => 'utf8mb4',
    ],
    'admin' => [
        'user' => getenv('ADMIN_USER') ?: 'admin',
        'pass' => getenv('ADMIN_PASS') ?: 'theresa1',
    ],
];
