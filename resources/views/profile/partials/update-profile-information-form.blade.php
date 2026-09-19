<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Profile Information') }}
        </h2>

    </header>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input.label for="name" :value="__('Name')" class="text-gray-900" />
            <x-input.text id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input.label for="email" :value="__('Email')" class="text-gray-900" />
            <x-input.text id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />
        </div>

        <div>
            <x-input.label for="locale" :value="__('Sprache')" class="text-gray-900" />
            <x-input.select id="locale" name="locale" class="mt-1 block w-full">
                <option value="">{{ __('Automatisch (Browsersprache)') }}</option>
                @foreach (config('custom.locales') as $code => $bezeichnung)
                    <option value="{{ $code }}" @selected(old('locale', $user->locale) === $code)>{{ $bezeichnung }}</option>
                @endforeach
            </x-input.select>
            <x-input-error class="mt-2" :messages="$errors->get('locale')" />
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
