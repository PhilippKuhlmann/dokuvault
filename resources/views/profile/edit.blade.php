<x-empty-layout>

    {{--
        Das Profil im selben Blatt wie die Suchen: Millimeterpapier,
        Schriftkopf, scharfe Ecken.

        Vorher waren es vier lose Karten auf grauem Grund. Sie gehoeren
        zusammen - es ist eine Seite ueber einen Zugang, nicht vier Seiten -,
        deshalb jetzt ein Blatt mit vier Abschnitten und Trennlinie dazwischen.

        Der frueher hier stehende <x-slot name="header"> ist entfallen:
        layouts/empty rendert nur {{ $slot }}, einen header-Slot gibt es dort
        nicht. Der Block wurde stillschweigend verworfen.
    --}}
    <div class="relative min-h-[calc(100vh-4rem)] overflow-hidden px-4 py-12
                bg-linear-to-b from-chathams-blue-50 to-chathams-blue-100
                dark:from-gray-900 dark:to-cerulean-950">

        <div class="netzplan-raster pointer-events-none absolute inset-0 opacity-60
                    text-chathams-blue-100 dark:text-cerulean-950"></div>

        <div class="relative z-10 mx-auto w-full max-w-3xl rounded-lg border border-chathams-blue-200
                    bg-white shadow-xl dark:border-gray-700 dark:bg-gray-800">

            <div class="flex items-center justify-between gap-4 border-b border-chathams-blue-100 px-6 py-3
                        font-mono text-[11px] uppercase tracking-[0.15em] text-gray-500
                        dark:border-gray-700 dark:text-gray-400">
                <span>{{ __('Profil') }}</span>
                <span class="normal-case">{{ auth()->user()->username }}</span>
            </div>

            <div class="divide-y divide-chathams-blue-100 dark:divide-gray-700">
                <div class="px-6 py-8">
                    @include('profile.partials.update-profile-information-form')
                </div>

                <div class="px-6 py-8">
                    @include('profile.partials.update-password-form')
                </div>

                <div class="px-6 py-8">
                    @include('profile.partials.two-factor-form')
                </div>

                <div class="px-6 py-8">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>

</x-empty-layout>
