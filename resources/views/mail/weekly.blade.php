{{--
    Le bilan de la semaine des tâches projet (`Notifications\WeeklyDigest`) : pour information, par projet.

    @var array $brand
    @var array $projects  [['title' => ..., 'tasks' => [...]]]
    @var array $copies
    @var string $boxText  en tête : rien à faire, ou les questions qui attendent la personne
--}}
@extends('filament-tma::mail.layout')

@section('content')
    @include('filament-tma::mail.partials.box', [
        'label' => __('Pour information'),
        'text' => $boxText,
    ])

    @foreach ($projects as $project)
        @include('filament-tma::mail.partials.section', ['title' => $project['title']])
        @foreach ($project['tasks'] as $task)
            @include('filament-tma::mail.partials.group', [
                'ref' => $task['ref'],
                'title' => $task['title'],
                'url' => $task['url'],
                'meta' => collect($task['facts'])->map(fn ($value, $label) => $label.' : '.$value)->values()->all(),
                'lines' => $task['lines'],
            ])
        @endforeach
    @endforeach

    @if ($copies !== [])
        @include('filament-tma::mail.partials.section', ['title' => __('En copie'), 'hint' => __('Là où vous êtes destinataire secondaire.'), 'muted' => true])
        @foreach ($copies as $task)
            @include('filament-tma::mail.partials.group', [
                'ref' => $task['ref'],
                'title' => $task['title'],
                'url' => $task['url'],
                'meta' => collect($task['facts'])->map(fn ($value, $label) => $label.' : '.$value)->values()->all(),
                'lines' => $task['lines'],
                'muted' => true,
            ])
        @endforeach
    @endif
@endsection
