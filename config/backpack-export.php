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

    // Write cells that look like dates as real Excel dates instead of text.
    'convert-dates' => [
        'enabled' => false,
        // How dates look in the list (Carbon isoFormat). null = backpack.base.default_datetime_format / default_date_format.
        'input-datetime-formats' => null,
        'input-date-formats' => null,
        // Locale of month names in the list. null = app()->getLocale().
        'locale' => null,
        // How dates are displayed in Excel (Excel number format codes), e.g. 'YYYY-MM-DD HH:MM', 'DD-MM-YYYY'.
        'excel-datetime-format' => 'DD.MM.YYYY HH:MM',
        'excel-date-format' => 'DD.MM.YYYY',
    ],
];