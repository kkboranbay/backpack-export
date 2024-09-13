<?php

use Illuminate\Support\Facades\Hash;

return [
    'login' =>  env('LOGIN_TO_ADMIN_PANEL', ''),
    'password' =>  env('PASSWORD_TO_ADMIN_PANEL', ''),
    'limitPerRequest' => 1000, // This is the value that is used in the list to display the number of records in one page.
    'queueConnection' =>  env('QUEUE_CONNECTION', 'database'),
    'onQueue' =>  'default',
    'disableCSRFhash' =>  env('DISABLE_CSRF_HASH', Hash::make('some-hash')),
];