{{--
    Le titre d'une partie d'un bilan (« À faire », « Nouveautés », « En copie »), et sa phrase d'explication.

    @var array $brand
    @var string $title
    @var string|null $hint
    @var bool $muted
--}}
<p style="margin: 26px 0 {{ filled($hint ?? null) ? '2px' : '12px' }} 0; padding-top: 14px; border-top: 1px solid #E5E7EB; font-family: {!! $brand['font'] !!}; font-size: 13px; line-height: 18px; letter-spacing: 0.06em; text-transform: uppercase; font-weight: bold; color: {{ ($muted ?? false) ? '#6B7280' : $brand['ink'] }};">{{ $title }}</p>
@if (filled($hint ?? null))
    <p style="margin: 0 0 12px 0; font-family: {!! $brand['font'] !!}; font-size: 13px; line-height: 19px; color: #6B7280;">{{ $hint }}</p>
@endif
