{{--
    Ce qui a été écrit (un message, une question, un motif), cité.

    @var array $brand
    @var string $text
--}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 22px 0;">
    <tr>
        <td style="border-left: 3px solid #D1D5DB; padding: 2px 0 2px 14px; font-family: {!! $brand['font'] !!}; font-size: 15px; line-height: 23px; color: #374151;">{!! nl2br(e($text)) !!}</td>
    </tr>
</table>
