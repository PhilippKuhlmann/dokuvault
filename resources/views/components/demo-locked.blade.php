{{-- A settings page in the public demo: shown, but nothing can be changed
     (BlockInDemo, LockedInDemo). Outside the demo just the content. --}}
@if (config('app.demo'))
    <div class="max-w-3xl rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/25 dark:text-amber-200">
        {{ __('In der Demo nur zum Ansehen – diese Einstellungen gelten für alle Besucher.') }}
    </div>
    <fieldset disabled {{ $attributes->merge(['class' => 'min-w-0']) }}>
        {{ $slot }}
    </fieldset>
@else
    {{ $slot }}
@endif
