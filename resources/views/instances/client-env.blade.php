{{-- La configuration d'une application cliente, produite par « Configuration de l'application » (InstanceResource). --}}
<div class="fi-tma-client-env" x-data="{ copied: null, copy(id) { navigator.clipboard.writeText(this.$refs[id].innerText); this.copied = id; setTimeout(() => this.copied = null, 1500) } }">
    <p>{{ __('filament-tma::instances.env.intro') }}</p>

    <div class="fi-tma-client-env-block">
        <pre x-ref="command">{{ $command }}</pre>
        <x-filament::link tag="button" size="sm" icon="heroicon-m-clipboard" x-on:click="copy('command')">
            <span x-text="copied === 'command' ? @js(__('filament-tma::instances.env.copied')) : @js(__('filament-tma::instances.env.copy'))"></span>
        </x-filament::link>
    </div>

    <p>{{ __('filament-tma::instances.env.fallback') }}</p>

    <details>
        <summary>{{ __('filament-tma::instances.env.manual') }}</summary>
        <div class="fi-tma-client-env-block">
            <pre x-ref="env">{{ $env }}</pre>
            <x-filament::link tag="button" size="sm" icon="heroicon-m-clipboard" x-on:click="copy('env')">
                <span x-text="copied === 'env' ? @js(__('filament-tma::instances.env.copied')) : @js(__('filament-tma::instances.env.copy'))"></span>
            </x-filament::link>
        </div>
        <p>{{ __('filament-tma::instances.env.manual_account', ['email' => \CharlesStOlive\FilamentTma\Filament\Resources\Instances\InstanceResource::MASTER_ACCOUNT]) }}</p>
    </details>

    <p><strong>{{ __('filament-tma::instances.env.secret_warning') }}</strong></p>
</div>
