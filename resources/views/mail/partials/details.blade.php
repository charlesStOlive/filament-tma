{{--
    Les repères d'un ticket ou d'une tâche, en petit : état, priorité, projet…

    @var array $brand
    @var array<string, string> $rows  libellé => valeur
--}}
@if ($rows !== [])
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top: 1px solid #E5E7EB;">
        @foreach ($rows as $label => $value)
            <tr>
                <td width="110" valign="top" style="padding: 8px 12px 0 0; font-family: {!! $brand['font'] !!}; font-size: 13px; line-height: 19px; color: #6B7280;">{{ $label }}</td>
                <td valign="top" style="padding: 8px 0 0 0; font-family: {!! $brand['font'] !!}; font-size: 13px; line-height: 19px; color: #111827;">{{ $value }}</td>
            </tr>
        @endforeach
    </table>
@endif
