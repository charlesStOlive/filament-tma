{!! $kicker !!}

{!! $heading !!}

@if ($tone === 'action')
{!! mb_strtoupper(__('À faire')) !!} : {!! $data['action'] ?? __('Ce ticket attend votre action.') !!}
@if ($closesOn)
{!! __('Sans réponse, le ticket sera clos automatiquement le :date.', ['date' => $closesOn]) !!}
@endif

@elseif ($kind === 'task')
{!! mb_strtoupper(__('Pour information')) !!} : {!! __('Aucune action n’est attendue de votre part.') !!}

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
{!! __('Régler mes notifications') !!} : {!! $brand['preferences'] !!}
@endif
{!! $brand['name'] !!}
