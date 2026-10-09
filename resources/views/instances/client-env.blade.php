{{-- La configuration d'une application cliente, produite par « Configuration de l'application » (InstanceResource). --}}
<div class="fi-tma-client-env" x-data="{ copied: null, copy(id) { navigator.clipboard.writeText(this.$refs[id].innerText); this.copied = id; setTimeout(() => this.copied = null, 1500) } }">
    <p>{{ __('Dans l’application cliente, lancez cette commande : elle écrit sa configuration dans son .env et crée le compte du centre de service.') }}</p>

    <div class="fi-tma-client-env-block">
        <pre x-ref="command">{{ $command }}</pre>
        <x-filament::link tag="button" size="sm" icon="heroicon-m-clipboard" x-on:click="copy('command')">
            <span x-text="copied === 'command' ? @js(__('Copié')) : @js(__('Copier'))"></span>
        </x-filament::link>
    </div>

    <p>{{ __('Elle affiche ensuite un jeton : collez-le ici, dans « Jeton émis par l’application », puis « Tester ».') }}</p>

    <details>
        <summary>{{ __('Ou, à la main, ces lignes dans son .env') }}</summary>
        <div class="fi-tma-client-env-block">
            <pre x-ref="env">{{ $env }}</pre>
            <x-filament::link tag="button" size="sm" icon="heroicon-m-clipboard" x-on:click="copy('env')">
                <span x-text="copied === 'env' ? @js(__('Copié')) : @js(__('Copier'))"></span>
            </x-filament::link>
        </div>
        <p>{{ __('Il faut alors créer vous-même, dans l’application, le compte :email et son jeton.', ['email' => \CharlesStOlive\FilamentTma\Filament\Resources\Instances\InstanceResource::MASTER_ACCOUNT]) }}</p>
    </details>

    <p><strong>{{ __('Ce jeton ne sera plus affiché.') }}</strong></p>
</div>
