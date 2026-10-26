{{--
    Le bilan du matin (`Notifications\DailyDigest`) : « À faire », « Nouveautés », puis « En copie ».

    @var array $brand
    @var array $todo  [['title' => ..., 'items' => [...]]]
    @var string $todoText  ce qui attend la personne, en une phrase
    @var array $news
    @var array $copies
--}}
@extends('filament-tma::mail.layout')

@section('content')
    @if ($todo !== [])
        @include('filament-tma::mail.partials.box', [
            'label' => __('filament-tma::notifications.digest.todo'),
            'text' => $todoText,
        ])
        @foreach ($todo as $group)
            <p style="margin: 18px 0 10px 0; font-family: {!! $brand['font'] !!}; font-size: 13px; line-height: 18px; font-weight: bold; color: #374151;">{{ $group['title'] }}</p>
            @foreach ($group['items'] as $item)
                @include('filament-tma::mail.partials.group', [
                    'ref' => $item['ref'],
                    'title' => $item['title'],
                    'url' => $item['url'],
                    'late' => $item['late'],
                    'meta' => array_values(array_filter([
                        $item['state'],
                        $item['since'] ? __('filament-tma::notifications.digest.since', ['date' => $item['since']]) : null,
                        $item['closes_on'] ? __('filament-tma::notifications.digest.auto_closed', ['date' => $item['closes_on']]) : null,
                    ])),
                    'lines' => $item['lines'],
                ])
            @endforeach
        @endforeach
    @endif

    @if ($news !== [])
        @include('filament-tma::mail.partials.section', ['title' => __('filament-tma::notifications.digest.news'), 'hint' => __('filament-tma::notifications.digest.news_help')])
        @foreach ($news as $group)
            @include('filament-tma::mail.partials.group', $group)
        @endforeach
    @endif

    @if ($copies !== [])
        @include('filament-tma::mail.partials.section', ['title' => __('filament-tma::notifications.digest.copy'), 'hint' => __('filament-tma::notifications.digest.copy_help'), 'muted' => true])
        @foreach ($copies as $group)
            @include('filament-tma::mail.partials.group', [...$group, 'muted' => true])
        @endforeach
    @endif
@endsection
