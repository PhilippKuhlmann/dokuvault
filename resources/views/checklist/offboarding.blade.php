<x-app-layout :$customer>
    <div class="p-3 sm:p-5 space-y-5">
        <div>
            <a href="{{ route('checklist.index', $customer) }}" class="text-sm text-cerulean-600 hover:underline dark:text-cerulean-400">← {{ __('Checklisten') }}</a>
            <div class="mt-1 text-3xl font-CoconPro text-gray-900 dark:text-gray-100">{{ __('Offboarding starten') }}</div>
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Die Liste entsteht aus der Vorlage und aus dem, was in der Doku zur Person steht: Gruppen und Postfach.') }}</div>
        </div>

        <x-panel class="max-w-2xl">
            @if ($adUsers->isEmpty())
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Für diesen Kunden ist kein aktiver AD-Benutzer dokumentiert.') }}</div>
            @else
                <form method="POST" action="{{ route('checklist.offboarding.store', $customer) }}" class="space-y-4">
                    @csrf
                    <div>
                        <x-input.label for="ad_user_id" :value="__('AD-Benutzer')" />
                        <x-input.select id="ad_user_id" name="ad_user_id" class="mt-1 block w-full" required>
                            <option value="">{{ __('Bitte wählen') }}</option>
                            @foreach ($adUsers as $adUser)
                                <option value="{{ $adUser->id }}" @selected(old('ad_user_id') == $adUser->id)>
                                    {{ trim($adUser->firstName.' '.$adUser->lastName) ?: $adUser->username }} ({{ $adUser->username }})
                                </option>
                            @endforeach
                        </x-input.select>
                        <x-input.fehler feld="ad_user_id" />
                    </div>
                    <div>
                        <x-input.label for="reference_date" :value="__('Letzter Arbeitstag')" />
                        <x-input.text id="reference_date" name="reference_date" type="date" feld="reference_date" class="mt-1 block w-full" :value="old('reference_date', now()->toDateString())" required />
                        <x-input.fehler feld="reference_date" />
                    </div>
                    <div>
                        <x-input.label for="note" :value="__('Notiz')" />
                        <textarea id="note" name="note" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 shadow-xs focus:border-cerulean-500 focus:ring-cerulean-500 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-100">{{ old('note') }}</textarea>
                    </div>
                    <div class="flex justify-end">
                        <x-input.button :label="__('Offboarding starten')" />
                    </div>
                </form>
            @endif
        </x-panel>
    </div>
</x-app-layout>
