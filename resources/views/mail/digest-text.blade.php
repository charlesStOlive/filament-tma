{!! $kicker !!}

{!! $heading !!}
@if ($todo !== [])

=== {!! mb_strtoupper(__('filament-tma::notifications.digest.todo')) !!} ===
@foreach ($todo as $group)

{!! $group['title'] !!}
@foreach ($group['items'] as $item)
- {!! $item['ref'] !!} — {!! $item['title'] !!} ({!! implode(', ', array_filter([$item['state'], $item['since'] ? __('filament-tma::notifications.digest.since', ['date' => $item['since']]) : null, $item['closes_on'] ? __('filament-tma::notifications.digest.auto_closed', ['date' => $item['closes_on']]) : null, $item['late'] ? __('filament-tma::notifications.digest.overdue') : null])) !!})
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

=== {!! mb_strtoupper(__('filament-tma::notifications.digest.news')) !!} ===
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

=== {!! mb_strtoupper(__('filament-tma::notifications.digest.copy')) !!} ===
{!! __('filament-tma::notifications.digest.copy_help') !!}
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
{!! __('filament-tma::notifications.mail.settings') !!} : {!! $brand['preferences'] !!}
@endif
{!! $brand['name'] !!}
