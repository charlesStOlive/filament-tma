{!! $kicker !!}

{!! $heading !!}

{!! mb_strtoupper(__('Pour information')) !!} : {!! __('Aucune action n’est attendue de votre part.') !!}
@foreach ($projects as $project)

=== {!! $project['title'] !!} ===
@foreach ($project['tasks'] as $task)
- {!! $task['ref'] !!} — {!! $task['title'] !!} ({!! collect($task['facts'])->map(fn ($value, $label) => $label.' : '.$value)->implode(', ') !!})
@if ($task['url'])
  {!! $task['url'] !!}
@endif
@foreach ($task['lines'] as $line)
  · {!! $line['line'] !!}@if (filled($line['excerpt'])) « {!! $line['excerpt'] !!} »@endif

@endforeach
@endforeach
@endforeach
@if ($copies !== [])

=== {!! mb_strtoupper(__('En copie')) !!} ===
@foreach ($copies as $task)
- {!! $task['ref'] !!} — {!! $task['title'] !!}
@foreach ($task['lines'] as $line)
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
