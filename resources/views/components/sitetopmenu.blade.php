{{-- $neu = false: Die Liste bringt ihren eigenen Anlegen-Knopf mit (z. B. das
     VLAN-Modal), sonst staenden zwei nebeneinander.

     $titel: nur fuer Listen, die selbst Livewire sind. Der Titel unten wird
     sonst aus dem Routennamen abgeleitet - beim Rerender einer Livewire-
     Komponente heisst die laufende Route aber "livewire.update", und die
     Ueberschrift verschwand nach jeder Aktion. --}}
@props(['can' => null, 'neu' => true, 'titel' => null])

@php
    $routeName = \Illuminate\Support\Facades\Route::currentRouteName() ?? '';
    $segments = explode('.', $routeName);
    $isAdmin = ($segments[0] ?? null) === 'admin';
    $titleKey = $isAdmin ? implode('.', array_slice($segments, 0, 2)) : ($segments[0] ?? null);

    // admin_list_titles steht in config/custom.php, nicht mehr hier: Damit ist
    // es dieselbe Art Quelle wie list_titles unten, und der Uebersetzungs-Test
    // (LocaleTest) findet die Zeichenketten automatisch. Vorher gab dieser
    // Zweig seinen Wert roh zurueck, ohne __() - im Menue stand schon
    // "Rack catalogue", in der Ueberschrift daneben "Rack-Katalog".
    $title = $titel ?? ($isAdmin
        ? __(config('custom.admin_list_titles')[$titleKey] ?? '')
        : (__(config('custom.list_titles')[$titleKey] ?? config('custom.trashables')[$titleKey][1] ?? '')));
@endphp

<div class="flex flex-wrap w-full items-center justify-between gap-3 px-3 pt-4 pb-1">
    <div class="text-2xl font-CoconPro text-gray-900 dark:text-gray-100">
        {{ $title }}
    </div>

    <div class="flex items-center gap-3">
        @cannot('isCustomerR')
            @if ($neu && (! $can || auth()->user()->can($can)))
                <a href="/{{ Request::path() }}/create"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-cerulean-600 text-white text-sm font-DINPro-bold shadow-sm hover:bg-cerulean-700 focus:outline-none focus:ring-2 focus:ring-cerulean-500 focus:ring-offset-2 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    {{ __('Neu') }}
                </a>
            @endif
        @endcannot

        {{ $slot }}
    </div>
</div>
