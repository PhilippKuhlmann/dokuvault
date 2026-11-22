{{-- editAction statt editUrl: Dann oeffnet der Stift ein Livewire-Modal statt
     eine eigene Seite zu laden (siehe VLAN-Liste). --}}
@props(['editUrl' => null, 'can', 'editAction' => null])

{{--
    The head line of a card, three parts side by side (design 1A):

      name      the slot - name, operating system, badges - and below it the
                quiet "untertitel" line (model, form factor). Takes the
                room that is left; on wide screens it no longer wraps, a long
                operating system name is cut instead.
      kernwerte what one nearly always looks for (IP, place, services), each
                in a column of fixed width (x-kernwert breite): on every line
                of a list the same value stands in the same place.
      aktionen  remote access, copy password - and the pencil, always at
                the right edge.

    Below lg everything wraps: kernwerte under the name, the actions stay
    top right.
--}}
<div class="flex w-full flex-wrap items-center gap-x-6 gap-y-2 px-3 py-2 lg:flex-nowrap">
    <div class="min-w-0 flex-1 basis-64">
        {{-- Auf schmalen Bildschirmen kleiner: In text-2xl brach ein Name wie
             srv-hyperv-01.mustermann.local mitten durch. 20px statt 24px auch
             auf breiten Schirmen - in der zugeklappten Liste ist der Kopf
             alles, was man sieht, und er soll ruhig bleiben. min-w-0 laesst
             den Block schmaler werden als seinen Inhalt. --}}
        <div class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 wrap-break-word text-base leading-tight sm:gap-x-3 sm:text-xl lg:flex-nowrap dark:text-gray-100">
            {{ $slot }}
        </div>
        {{-- A second, quiet line under the name (model, form factor): in a
             folded card the head line is all there is to tell entries
             apart. --}}
        @isset($untertitel)
            {{-- Flex, not truncate on the line: a badge in it (EOL) must stay
                 visible - the text before it is cut instead (its own span). --}}
            <div class="mt-1 flex min-w-0 items-center gap-2 text-sm text-gray-500 dark:text-gray-400">{{ $untertitel }}</div>
        @endisset
    </div>
    @isset($kernwerte)
        <div class="flex shrink-0 flex-wrap items-center gap-x-6 gap-y-1.5 lg:flex-nowrap">
            {{ $kernwerte }}
        </div>
    @endisset
    {{-- Fixed width on wide screens, right-aligned: a line with remote
         access and password has more buttons than one with the pencil only,
         and the columns before it would otherwise shift. --}}
    <div class="ml-auto flex shrink-0 items-center justify-end gap-2 lg:w-32">
        @isset($aktionen)
            {{ $aktionen }}
        @endisset
        @can($can)
            @if ($editAction)
                <x-input.symbolknopf wire:click="{{ $editAction }}" :titel="__('Bearbeiten')">
                    <x-svg.edit class="h-5 w-5" />
                </x-input.symbolknopf>
            @else
                <x-input.symbolknopf :href="$editUrl" :titel="__('Bearbeiten')">
                    <x-svg.edit class="h-5 w-5" />
                </x-input.symbolknopf>
            @endif
        @endcan
    </div>
</div>
