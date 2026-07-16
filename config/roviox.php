<?php

return [

    /*
    | Base URL of your Roviox API host, e.g. https://api.roviox.example
    | (the dedicated API domain, without a trailing slash).
    */
    'url' => env('ROVIOX_URL', 'https://api.roviox.test'),

    /*
    | Domain API key, created in Roviox under Settings → API keys.
    | The key determines which domain you send for.
    */
    'key' => env('ROVIOX_KEY'),

    'timeout' => env('ROVIOX_TIMEOUT', 15),
];
