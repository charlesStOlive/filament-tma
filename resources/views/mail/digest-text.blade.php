{!! $kicker !!}

{!! $heading !!}
@if ($todo !== [])

=== {!! mb_strtoupper(__('À faire')) !!} ===
@foreach ($todo as $group)

{!! $group['title'] !!}
@foreach ($group['items'] as $item)
- {!! $item['ref'] !!} — {!! $item['title'] !!} ({!! implode(', ', array_filter([$item['state'], $item['since'] ? __('depuis le :date', ['date' => $item['since']]) : null, $item['closes_on'] ? __('clos sans réponse le :date', ['date' => $item['closes_on']]) : null, $item['late'] ? __('en retard') : null])) !!})
@if ($item['url'])
  {!! $item['url'] !!}
@endif
@foreach ($item['lines'] as $line)
  · {!! $line['line'] !!}@if (filled($line['excerpt'])) « {!! $line['excerpt'] !!} »@endif

@endforeach
@endforeach
@endforeach
@endif
@if ($news !== [])

=== {!! mb_strtoupper(__('Nouveautés')) !!} ===
@foreach ($news as $group)
- {!! $group['ref'] !!} — {!! $group['title'] !!}
@if ($group['url'])
  {!! $group['url'] !!}
@endif
@foreach ($group['lines'] as $line)
  · {!! $line['line'] !!}@if (filled($line['excerpt'])) « {!! $line['excerpt'] !!} »@endif

@endforeach
@endforeach
@endif
@if ($copies !== [])

=== {!! mb_strtoupper(__('En copie')) !!} ===
{!! __('Pour information : là où vous êtes destinataire secondaire.') !!}
@foreach ($copies as $group)
- {!! $group['ref'] !!} — {!! $group['title'] !!}
@foreach ($group['lines'] as $line)
  · {!! $line['line'] !!}@if (filled($line['excerpt'])) « {!! $line['excerpt'] !!} »@endif

@endforeach
@endforeach
@endif

--
{!! $reason !!}
@if ($brand['preferences'])
{!! __('Régler mes notifications') !!} : {!! $brand['preferences'] !!}
@endif
{!! $brand['name'] !!}
