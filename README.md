## Introduction

This package is designed to address limitations in Laravel Backpack's built-in export feature, which uses jQuery DataTables and only exports the data visible on the page. While you can choose the 'Show All' option to load and export all data, this can lead to memory issues when dealing with large datasets.

Existing export packages for Laravel Backpack fall short in several ways—some suffer from memory leaks, others don't support email delivery, and many fail to work with filtered data or fields utilizing closures in `setupListOperation`. This package solves these issues by enabling seamless export of all or filtered data, delivering the exported files via email, all while avoiding memory leaks and handling closures without errors.

## Note

This package is designed to work only for Backpack Pro users.

## Environment Variables

Add the following variable to your `.env` file. It is necessary to fill in this variable for the package to function correctly:

```env
DISABLE_CSRF_HASH=your_unique_csrf_hash
```

## Installation

To install the package, follow these steps:

1. Install the package via Composer:

    ```bash
    composer require kkboranbay/backpack-export
    ```

2. Publish the configuration file:

    ```bash
    php artisan vendor:publish --tag=backpack-export-config
    ```

    This will create a new file at `config/backpack/operations/backpack-export.php` where you can customize options like the queue connection.

3. Publish the view files:

    ```bash
    php artisan vendor:publish --tag=backpack-export-views
    ```

    This will create a new folder `backpack-export` with view files in `resources/views/vendor/backpack/`. You can customize the email views or export button here.

4. Publish the translation files:

    ```bash
    php artisan vendor:publish --tag=backpack-export-translations
    ```

    This will create a new folder `backpack-export` in `resources/lang/vendor/backpack/backpack-export`. Here, you can add translations.

5. **Update CSRF Token Verification**

   To handle CSRF token verification for the export process, you need to update the `VerifyCsrfToken.php` file. Add the following code to `app/Http/Middleware/VerifyCsrfToken.php`:

    ```php
    public function handle($request, \Closure $next)
    {
        if ($request->has(config('backpack.operations.backpack-export.disableCSRFhash'))) {
            $this->except[] = '*';
        }

        return parent::handle($request, $next);
    }
    ```

### Usage


1. In your CRUD controller, add the `ExportOperation` trait to enable export functionality:

    ```php
    use \Kkboranbay\BackpackExport\Traits\ExportOperation;
    ```

2. To control access to the export feature, you can add permission checks in your CRUD controller. For example:

    ```php
    if (backpack_user() && backpack_user()->can('user-export')) {
        $this->crud->allowAccess('export');
    }
    ```

    This will ensure that only users with the `user-export` permission can see and use the export button.

### Dates as Excel dates

By default every cell is exported as text, so a datetime column ends up as `9 Nov 2026, 22:50` text in Excel. To export such cells as real Excel dates (sortable and filterable), enable `convert-dates` in `config/backpack/operations/backpack-export.php`:

```php
'convert-dates' => [
    'enabled' => true,
    'input-datetime-formats' => null, // null = backpack.base.default_datetime_format; string or array of Carbon isoFormat formats
    'input-date-formats' => null,     // null = backpack.base.default_date_format
    'locale' => null,                 // null = app()->getLocale()
    'excel-datetime-format' => 'dd.mm.yyyy hh:mm', // Excel number format, e.g. 'yyyy-mm-dd hh:mm'
    'excel-date-format' => 'dd.mm.yyyy',           // e.g. 'dd-mm-yyyy', 'yyyy-mm-dd'
],
```

Write Excel formats in lowercase: Excel accepts `DD.MM.YYYY` too, but Apple Numbers then shows the date as a plain number. Date columns are widened to fit the format.

A cell is converted only when it matches one of the input formats exactly. If a column contains a value with digits that is not a date, the rest of that column is left as text. Columns with a custom `format` in `setupListOperation` need that format added to `input-datetime-formats` / `input-date-formats`.



### Troubleshooting

If you encounter any issues:

- Ensure that all environment variables are correctly set in your `.env` file.
- Check the logs for any errors related to the export process.
- Verify that the necessary permissions and settings are correctly configured in Laravel Backpack.

### Contributing

Contributions are welcome! If you find any bugs or have suggestions for improvements, please open an issue or submit a pull request on the [GitHub repository](https://github.com/kkboranbay/backpack-export).

### License

This package is licensed under the MIT License. See the [LICENSE](LICENSE.md) file for more details.

---

Feel free to adjust the details based on your specific package and its functionality.