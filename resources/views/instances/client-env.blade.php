{{-- La configuration d'une application cliente, produite par « Configuration de l'application » (InstanceResource). --}}
<div class="fi-tma-client-env" x-data="{ copied: null, copy(id) { navigator.clipboard.writeText(this.$refs[id].innerText); this.copied = id; setTimeout(() => this.copied = null, 1500) } }">
    <p>{{ __('Dans l’application cliente, lancez cette commande. Elle écrit sa configuration dans son .env, crée le compte du centre de service et lui transmet son jeton ; le centre de service vérifie aussitôt la connexion dans les deux sens.') }}</p>

    <div class="fi-tma-client-env-block">
        <pre x-ref="command">{{ $command }}</pre>
        <x-filament::link tag="button" size="sm" icon="heroicon-m-clipboard" x-on:click="copy('command')">
            <span x-text="copied === 'command' ? @js(__('Copié')) : @js(__('Copier'))"></span>
        </x-filament::link>
    </div>

    <p>{{ __('Si la transmission échoue, la commande affiche le jeton : collez-le alors dans la fiche, « Saisir le jeton à la main ».') }}</p>

    <details>
        <summary>{{ __('Ou, à la main, ces lignes dans son .env') }}</summary>
        <div class="fi-tma-client-env-block">
            <pre x-ref="env">{{ $env }}</pre>
            <x-filament::link tag="button" size="sm" icon="heroicon-m-clipboard" x-on:click="copy('env')">
                <span x-text="copied === 'env' ? @js(__('Copié')) : @js(__('Copier'))"></span>
            </x-filament::link>
        </div>
        <p>{{ __('Il faut alors créer vous-même, dans l’application, le compte :email et son jeton, puis coller ce jeton dans la fiche.', ['email' => \CharlesStOlive\FilamentTma\Filament\Resources\Instances\InstanceResource::MASTER_ACCOUNT]) }}</p>
    </details>

    <p><strong>{{ __('Cette commande contient un jeton qui ne sera plus affiché.') }}</strong></p>
</div>
