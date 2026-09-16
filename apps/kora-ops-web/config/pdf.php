<?php

return [
    'chrome_path' => env('BROWSERSHOT_CHROME_PATH', '/usr/bin/chromium'),
    'node_binary' => env('BROWSERSHOT_NODE_BINARY', '/usr/bin/node'),
    'npm_binary' => env('BROWSERSHOT_NPM_BINARY', '/usr/bin/npm'),
    'temp_dir' => storage_path('app/temp/chrome'),
    'home_dir' => env('BROWSERSHOT_HOME', storage_path('app/temp/chrome/home')),
    'timeout' => 60000,

    'common_args' => [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        '--disable-gpu',
        '--no-first-run',
        '--no-zygote',
        '--headless',
        '--allow-running-insecure-content',
        '--ignore-certificate-errors',
    ],

    'profiles' => [
        // 80mm thermal ticket — narrow width, auto height
        'ticket' => [
            'paper_size' => [80, 297], // mm — width fixed, height generous (auto-paginates)
            'margins' => [0, 0, 0, 0],
            'window_size' => [302, 1200], // 80mm @ 96dpi ≈ 302px
            'device_scale_factor' => 2,
            'wait_until_network_idle' => true,
            'emulate_media' => 'print',
            'args' => [
                '--disable-web-security',
                '--disable-crash-reporter',
                '--font-render-hinting=medium',
                '--enable-font-antialiasing',
            ],
        ],
    ],
];
