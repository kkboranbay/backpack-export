<?php

namespace Kkboranbay\BackpackExport\Jobs;

use App\Mail\ExportedDataMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use avadim\FastExcelLaravel\Excel;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Kkboranbay\BackpackExport\Mail\SendEmail;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\BrowserKit\HttpBrowser;

class ExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
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
            $client = new HttpBrowser();
            $host = rtrim(env('APP_URL'), '/');
            $loginURL = "$host/login";
            $email = config('backpack.operations.backpack-export.login');
            $password = config('backpack.operations.backpack-export.password');

            $urlToExport = "$host/$this->route?$this->filters";
            $endpointToExport = "$host/$this->route/search?$this->filters";

            $crawler = $client->request('GET', $loginURL);
            $token = $crawler->filter('input[name="_token"]')->attr('value');

            $client->request('POST', $loginURL, [
                '_token' => $token,
                'email' => $email,
                'password' => $password
            ]);

            $crawler = $client->request('GET', $urlToExport, ['_token' => $token]);
            $columns = $crawler->filter('th[data-column-name]')->each(fn($node) => $node->text());

            $excel = Excel::create(['Sheet1']);
            $sheet = $excel->sheet();
            $sheet->writeRow($columns);

            foreach ($this->fetchData($client, $endpointToExport, $columns) as $rowData) {
                $sheet->writeRow($rowData, ['height' => 20]);
            }
            $filePath = storage_path("app/public/$this->fileName.xlsx");
            $excel->saveTo($filePath);

            Bus::chain([
                fn() => Mail::to($email)->send(new SendEmail($filePath)),
                fn() => Storage::exists($filePath) ? Storage::delete($filePath) : null,
            ])
            ->dispatch()
            ->onConnection(config('backpack.operations.backpack-export.queueConnection'))
            ->onQueue(config('backpack.operations.backpack-export.onQueue'));
        } catch (\Throwable $exception) {
            Log::error('Error: Export from Admin Panel', [
                'route' => $this->route,
                'email' => $email,
                'error' => $exception->getMessage() 
            ]);
            throw $exception;
        }
    }

    protected function fetchData($client, $endpointToExport, $columns)
    {
        $start = 0;
        $limit = config('backpack.operations.backpack-export.limitPerRequest') ?? 1000;

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
                        'email' => config('backpack.operations.backpack-export.login')
                    ]);
                }

                if ($diffInCounts == 1) array_pop($item);

                $data = [];
                foreach ($item as $value) {
                    $crawler = new Crawler($value);
                    $text = $crawler->filter('span')->last()->count();
                    $data[] = ($text > 0)
                        ?  $crawler->filter('span')->last()->text()
                        : 'ERROR! Something went wrong.';
                }
                yield $data;
            }
            $start += $limit;
        }
    }
}
