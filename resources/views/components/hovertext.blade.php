{{--
    Zeigt beim Ueberfahren einen kurzen Text ueber dem Element.

    Am Viewport statt am Element positioniert: Die Kacheln stehen in Karten mit
    Spaltensatz und in Bereichen mit eigenem Scrollrahmen - beides schneidet ein
    absolut positioniertes Fenster ab (overflow-x: auto macht auch overflow-y zu
    auto). position: fixed entkommt jedem Rahmen; dieselbe Loesung wie bei den
    Buchsen der Patchfelder.
--}}
@props(['text'])

@if (filled($text))
    {{-- $attributes durchreichen: Wird der Inhalt per x-show ausgeblendet, muss
         der Wrapper mitgehen, sonst bleibt eine Luecke in der Reihe stehen. --}}
    <span {{ $attributes->merge(['class' => 'inline-block']) }} x-data="{ offen: false, x: 0, y: 0 }"
        x-on:mouseenter="const r = $el.getBoundingClientRect(); x = r.left + r.width / 2; y = r.top; offen = true"
        x-on:mouseleave="offen = false"
        x-on:focusin="const r = $el.getBoundingClientRect(); x = r.left + r.width / 2; y = r.top; offen = true"
        x-on:focusout="offen = false">

        {{ $slot }}

        <span x-show="offen" x-cloak x-bind:style="`left: ${x}px; top: ${y - 8}px`"
            class="fixed z-50 w-56 -translate-x-1/2 -translate-y-full rounded border border-gray-200 bg-white p-2 text-left text-xs font-normal leading-snug text-gray-700 shadow-lg dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
            role="tooltip">{{ $text }}</span>
    </span>
@else
    {{ $slot }}
@endif
