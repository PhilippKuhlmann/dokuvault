{{--
    Sprachumschalter fuer die laufende Sitzung. Die dauerhafte Wahl steht im
    Profil; das hier greift auch auf Gastseiten und bei gesperrten Zugaengen.
--}}
@if (count(config('custom.locales', [])) > 1)
    <div class="flex items-center gap-1">
        @foreach (config('custom.locales') as $code => $bezeichnung)
            <form method="POST" action="{{ route('locale.update', $code) }}">
                @csrf
                {{-- Aktiv-Zustand in dieselbe Klassenliste, nicht als zweites
                     class-Attribut: Der Browser nimmt sonst nur das erste. --}}
                <button type="submit" title="{{ $bezeichnung }}"
                    {{ $attributes->merge(['class' => 'rounded-lg px-2 py-1 text-xs font-semibold uppercase focus:outline-none focus:ring-1 focus:ring-gray-200 dark:focus:ring-gray-700 '
                        .(app()->getLocale() === $code ? 'underline decoration-2 underline-offset-2' : 'opacity-60')]) }}>
                    {{ $code }}
                </button>
            </form>
        @endforeach
    </div>
@endif
