{!! $kicker !!}

{!! $heading !!}

@if ($tone === 'action')
{!! mb_strtoupper(__('filament-tma::notifications.digest.todo')) !!} : {!! $data['action'] ?? __('filament-tma::notifications.mail.waiting_action') !!}
@if ($closesOn)
{!! __('filament-tma::notifications.mail.closing', ['date' => $closesOn]) !!}
@endif

@elseif ($kind === 'task')
{!! mb_strtoupper(__('filament-tma::notifications.mail.for_information')) !!} : {!! __('filament-tma::notifications.weekly.no_action') !!}

@endif
{!! $data['line'] !!}
@if (filled($data['message'] ?? null))

{!! $data['message'] !!}
@elseif (filled($data['excerpt'] ?? null))

« {!! $data['excerpt'] !!} »
@endif

@if ($url)
{!! $button !!} : {!! $url !!}

@endif
@foreach ($rows as $label => $value)
{!! $label !!} : {!! $value !!}
@endforeach

--
@if (filled($reason ?? null))
{!! $reason !!}
@endif
@if ($brand['preferences'])
{!! __('filament-tma::notifications.mail.settings') !!} : {!! $brand['preferences'] !!}
@endif
{!! $brand['name'] !!}
