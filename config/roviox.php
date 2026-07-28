<?php

return [

    /*
    | Domain API key, created in Roviox under Settings → API keys.
    | The key determines which domain you send for.
    */
    'key' => env('ROVIOX_KEY'),

    'timeout' => env('ROVIOX_TIMEOUT', 15),
];
