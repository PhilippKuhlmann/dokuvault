<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Konto Löschen') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Nach dem Löschen des Kontos hast du keinen Zugriff mehr auf deine Daten!') }}
        </p>
    </header>

    <x-input.button :label="__('Konto Löschen!')" color="red" x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')" />


    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6 dark:bg-gray-800">
            @csrf
            @method('delete')

            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Bist du sicher?') }}
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                {{ __('Nach dem Löschen des Kontos hast du keinen Zugriff mehr auf deine Daten!') }}
            </p>

            <div class="mt-6">
                <x-input.label for="password" value="{{ __('Password') }}" class="sr-only" />

                <x-input.text
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-3/4"
                    placeholder="{{ __('Password') }}"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end">
                <x-input.button :label="__('Abbrechen')" color="gray" type="button" x-on:click="$dispatch('close')" />

                <x-input.button :label="__('Konto Löschen!')" color="red" />
            </div>
        </form>
    </x-modal>
</section>
