@php
    /** @var \Spatie\MediaLibrary\HasMedia $model */
    $model = $model ?? $getRecord();
    $files = $model->getMedia($collection ?? 'attachments');
@endphp

@if ($files->isNotEmpty())
    <ul class="fi-tma-attachments">
        @foreach ($files as $media)
            <li>
                <a href="{{ route('filament-tma.media', $media) }}" target="_blank" rel="noopener" class="fi-tma-attachment">
                    @if (str_starts_with((string) $media->mime_type, 'image/'))
                        <img src="{{ route('filament-tma.media', [$media, 'inline' => 1]) }}" alt="{{ $media->name }}" loading="lazy">
                    @else
                        <x-filament::icon icon="heroicon-m-paper-clip" class="fi-tma-attachment-icon" />
                    @endif
                    <span>{{ $media->file_name }}</span>
                    <small>{{ $media->human_readable_size }}</small>
                </a>
            </li>
        @endforeach
    </ul>
@endif
