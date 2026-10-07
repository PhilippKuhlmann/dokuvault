@props(['feld', 'label', 'einheit', 'min', 'max', 'hinweis' => null])

{{-- One number setting that saves while typing (wire:model.live), as on
     Fristen and Sicherheit: label, field with unit, footnote, error. --}}
<div wire:key="zahl-{{ $feld }}">
    <x-input.label for="{{ $feld }}" :value="$label" />

    <div class="mt-1 flex items-center gap-2">
        <x-input.field id="{{ $feld }}" type="number" min="{{ $min }}" max="{{ $max }}"
            wire:model.live.debounce.600ms="{{ $feld }}" class="w-32" />
        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $einheit }}</span>
        <span wire:loading wire:target="{{ $feld }}" class="text-xs text-gray-400 dark:text-gray-500">{{ __('speichert …') }}</span>
    </div>

    @if ($hinweis)
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $hinweis }}</p>
    @endif

    <x-input.fehler :feld="$feld" />
</div>
