@if ($crud->hasAccess('export'))
	<div class="btn-group">
		<a class="btn btn-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" tabindex="2">
			<i class="la la-download"></i> {{ trans('backpack::crud.export.export') }}
		</a>
		<ul class="dropdown-menu dropdown-menu-right">
			<li class="dropdown-header">Export as:</li>
			@foreach ($crud->get('export.exportTypes') as $key => $type)
                @php
					$queryString = http_build_query(request()->query());
					$exportUrl = url($crud->route.'/export').'?'.$queryString;
				@endphp
                <a class="dropdown-item" href="{{ $exportUrl }}">{{ $type }}</a>
			@endforeach
		</ul>
	</div>
@endif