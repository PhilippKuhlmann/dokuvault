<section>
    <header>
        <h2 class="font-mono text-[11px] uppercase tracking-[0.12em] text-cerulean-600 dark:text-cerulean-400">
            {{ __('Angaben zum Zugang') }}
        </h2>

    </header>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input.feldname for="name" :value="__('Name')" />
            <x-input.text id="name" name="name" type="text" class="mt-1.5 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input.fehler class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input.feldname for="email" :value="__('E-Mail')" />
            <x-input.text id="email" name="email" type="email" class="mt-1.5 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input.fehler class="mt-2" :messages="$errors->get('email')" />
        </div>

        <div>
            <x-input.feldname for="locale" :value="__('Sprache')" />
            <x-input.select id="locale" name="locale" class="mt-1.5 block w-full">
                <option value="">{{ __('Automatisch (Browsersprache)') }}</option>
                @foreach (config('custom.locales') as $code => $bezeichnung)
                    <option value="{{ $code }}" @selected(old('locale', $user->locale) === $code)>{{ $bezeichnung }}</option>
                @endforeach
            </x-input.select>
            <x-input.fehler class="mt-2" :messages="$errors->get('locale')" />
        </div>

        <div class="flex items-center gap-4">
            <x-input.button :label="__('Speichern')" />

            @if (session('status') === 'profile-updated')
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
