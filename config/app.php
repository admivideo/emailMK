<?php

declare(strict_types=1);

return [
    'name' => getenv('APP_NAME') ?: 'emailMK',
    'env' => getenv('APP_ENV') ?: 'local',
    'timezone' => getenv('APP_TIMEZONE') ?: 'UTC',
    'base_url' => getenv('APP_URL') ?: 'http://localhost',
];
