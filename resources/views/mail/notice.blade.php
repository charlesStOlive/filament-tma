{{--
    Ce qu'une personne apprend tout de suite, par courriel (`Notifications\NoticeNotification`). Un ticket qui l'attend
    s'ouvre sur « À faire » ; une tâche, sur « Pour information ».

    @var array $brand
    @var string $tone  `action`, `outcome`, `info`
    @var string $kind  `ticket`, `task`
    @var array $data  la notification (`Notice::$data`)
    @var string|null $closesOn  la date de clôture automatique, quand on attend une réponse
    @var string|null $url
    @var string $button
    @var array<string, string> $rows
--}}
@extends('filament-tma::mail.layout')

@section('content')
    @if ($tone === 'action')
        @include('filament-tma::mail.partials.box', [
            'label' => __('filament-tma::notifications.digest.todo'),
            'text' => $data['action'] ?? __('filament-tma::notifications.mail.waiting_action'),
            'note' => $closesOn ? __('filament-tma::notifications.mail.closing', ['date' => $closesOn]) : null,
        ])
    @elseif ($kind === 'task')
        @include('filament-tma::mail.partials.box', [
            'label' => __('filament-tma::notifications.mail.for_information'),
            'text' => __('filament-tma::notifications.weekly.no_action'),
        ])
    @endif

    <p style="margin: 0 0 14px 0; font-size: 15px; line-height: 23px; color: #1F2937;">{{ $data['line'] }}</p>

    @if (filled($data['message'] ?? null))
        @include('filament-tma::mail.partials.quote', ['text' => $data['message']])
    @elseif (filled($data['excerpt'] ?? null))
        @include('filament-tma::mail.partials.quote', ['text' => $data['excerpt']])
    @endif

    @if ($url)
        @include('filament-tma::mail.partials.button', ['url' => $url, 'label' => $button])
    @endif

    @include('filament-tma::mail.partials.details', ['rows' => $rows])
@endsection
