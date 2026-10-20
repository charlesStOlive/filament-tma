{{--
    Un bouton « à l'épreuve des messageries » : une cellule colorée et un lien remplissant la cellule.

    @var array $brand
    @var string $url
    @var string $label
--}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 4px 0 24px 0;">
    <tr>
        <td align="center" bgcolor="{{ $brand['color'] }}" style="background-color: {{ $brand['color'] }}; border-radius: 6px;">
            <a href="{{ $url }}" target="_blank" style="display: inline-block; padding: 12px 24px; font-family: {!! $brand['font'] !!}; font-size: 15px; line-height: 20px; font-weight: bold; color: {{ $brand['on'] }}; text-decoration: none; border-radius: 6px;">{{ $label }}</a>
        </td>
    </tr>
</table>
