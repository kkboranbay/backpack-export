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
                <a class="dropdown-item backpack-export-link" data-export-url="{{ $exportUrl }}" href="{{ $exportUrl }}">{{ $type }}</a>
			@endforeach
		</ul>
	</div>
@endif

@push('after_scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const updateExportUrls = () => {
            const queryString = new URLSearchParams(window.location.search).toString();

			document.querySelectorAll('.backpack-export-link').forEach(link => {
                const baseUrl = link.dataset.exportUrl.split('?')[0];
                link.href = `${baseUrl}?${queryString}`;
            });
        };

        updateExportUrls();

        let currentUrl = window.location.href;
        setInterval(() => {
            const newUrl = window.location.href;
            if (newUrl !== currentUrl) {
                currentUrl = newUrl;
                updateExportUrls();
            }
        }, 300);
    });
</script>
@endpush