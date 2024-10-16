<?php

namespace Kkboranbay\BackpackExport\Traits;

use Illuminate\Support\Facades\Route;
use Kkboranbay\BackpackExport\Jobs\ExportJob;

trait ExportOperation
{
    protected function setupExportRoutes($segment, $routeName, $controller)
    {
        Route::get($segment.'/export', [
            'as'        => $routeName.'.export',
            'uses'      => $controller.'@export',
            'operation' => 'export',
        ]);
    }

    protected function setupExportDefaults()
    {
        $this->crud->operation(['list', 'export'], function () {
            $this->crud->loadDefaultOperationSettingsFromConfig();
            $this->crud->set('export.exportTypes', $this->getExportTypes());
            $this->crud->addButton('top', 'export', 'view', 'backpack-export::buttons.export', 'end');
        });
    }

    public function getExportTypes(): array
    {
        return [
            'excel' => 'Excel',
        ];
    }

    public function getExportFilename(): string
    {
        $title = $this->crud->route;
        return 'Export-'.ucfirst($title).'-'.date('Y-m-d H:i:s');
    }

    public function export()
    {
        $this->crud->hasAccessOrFail('export');

        $route = $this->crud->route;
        $filters = request()->getQueryString();

        $authUser = backpack_auth()->user();
        ExportJob::dispatch($authUser, $route, $filters, $this->getExportFilename())
            ->onConnection(config('backpack.operations.backpack-export.queueConnection'))
            ->onQueue(config('backpack.operations.backpack-export.onQueue'));;

        \Alert::success(trans('backpack-export::export.processing'))->flash();

        return redirect()->back();
    }
}