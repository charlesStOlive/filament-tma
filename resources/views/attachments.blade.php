@php
    /**
     * Les fichiers d'un ticket ou d'une entrée : une vignette pour une image, une icône pour un document. Un clic sur
     * une image l'ouvre dans la visionneuse (`lightbox.blade.php`), avec les autres images du bloc ; ouverte dans un
     * onglet (clic du milieu), elle s'affiche aussi, sans téléchargement. Un document se télécharge. Tout passe par la
     * route qui contrôle les droits.
     *
     * @var \Spatie\MediaLibrary\HasMedia $model
     * @var bool|null $large  des vignettes plus grandes (la grande fenêtre des notes)
     */
    $model = $model ?? $getRecord();
    $files = $model->getMedia($collection ?? 'attachments');
    $images = $files->filter(fn ($media): bool => str_starts_with((string) $media->mime_type, 'image/'))->values();
    $gallery = $images->map(fn ($media): array => [
        'src' => route('filament-tma.media', [$media, 'inline' => 1]),
        'download' => route('filament-tma.media', $media),
        'name' => $media->file_name,
    ])->all();
@endphp

@if ($files->isNotEmpty())
    <ul x-data @class(['fi-tma-attachments', 'fi-tma-attachments--large' => $large ?? false])>
        @foreach ($files as $media)
            @php($position = $images->search(fn ($image): bool => $image->is($media)))
            <li>
                @if ($position !== false)
                    <a
                        href="{{ $gallery[$position]['src'] }}"
                        target="_blank"
                        rel="noopener"
                        title="{{ __('filament-tma::attachments.view_image') }}"
                        class="fi-tma-attachment fi-tma-attachment--image"
                        x-on:click.prevent="$dispatch('tma-lightbox', { images: @js($gallery), index: {{ $position }} })"
                    >
                        <img src="{{ $gallery[$position]['src'] }}" alt="{{ $media->name }}" loading="lazy">
                @else
                    <a href="{{ route('filament-tma.media', $media) }}" target="_blank" rel="noopener" class="fi-tma-attachment">
                        <x-filament::icon :icon="str_contains((string) $media->mime_type, 'pdf') ? 'heroicon-o-document-text' : 'heroicon-o-paper-clip'" class="fi-tma-attachment-icon" />
                @endif
                        <span>{{ $media->file_name }}</span>
                        <small>{{ $media->human_readable_size }}</small>
                    </a>
            </li>
        @endforeach
    </ul>
@endif
