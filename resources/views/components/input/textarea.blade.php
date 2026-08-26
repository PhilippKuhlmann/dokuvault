@props(['rows' => 3])

{{-- Mehrzeilig fuer Werte, die keine Zeile sind: ein SSH-Schluessel steht in
     einem einzeiligen Feld als endloser Strich da, den man nicht pruefen kann.
     font-mono, weil ein falsches Zeichen sonst nicht auffaellt. --}}
<textarea rows="{{ $rows }}" spellcheck="false"
    {{ $attributes->merge(['class' => 'w-full min-w-0 resize-y font-mono text-xs leading-relaxed border-gray-300 dark:border-gray-700 focus:border-cerulean-500 focus:ring-cerulean-500 rounded-lg shadow-sm dark:bg-gray-700 dark:text-gray-100']) }}></textarea>
