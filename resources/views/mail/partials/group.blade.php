{{--
    Un ticket ou une tâche dans un bilan : son numéro et son titre (un lien), une ligne de repères, puis ce qui s'y est
    passé. En copie, le tout est plus discret.

    @var array $brand
    @var string $ref
    @var string $title
    @var string|null $url
    @var array<int, string> $meta  les repères (état, depuis, échéance…)
    @var bool $late  en retard
    @var array<int, array{line: string, excerpt: ?string}> $lines
    @var bool $muted
--}}
@php($muted = $muted ?? false)
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 12px 0;">
    <tr>
        <td style="border-left: 3px solid {{ $muted ? '#E5E7EB' : $brand['color'] }}; padding: 2px 0 2px 12px; font-family: {!! $brand['font'] !!};">
            <p style="margin: 0; font-size: 15px; line-height: 22px; font-weight: bold;">
                @if ($url)
                    <a href="{{ $url }}" target="_blank" style="color: {{ $muted ? '#374151' : '#111827' }}; text-decoration: none;">{{ $ref }} — {{ $title }}</a>
                @else
                    <span style="color: {{ $muted ? '#374151' : '#111827' }};">{{ $ref }} — {{ $title }}</span>
                @endif
            </p>
            @if (($meta ?? []) !== [] || ($late ?? false))
                <p style="margin: 2px 0 0 0; font-size: 13px; line-height: 19px; color: #6B7280;">
                    {{ implode(' · ', $meta ?? []) }}@if ($late ?? false)@if (($meta ?? []) !== []) · @endif<span style="color: #B91C1C; font-weight: bold;">{{ __('filament-tma::notifications.digest.overdue') }}</span>@endif
                </p>
            @endif
            @foreach ($lines ?? [] as $line)
                <p style="margin: 6px 0 0 0; font-size: 14px; line-height: 21px; color: {{ $muted ? '#6B7280' : '#374151' }};">
                    {{ $line['line'] }}@if (filled($line['excerpt'])) <span style="color: #6B7280;">« {{ $line['excerpt'] }} »</span>@endif
                </p>
            @endforeach
        </td>
    </tr>
</table>
