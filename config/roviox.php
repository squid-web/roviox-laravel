<?php

return [

    /*
    | Base URL of the Roviox API. The default points at the hosted service,
    | so you normally only set ROVIOX_KEY. Override this when you self-host
    | or when you develop against a local install.
    */
    'url' => env('ROVIOX_URL', 'https://api.roviox.app'),

    /*
    | Domain API key, created in Roviox under Settings → API keys.
    | The key determines which domain you send for.
    */
    'key' => env('ROVIOX_KEY'),

    'timeout' => env('ROVIOX_TIMEOUT', 15),
];
