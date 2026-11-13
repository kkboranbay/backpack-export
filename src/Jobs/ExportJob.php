<?php

namespace Kkboranbay\BackpackExport\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use avadim\FastExcelLaravel\Excel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Kkboranbay\BackpackExport\Mail\SendEmail;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\HttpClient;

class ExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        public $authUser,
        public string $route,
        public ?string $filters = '',
        public string $fileName,
    ) {}

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $timeout = config('backpack.operations.backpack-export.http-timeout') ?? 180;
            $client = new HttpBrowser(HttpClient::create([
                'timeout' => $timeout,
            ]));
            $host = rtrim(env('APP_URL'), '/');
            $loginURL = "$host/login";

            $authUser = $this->authUser;
            $email = $authUser->email;
            $realPassword = $authUser->password;
            $fakePasswordStr = 'password';
            $fakePasswordHash = Hash::make($fakePasswordStr);

            $authUser->password = $fakePasswordHash;

            $urlToExport = "$host/$this->route?$this->filters";
            $endpointToExport = "$host/$this->route/search?$this->filters";

            Log::info('BackpackExport: ', [
                'email' => $email,
                'route' => $this->route,
                'filters' => $this->filters,
                'fileName' => $this->fileName,
                'urlToExport' => $urlToExport,
                'endpointToExport' => $endpointToExport,
            ]);

            Model::withoutEvents(function () use ($authUser) {
                $authUser->save();
            });

            $crawler = $client->request('GET', $loginURL);
            $token = $crawler->filter('input[name="_token"]')->attr('value');

            $client->request('POST', $loginURL, [
                '_token' => $token,
                'email' => $email,
                'password' => $fakePasswordStr
            ]);

            $authUser->password = $realPassword;
            Model::withoutEvents(function () use ($authUser) {
                $authUser->save();
            });

            $crawler = $client->request('GET', $urlToExport, ['_token' => $token]);
            $columns = $crawler->filter('th[data-column-name]')->each(fn($node) => $node->text());
            Log::info('BackpackExport: ', [
                'columns' => $columns,
            ]);
            
            $excel = Excel::create(['Sheet1']);
            $sheet = $excel->sheet();
            $sheet->writeRow($columns);

            $dateFormats = $this->dateFormats();
            $notDateColumns = [];
            $dateColumnWidths = [];

            foreach ($this->fetchData($client, $endpointToExport, $columns) as $rowData) {
                $cellStyles = null;
                if ($dateFormats) {
                    [$rowData, $cellStyles] = $this->convertDates($rowData, $dateFormats, $notDateColumns);
                    foreach ($cellStyles ?? [] as $index => $style) {
                        $dateColumnWidths[$index] = max($dateColumnWidths[$index] ?? 0, mb_strlen($style['format']) + 2);
                    }
                }
                $sheet->writeRow($rowData, ['height' => 20], $cellStyles);
            }

            // Widen date columns, otherwise Excel shows "#####" for dates that do not fit.
            // Set in column order: the writer keeps insertion order and Excel requires sorted <col> elements.
            ksort($dateColumnWidths);
            foreach ($dateColumnWidths as $index => $width) {
                $sheet->setColWidth($index + 1, $width, true);
            }
            $filePath = "public/$this->fileName.xlsx";
            $excel->saveTo('app/'.$filePath);
            Log::info('BackpackExport Save File: ', [
                'email' => $email,
                'route' => $this->route,
                'filePath' => $filePath,
            ]);

            Bus::chain([
                fn() => Mail::to($email)->send(new SendEmail('app/'.$filePath)),
                fn() => Storage::exists($filePath) ? Storage::delete($filePath) : null,
            ])
            ->onConnection(config('backpack.operations.backpack-export.queueConnection'))
            ->onQueue(config('backpack.operations.backpack-export.onQueue'))
            ->dispatch();
        } catch (\Throwable $exception) {
            Log::error('Backpack Export Error: ', [
                'route' => $this->route,
                'email' => $this->authUser->email,
                'error' => $exception->getMessage() 
            ]);
            throw $exception;
        }
    }

    /**
     * Input formats (Carbon isoFormat) to recognize, mapped to the Excel format to write them with.
     * Empty when date conversion is disabled.
     */
    protected function dateFormats(): array
    {
        $config = config('backpack.operations.backpack-export.convert-dates') ?? [];
        if (empty($config['enabled'])) {
            return [];
        }

        $datetimeFormats = $config['input-datetime-formats'] ?? config('backpack.base.default_datetime_format');
        $dateFormats = $config['input-date-formats'] ?? config('backpack.base.default_date_format');

        $formats = [];
        foreach ((array) $datetimeFormats as $format) {
            $formats[] = ['input' => $format, 'dateOnly' => false, 'excel' => $config['excel-datetime-format'] ?? 'dd.mm.yyyy hh:mm'];
        }
        foreach ((array) $dateFormats as $format) {
            $formats[] = ['input' => $format, 'dateOnly' => true, 'excel' => $config['excel-date-format'] ?? 'dd.mm.yyyy'];
        }

        return array_filter($formats, fn($format) => !empty($format['input']));
    }

    /**
     * Replaces date-looking cells with 'Y-m-d H:i:s' values and returns per-cell Excel formats for them.
     * A column with a value that is not a date is not checked again, to keep large exports fast.
     */
    protected function convertDates(array $row, array $formats, array &$notDateColumns): array
    {
        $locale = config('backpack.operations.backpack-export.convert-dates.locale') ?? app()->getLocale();
        $cellStyles = [];

        foreach ($row as $index => $value) {
            if (isset($notDateColumns[$index]) || !is_string($value) || !preg_match('/\d/', $value)) {
                continue;
            }

            $converted = $this->parseDate($value, $formats, $locale);
            if ($converted === null) {
                $notDateColumns[$index] = true;
                continue;
            }

            $row[$index] = $converted['value'];
            $cellStyles[$index] = ['format' => $converted['excel']];
        }

        return [$row, $cellStyles ?: null];
    }

    protected function parseDate(string $value, array $formats, string $locale): ?array
    {
        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromLocaleIsoFormat($format['input'], $locale, $value);
            } catch (\Throwable) {
                continue;
            }

            // Reject overflowed dates like "31 фев" and partial matches.
            if ($date->locale($locale)->isoFormat($format['input']) !== $value) {
                continue;
            }

            return [
                'value' => $format['dateOnly'] ? $date->format('Y-m-d') : $date->format('Y-m-d H:i:s'),
                'excel' => $format['excel'],
            ];
        }

        return null;
    }

    protected function fetchData($client, $endpointToExport, $columns)
    {
        $start = 0;
        $limit = config('backpack.operations.backpack-export.limitPerRequest') ?? 500;

        $exceptions = config('backpack.operations.backpack-export.limitPerRequestExceptions') ?? [];

        if (isset($exceptions[$this->route])) {
            $limit = $exceptions[$this->route];
        }

        Log::info("Backpack Export: Limit = $limit");

        while (true) {
            $client->jsonRequest('POST', $endpointToExport, [
                'start' => $start,
                'length' => $limit,
                config('backpack.operations.backpack-export.disableCSRFhash') => true
            ]);

            $response = $client->getResponse();
            $content = json_decode($response->getContent(), true);

            if (empty($content['data'])) break;

            foreach ($content['data'] as $item) {
                $diffInCounts = count($item) - count($columns);
                if ($diffInCounts > 1) {
                    Log::error('Columns and items not matched!', [
                        'route' => $this->route,
                        'email' => $this->authUser->email,
                    ]);
                }

                if ($diffInCounts == 1) array_pop($item);

                $data = [];
                foreach ($item as $value) {
                    $crawler = new Crawler($value);
                    $text = $crawler->filter('span')->last()->count();
                    $data[] = ($text > 0)
                        ?  $crawler->filter('span')->last()->text()
                        : '';
                }
                yield $data;
            }
            $start += $limit;
        }
    }
}
