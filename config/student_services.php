<?php

return [
    // Temporary test mode. Set ID_CARD_ALLOW_REPEAT_REQUESTS=false to restore the one-open-request limit.
    'allow_repeat_id_card_requests' => env('ID_CARD_ALLOW_REPEAT_REQUESTS', true),
];
