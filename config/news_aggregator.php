<?php

return [

    'timeout' => env('NEWS_AGGREGATOR_TIMEOUT', 30),

    'retry_attempts' => env('NEWS_AGGREGATOR_RETRY_ATTEMPTS', 3),

    'retry_delays' => [1000, 3000, 9000], // milliseconds

    'chunk_size' => 50, // articles per DB transaction batch

];
