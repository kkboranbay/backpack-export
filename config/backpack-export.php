<?php

return [
    'limitPerRequest' => 1000, // This is the value that is used in the list to display the number of records in one page.
    'limitPerRequestExceptions' => [
        'route-name' => 500, // this is just example
    ],
    'queueConnection' =>  env('QUEUE_CONNECTION', 'database'),
    'onQueue' =>  'default',
    'disableCSRFhash' =>  env('DISABLE_CSRF_HASH', 'some-hash'),
    'http-timeout' => 180,
];