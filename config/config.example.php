<?php

declare(strict_types=1);

return [
    'app_name' => 'Raja',
    'base_url' => '/raja',
    'timezone' => 'Europe/Paris',
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'raja', // Replace if you use another database name.
        'user' => 'CHANGE_ME',
        'password' => 'CHANGE_ME',
        'charset' => 'utf8mb4',
    ],
    'tools' => [
        // Leave empty to use Raja's automatic Windows/winget detection.
        // Set full paths only if automatic detection cannot find a tool.
        'tesseract' => '',
        'pdftotext' => '',
        'pdftoppm' => '',
    ],
];
