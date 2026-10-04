{{--
    Zeigt beim Ueberfahren einen kurzen Text ueber dem Element.

    Am Viewport statt am Element positioniert: Die Kacheln stehen in Karten mit
    Spaltensatz und in Bereichen mit eigenem Scrollrahmen - beides schneidet ein
    absolut positioniertes Fenster ab (overflow-x: auto macht auch overflow-y zu
    auto). position: fixed entkommt jedem Rahmen; dieselbe Loesung wie bei den
    Buchsen der Patchfelder.

    dunkel: short label in the look of the menu tooltips (dark, one line),
    e.g. date and result on the backup runs - the browser's own title
    tooltip took a second to appear and never came on touch screens.
--}}
@props(['text', 'dunkel' => false])

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
            @class([
                'fixed z-50 -translate-x-1/2 -translate-y-full',
                'w-56 rounded border border-gray-200 bg-white p-2 text-left text-xs font-normal leading-snug text-gray-700 shadow-lg dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200' => ! $dunkel,
                'whitespace-nowrap rounded-lg bg-gray-900 px-3 py-2 text-sm font-medium text-white shadow-xs dark:bg-gray-700' => $dunkel,
            ])
            role="tooltip">{{ $text }}</span>
    </span>
@else
    {{ $slot }}
@endif
