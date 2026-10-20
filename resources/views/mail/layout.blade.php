{{--
    Le gabarit commun des courriels du module, pensé pour les messageries (Outlook compris) : des tableaux, des styles
    en ligne, 600 px de large au plus, le logo de l'application en tête, un filet de couleur (celle d'un ticket ou d'une
    tâche, `Support\MailBrand`) au-dessus de la carte, et le pied de page.

    @var array $brand  `MailBrand::for()`
    @var string $preheader  le texte d'aperçu des messageries (caché)
    @var string $kicker  la ligne au-dessus du titre (« Ticket DES-0001 · Support »)
    @var string $heading  le titre
    @var string|null $reason  pourquoi on reçoit ce courriel
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $heading }}</title>
    <!--[if mso]>
    <noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
    <style>table, td, a, p, span { font-family: Arial, sans-serif !important; }</style>
    <![endif]-->
    <style>
        @media only screen and (max-width: 620px) {
            .tma-card { padding: 22px 18px !important; }
            .tma-heading { font-size: 19px !important; }
        }
        a { color: {{ $brand['ink'] }}; }
    </style>
</head>
<body style="margin: 0; padding: 0; width: 100%; background-color: #F3F4F6; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;">
<div style="display: none; max-height: 0; max-width: 0; overflow: hidden; opacity: 0; mso-hide: all; font-size: 1px; line-height: 1px; color: #F3F4F6;">{{ $preheader }}&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#F3F4F6" style="background-color: #F3F4F6;">
    <tr>
        <td align="center" style="padding: 24px 12px;">
            <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" align="center"><tr><td><![endif]-->
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width: 600px; margin: 0 auto;">
                <tr>
                    <td align="left" style="padding: 0 4px 16px 4px; font-family: {!! $brand['font'] !!};">
                        @if ($brand['logo'])
                            <img src="{{ $brand['logo']['url'] }}" width="{{ $brand['logo']['width'] }}" height="{{ $brand['logo']['height'] }}" alt="{{ $brand['name'] }}" style="display: block; border: 0; outline: none; text-decoration: none; height: {{ $brand['logo']['height'] }}px; width: {{ $brand['logo']['width'] }}px;">
                        @else
                            <span style="font-size: 18px; font-weight: bold; color: #111827;">{{ $brand['name'] }}</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td bgcolor="{{ $brand['color'] }}" height="4" style="height: 4px; line-height: 4px; font-size: 4px; background-color: {{ $brand['color'] }}; border-radius: 6px 6px 0 0;">&nbsp;</td>
                </tr>
                <tr>
                    <td class="tma-card" bgcolor="#FFFFFF" style="background-color: #FFFFFF; padding: 28px 32px; border: 1px solid #E5E7EB; border-top: 0; border-radius: 0 0 6px 6px; font-family: {!! $brand['font'] !!}; color: #1F2937;">
                        <p style="margin: 0 0 6px 0; font-size: 12px; line-height: 18px; letter-spacing: 0.04em; text-transform: uppercase; font-weight: bold; color: {{ $brand['ink'] }};">{{ $kicker }}</p>
                        <h1 class="tma-heading" style="margin: 0 0 20px 0; font-size: 21px; line-height: 28px; font-weight: bold; color: #111827;">{{ $heading }}</h1>
                        @yield('content')
                    </td>
                </tr>
                <tr>
                    <td style="padding: 18px 8px 0 8px; font-family: {!! $brand['font'] !!}; font-size: 12px; line-height: 18px; color: #6B7280;">
                        @if (filled($reason ?? null))
                            <p style="margin: 0 0 6px 0;">{{ $reason }}</p>
                        @endif
                        @if ($brand['preferences'])
                            <p style="margin: 0 0 6px 0;"><a href="{{ $brand['preferences'] }}" style="color: #6B7280; text-decoration: underline;">{{ __('Régler mes notifications') }}</a></p>
                        @endif
                        <p style="margin: 0;">{{ $brand['name'] }}</p>
                    </td>
                </tr>
            </table>
            <!--[if mso]></td></tr></table><![endif]-->
        </td>
    </tr>
</table>
</body>
</html>
