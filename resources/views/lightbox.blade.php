{{--
    La visionneuse des images d'un ticket ou d'une tâche (pièces jointes de la demande, des messages et des notes) : un
    clic sur une vignette l'ouvre par-dessus la page, sans téléchargement. Une seule par page, en fin de <body>
    (`FilamentTmaPlugin`) ; les vignettes l'ouvrent par l'événement `tma-lightbox` ({ images: [{ src, download,
    name }], index }) — voir `attachments.blade.php`.

    Flèches pour passer d'une image à l'autre, clic sur l'image pour la voir en taille réelle, plein écran, ouvrir dans
    un onglet, télécharger. Échap la ferme sans fermer la fenêtre d'une note ouverte dessous : la touche est prise
    avant elle (écoute en phase de capture).
--}}
<div
    x-data="{
        open: false,
        images: [],
        index: 0,
        zoomed: false,
        get current() { return this.images[this.index] ?? {} },
        show(detail) {
            this.images = detail.images ?? []
            this.index = detail.index ?? 0
            this.zoomed = false
            this.open = this.images.length > 0
            this.$nextTick(() => this.$refs.dialog.focus())
        },
        close() {
            this.open = false
            this.zoomed = false
            if (document.fullscreenElement) document.exitFullscreen()
        },
        go(step) {
            if (this.images.length < 2) return
            this.index = (this.index + step + this.images.length) % this.images.length
            this.zoomed = false
        },
        fullscreen() {
            document.fullscreenElement ? document.exitFullscreen() : this.$refs.dialog.requestFullscreen?.()
        },
    }"
    x-init="window.addEventListener('keydown', (event) => {
        if (! $data.open) return
        if (event.key === 'Escape') { event.preventDefault(); event.stopImmediatePropagation(); $data.close() }
        if (event.key === 'ArrowRight') { event.preventDefault(); $data.go(1) }
        if (event.key === 'ArrowLeft') { event.preventDefault(); $data.go(-1) }
    }, true)"
    x-on:tma-lightbox.window="show($event.detail)"
>
    <div
        x-show="open"
        x-cloak
        x-transition.opacity.duration.150ms
        x-ref="dialog"
        tabindex="-1"
        role="dialog"
        aria-modal="true"
        x-bind:aria-label="current.name"
        class="fi-tma-lightbox"
    >
        <div class="fi-tma-lightbox-bar">
            <span class="fi-tma-lightbox-name" x-text="current.name"></span>
            <span class="fi-tma-lightbox-count" x-show="images.length > 1" x-text="(index + 1) + ' / ' + images.length"></span>

            <div class="fi-tma-lightbox-tools">
                <button type="button" x-on:click="zoomed = ! zoomed" x-bind:title="zoomed ? @js(__('filament-tma::attachments.lightbox.fit')) : @js(__('filament-tma::attachments.lightbox.actual_size'))" x-bind:aria-label="zoomed ? @js(__('filament-tma::attachments.lightbox.fit')) : @js(__('filament-tma::attachments.lightbox.actual_size'))">
                    <x-filament::icon icon="heroicon-m-magnifying-glass-plus" x-show="! zoomed" />
                    <x-filament::icon icon="heroicon-m-magnifying-glass-minus" x-show="zoomed" x-cloak />
                </button>
                <button type="button" x-on:click="fullscreen()" title="{{ __('filament-tma::attachments.lightbox.fullscreen') }}" aria-label="{{ __('filament-tma::attachments.lightbox.fullscreen') }}">
                    <x-filament::icon icon="heroicon-m-arrows-pointing-out" />
                </button>
                <a x-bind:href="current.src" target="_blank" rel="noopener" title="{{ __('filament-tma::attachments.lightbox.open_tab') }}" aria-label="{{ __('filament-tma::attachments.lightbox.open_tab') }}">
                    <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" />
                </a>
                <a x-bind:href="current.download" title="{{ __('filament-tma::attachments.lightbox.download') }}" aria-label="{{ __('filament-tma::attachments.lightbox.download') }}">
                    <x-filament::icon icon="heroicon-m-arrow-down-tray" />
                </a>
                <button type="button" x-on:click="close()" title="{{ __('filament-tma::attachments.lightbox.close') }}" aria-label="{{ __('filament-tma::common.close') }}">
                    <x-filament::icon icon="heroicon-m-x-mark" />
                </button>
            </div>
        </div>

        <div class="fi-tma-lightbox-stage" x-bind:class="{ 'is-zoomed': zoomed }" x-on:click.self="close()">
            <img x-bind:src="current.src" x-bind:alt="current.name" x-on:click="zoomed = ! zoomed">
        </div>

        <button type="button" class="fi-tma-lightbox-nav fi-tma-lightbox-prev" x-show="images.length > 1" x-on:click="go(-1)" title="{{ __('filament-tma::attachments.lightbox.previous') }}" aria-label="{{ __('filament-tma::attachments.lightbox.previous') }}">
            <x-filament::icon icon="heroicon-m-chevron-left" />
        </button>
        <button type="button" class="fi-tma-lightbox-nav fi-tma-lightbox-next" x-show="images.length > 1" x-on:click="go(1)" title="{{ __('filament-tma::attachments.lightbox.next') }}" aria-label="{{ __('filament-tma::attachments.lightbox.next') }}">
            <x-filament::icon icon="heroicon-m-chevron-right" />
        </button>
    </div>
</div>
