<?php

namespace Kkboranbay\BackpackExport\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use avadim\FastExcelLaravel\Excel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
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
            $client = new HttpBrowser();
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

            foreach ($this->fetchData($client, $endpointToExport, $columns) as $rowData) {
                $sheet->writeRow($rowData, ['height' => 20]);
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

    protected function fetchData($client, $endpointToExport, $columns)
    {
        $start = 0;
        $limit = config('backpack.operations.backpack-export.limitPerRequest') ?? 500;

        $exceptions = config('backpack.operations.backpack-export.limitPerRequestExceptions') ?? [];

        if (isset($exceptions[$this->route])) {
            $limit = $exceptions[$this->route];
        }

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
