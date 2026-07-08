<div class="mx-auto max-w-3xl">
    <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Profil') }}</h1>
        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{{ __('Gérez votre profil, votre compte et vos préférences.') }}</p>
    </div>

    <div
        x-data="{
            tab: 'profil',
            activeBtn: null,
            move(btn) {
                if (! btn) return;
                this.activeBtn = btn;
                this.$refs.marker.style.width = btn.offsetWidth + 'px';
                this.$refs.marker.style.left = btn.offsetLeft + 'px';
            },
            pick(name, btn) { this.tab = name; this.move(btn); }
        }"
        x-init="$nextTick(() => move($refs.tabs.firstElementChild))"
        @resize.window="move(activeBtn)"
    >
        {{-- Tab bar with sliding marker --}}
        <div class="overflow-x-auto pb-1">
            <div x-ref="tabs" class="relative inline-flex h-10 items-center gap-1 rounded-lg bg-neutral-100 p-1 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400">
                @foreach (['profil' => __('Profil'), 'compte' => __('Compte'), 'prefs' => __('Préférences'), 'api' => __('API & MCP')] as $key => $label)
                    <button type="button" @click="pick('{{ $key }}', $el)"
                            :class="tab === '{{ $key }}' ? 'text-neutral-900 dark:text-white' : 'hover:text-neutral-700 dark:hover:text-neutral-200'"
                            class="relative z-10 inline-flex h-8 items-center whitespace-nowrap rounded-md px-3 text-sm font-medium transition-colors">{{ $label }}</button>
                @endforeach
                <div x-ref="marker" x-cloak class="absolute inset-y-1 left-0 z-0 rounded-md bg-white shadow-sm transition-all duration-300 ease-out dark:bg-neutral-700"></div>
            </div>
        </div>

        {{-- ================= Tab: Profil (auto-save) ================= --}}
        <div x-show="tab === 'profil'" class="mt-4">
            <section class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold">{{ __('Informations publiques') }}</h2>
                    <span class="inline-flex items-center gap-1 text-xs text-neutral-400">
                        <span wire:loading.delay wire:target="autosaveProfile">{{ __('Enregistrement…') }}</span>
                        <span wire:loading.remove wire:target="autosaveProfile">{{ __('Enregistré automatiquement') }}</span>
                    </span>
                </div>

                {{-- Avatar --}}
                <div class="mt-4 flex items-center gap-4">
                    @if ($avatar && str_starts_with((string) $avatar->getMimeType(), 'image/'))
                        <img src="{{ $avatar->temporaryUrl() }}" alt="" class="h-20 w-20 shrink-0 rounded-full object-cover">
                    @else
                        <x-user-avatar :user="auth()->user()" size="xl" :hover-card="false" />
                    @endif
                    <div class="flex flex-col items-start gap-1.5">
                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-neutral-300 px-3 py-1.5 text-sm hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800">
                            <x-phosphor-upload-simple class="h-4 w-4"/>
                            <span>{{ __("Changer l'avatar") }}</span>
                            <input type="file" wire:model="avatar" accept="image/*" class="hidden">
                        </label>
                        <p wire:loading wire:target="avatar" class="text-xs text-neutral-500">{{ __('Téléversement…') }}</p>
                        @error('avatar') <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        @if (auth()->user()->avatar_path)
                            <button type="button" wire:click="removeAvatar" class="text-xs text-red-600 hover:underline dark:text-red-400">{{ __("Retirer l'avatar") }}</button>
                        @endif
                    </div>
                </div>

                <div class="mt-6 space-y-4">
                    <div>
                        <label for="name" class="mb-1 block text-sm font-medium">{{ __('Nom') }}</label>
                        <input id="name" type="text" wire:model.live.debounce.700ms="name" class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                        @error('name') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div x-data="{ len: @js(mb_strlen($biography)) }">
                        <label for="biography" class="mb-1 block text-sm font-medium">{{ __('Biographie') }} <span class="font-normal text-neutral-400">{{ __('(optionnel)') }}</span></label>
                        <textarea id="biography" rows="3" maxlength="500" x-on:input="len = $el.value.length"
                                  wire:model.live.debounce.700ms="biography"
                                  placeholder="{{ __('Quelques mots sur vous — affichés au survol de votre avatar.') }}"
                                  class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800"></textarea>
                        <div class="mt-1 flex items-center justify-between">
                            @error('biography') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @else <span></span> @enderror
                            <span class="text-xs text-neutral-400"><span x-text="len"></span>/500</span>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        {{-- ================= Tab: Compte (explicit buttons) ================= --}}
        <div x-show="tab === 'compte'" x-cloak class="mt-4 space-y-6">
            {{-- Email --}}
            <section class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <h2 class="text-base font-semibold">{{ __('Adresse e-mail') }}</h2>

                @if (session('profile-status'))
                    <div class="mt-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-500/10 dark:text-green-400">
                        {{ session('profile-status') }}
                    </div>
                @endif

                <form wire:submit="updateProfileInformation" class="mt-4 space-y-4">
                    <div>
                        <label for="email" class="mb-1 block text-sm font-medium">{{ __('Adresse e-mail') }}</label>
                        <input id="email" type="email" wire:model="email" class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                        @error('email') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                        @if (! auth()->user()->hasVerifiedEmail())
                            <p class="mt-2 text-sm text-amber-600 dark:text-amber-400">
                                {{ __("Votre adresse e-mail n'est pas vérifiée.") }}
                            </p>
                        @endif
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none">
                            {{ __('Mettre à jour') }}
                        </button>
                        <span wire:loading wire:target="updateProfileInformation" class="text-sm text-neutral-500">{{ __('Enregistrement…') }}</span>
                    </div>
                </form>
            </section>

            {{-- Password --}}
            <section class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <h2 class="text-base font-semibold">{{ __('Mot de passe') }}</h2>

                @if (session('password-status'))
                    <div class="mt-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-500/10 dark:text-green-400">
                        {{ session('password-status') }}
                    </div>
                @endif

                <form wire:submit="updatePassword" class="mt-4 space-y-4">
                    <div>
                        <label for="current_password" class="mb-1 block text-sm font-medium">{{ __('Mot de passe actuel') }}</label>
                        <input id="current_password" type="password" wire:model="current_password" autocomplete="current-password" class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                        @error('current_password') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="new_password" class="mb-1 block text-sm font-medium">{{ __('Nouveau mot de passe') }}</label>
                        <input id="new_password" type="password" wire:model="password" autocomplete="new-password" class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                        @error('password') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="mb-1 block text-sm font-medium">{{ __('Confirmer le nouveau mot de passe') }}</label>
                        <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none">
                            {{ __('Mettre à jour') }}
                        </button>
                        <span wire:loading wire:target="updatePassword" class="text-sm text-neutral-500">{{ __('Mise à jour…') }}</span>
                    </div>
                </form>
            </section>
        </div>

        {{-- ================= Tab: Préférences (auto-save) ================= --}}
        <div x-show="tab === 'prefs'" x-cloak class="mt-4 space-y-6">
            {{-- Language --}}
            <section class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <h2 class="text-base font-semibold">{{ __('Langue') }}</h2>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach (['fr' => 'Français', 'en' => 'English', 'es' => 'Español'] as $code => $name)
                        <button
                            type="button"
                            wire:click="updateLocale('{{ $code }}')"
                            class="rounded-lg border px-3 py-1.5 text-sm font-medium transition {{ $locale === $code ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300' : 'border-neutral-300 hover:bg-neutral-100 dark:border-neutral-700 dark:hover:bg-neutral-800' }}"
                        >
                            {{ $name }}
                        </button>
                    @endforeach
                </div>
            </section>

            {{-- Notifications --}}
            <section class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <h2 class="text-base font-semibold">{{ __('Notifications') }}</h2>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{{ __('Choisissez comment et quand être notifié.') }}</p>

                @php
                    $notifChannels = [
                        'inapp' => [__('Notifications dans l\'app'), __('La cloche en haut à droite.')],
                        'email' => [__('Notifications par email'), __('Recevoir aussi un email.')],
                    ];
                    $notifEvents = [
                        'comments' => [__('Commentaires'), __('Sur les cartes dont vous êtes membre.')],
                        'mentions' => [__('Mentions'), __('Quand on vous @mentionne.')],
                        'reactions' => [__('Réactions'), __('Quand on réagit à vos commentaires.')],
                        'assignments' => [__('Assignations'), __('Quand on vous assigne à une carte.')],
                        'mentions_only' => [__('Commentaires : mentions uniquement'), __('Ne notifier les commentaires que si vous êtes mentionné.')],
                    ];
                @endphp

                <div class="mt-4 space-y-4">
                    <div>
                        <p class="mb-1 text-xs font-medium uppercase tracking-wide text-neutral-400">{{ __('Canaux') }}</p>
                        <div class="divide-y divide-neutral-100 dark:divide-neutral-800">
                            @foreach ($notifChannels as $key => [$label, $desc])
                                @include('livewire.partials.notification-toggle', ['key' => $key, 'label' => $label, 'desc' => $desc])
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <p class="mb-1 text-xs font-medium uppercase tracking-wide text-neutral-400">{{ __('Événements') }}</p>
                        <div class="divide-y divide-neutral-100 dark:divide-neutral-800">
                            @foreach ($notifEvents as $key => [$label, $desc])
                                @include('livewire.partials.notification-toggle', ['key' => $key, 'label' => $label, 'desc' => $desc])
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>
        </div>

        {{-- ================= Tab: API & MCP ================= --}}
        <div x-show="tab === 'api'" x-cloak class="mt-4 space-y-6">
            {{-- API tokens (Sanctum) --}}
            <section class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <h2 class="text-base font-semibold">{{ __("Jetons d'API") }}</h2>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Créez des jetons pour accéder à l'API REST (<code class="text-xs">/api/v1</code>) via l'en-tête <code class="text-xs">Authorization: Bearer &lt;token&gt;</code>.</p>

                @if ($newToken)
                    <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-3 dark:border-amber-500/40 dark:bg-amber-500/10">
                        <p class="text-xs font-medium text-amber-700 dark:text-amber-400">{{ __('Copiez ce jeton maintenant — il ne sera plus affiché.') }}</p>
                        <div class="mt-2 flex items-center gap-2" x-data="{ copied: false }">
                            <input type="text" readonly value="{{ $newToken }}" @focus="$el.select()" class="flex-1 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 font-mono text-xs dark:border-neutral-700 dark:bg-neutral-800">
                            <button type="button" @click="navigator.clipboard?.writeText('{{ $newToken }}'); window.toast('{{ __('Copié !') }}', { type: 'success' }); copied = true; setTimeout(() => copied = false, 1500)" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500"><span x-text="copied ? '{{ __('Copié !') }}' : '{{ __('Copier') }}'"></span></button>
                        </div>
                    </div>
                @endif

                <form wire:submit="createToken" class="mt-4 flex items-end gap-2">
                    <div class="flex-1">
                        <label for="token_name" class="mb-1 block text-sm font-medium">{{ __('Nom du jeton') }}</label>
                        <input id="token_name" type="text" wire:model="tokenName" placeholder="{{ __('Ex : Script de synchro') }}" class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 focus:outline-none dark:border-neutral-700 dark:bg-neutral-800">
                        @error('tokenName') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">{{ __('Créer') }}</button>
                </form>

                <div class="mt-4 space-y-2">
                    @forelse ($tokens as $token)
                        <div wire:key="token-{{ $token->id }}" class="flex items-center justify-between gap-2 rounded-lg border border-neutral-200 px-3 py-2 text-sm dark:border-neutral-700">
                            <div class="min-w-0">
                                <p class="truncate font-medium">{{ $token->name }}</p>
                                <p class="text-xs text-neutral-400">Créé {{ $token->created_at->diffForHumans() }}@if ($token->last_used_at) · utilisé {{ $token->last_used_at->diffForHumans() }}@endif</p>
                            </div>
                            <button type="button" wire:click="revokeToken({{ $token->id }})" class="shrink-0 text-xs text-neutral-400 hover:text-red-500">{{ __('Révoquer') }}</button>
                        </div>
                    @empty
                        <p class="text-sm text-neutral-400">{{ __("Aucun jeton d'API.") }}</p>
                    @endforelse
                </div>
            </section>

            {{-- MCP (Model Context Protocol) --}}
            <section class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="flex items-center gap-2 text-base font-semibold"><x-phosphor-robot class="h-5 w-5" /> {{ __('Connexion IA (MCP)') }}</h2>
                        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{{ __("Branchez un assistant IA (Claude, Codex, Cursor…) sur vos boards via le protocole MCP. Les actions de l'IA respectent vos droits et apparaissent en temps réel dans l'activité.") }}</p>
                    </div>
                    @can('admin')
                        <button type="button" role="switch" aria-label="{{ __('Activer le MCP pour l\'instance') }}" :aria-checked="@js($mcpEnabled)" wire:click="toggleMcp" class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 items-center rounded-full transition {{ $mcpEnabled ? 'bg-indigo-600' : 'bg-neutral-300 dark:bg-neutral-700' }}">
                            <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition {{ $mcpEnabled ? 'translate-x-5' : 'translate-x-0.5' }}"></span>
                        </button>
                    @endcan
                </div>

                @unless ($mcpEnabled)
                    <p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">
                        Le MCP est actuellement <strong>désactivé</strong> pour l'instance. @can('admin') Activez-le avec l'interrupteur ci-dessus. @else Un administrateur doit l'activer. @endcan
                    </p>
                @else
                    @php
                        $tok = $newToken ?? '<VOTRE_TOKEN>';
                        $configs = [
                            'Claude Code (.mcp.json)' => '{
  "mcpServers": {
    "board": {
      "type": "http",
      "url": "'.$mcpEndpoint.'",
      "headers": { "Authorization": "Bearer '.$tok.'" }
    }
  }
}',
                            'Claude Desktop (claude_desktop_config.json)' => '{
  "mcpServers": {
    "board": {
      "command": "npx",
      "args": ["-y", "mcp-remote", "'.$mcpEndpoint.'", "--header", "Authorization: Bearer '.$tok.'"]
    }
  }
}',
                            'Codex (~/.codex/config.toml)' => '[mcp_servers.board]
command = "npx"
args = ["-y", "mcp-remote", "'.$mcpEndpoint.'", "--header", "Authorization: Bearer '.$tok.'"]',
                            'Cursor (.cursor/mcp.json)' => '{
  "mcpServers": {
    "board": {
      "url": "'.$mcpEndpoint.'",
      "headers": { "Authorization": "Bearer '.$tok.'" }
    }
  }
}',
                        ];
                    @endphp

                    <div class="mt-4 space-y-3">
                        <p class="text-sm text-neutral-600 dark:text-neutral-300">Endpoint : <code class="rounded bg-neutral-100 px-1.5 py-0.5 text-xs dark:bg-neutral-800">{{ $mcpEndpoint }}</code>@unless ($newToken) — créez d'abord un jeton d'API ci-dessus et remplacez <code class="text-xs">&lt;VOTRE_TOKEN&gt;</code>.@endunless</p>

                        @foreach ($configs as $label => $snippet)
                            <div x-data="{ copied: false }">
                                <div class="mb-1 flex items-center justify-between">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ $label }}</span>
                                    <button type="button" @click="navigator.clipboard?.writeText($refs.code.textContent); window.toast('{{ __('Copié !') }}', { type: 'success' }); copied = true; setTimeout(() => copied = false, 1500)" class="text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-400"><span x-text="copied ? '{{ __('Copié !') }}' : '{{ __('Copier') }}'"></span></button>
                                </div>
                                <pre x-ref="code" class="overflow-x-auto rounded-lg bg-neutral-900 p-3 font-mono text-xs text-neutral-100 dark:bg-neutral-950">{{ $snippet }}</pre>
                            </div>
                        @endforeach
                    </div>
                @endunless
            </section>
        </div>
    </div>
</div>
