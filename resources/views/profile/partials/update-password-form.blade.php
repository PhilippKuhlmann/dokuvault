<section>
    <header>
        <h2 class="font-mono text-[11px] uppercase tracking-[0.12em] text-cerulean-600 dark:text-cerulean-400">
            {{ __('Passwort ändern') }}
        </h2>

    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('put')

        <div>
            <x-input.feldname for="current_password" :value="__('Aktuelles Passwort')" />
            <x-input.text id="current_password" name="current_password" type="password" class="mt-1.5 block w-full" autocomplete="current-password" />
            <x-input.error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input.feldname for="password" :value="__('Neues Passwort')" />
            <x-input.text id="password" name="password" type="password" class="mt-1.5 block w-full" autocomplete="new-password" />
            <x-kennwortregel />
            <x-input.error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input.feldname for="password_confirmation" :value="__('Passwort bestätigen')" />
            <x-input.text id="password_confirmation" name="password_confirmation" type="password" class="mt-1.5 block w-full" autocomplete="new-password" />
            <x-input.error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4">
            <x-input.button :label="__('Speichern')" />

            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Gespeichert!') }}</p>
            @endif
        </div>
    </form>
</section>
