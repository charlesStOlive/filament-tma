{{--
    Un encadré en tête de courriel : « À faire » (un ticket attend la personne) ou « Pour information » (une tâche).

    @var array $brand
    @var string $label
    @var string $text
    @var string|null $note  une ligne de plus, plus petite (la date de clôture automatique)
--}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 22px 0;">
    <tr>
        <td bgcolor="{{ $brand['tint'] }}" style="background-color: {{ $brand['tint'] }}; border-left: 4px solid {{ $brand['color'] }}; padding: 14px 16px; font-family: {!! $brand['font'] !!};">
            <p style="margin: 0 0 4px 0; font-size: 12px; line-height: 16px; letter-spacing: 0.06em; text-transform: uppercase; font-weight: bold; color: {{ $brand['ink'] }};">{{ $label }}</p>
            <p style="margin: 0; font-size: 16px; line-height: 23px; font-weight: bold; color: #111827;">{{ $text }}</p>
            @if (filled($note ?? null))
                <p style="margin: 6px 0 0 0; font-size: 13px; line-height: 19px; color: #374151;">{{ $note }}</p>
            @endif
        </td>
    </tr>
</table>
