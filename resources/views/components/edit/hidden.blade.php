@props(['hidden'])

@can('see_hidden')
    <div class="flex items-center mt-2">
        <input {{ $hidden ? 'checked' : '' }} id="checkbox_hidden" type="checkbox" name="hidden" value="1"
            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
        <label for="checkbox_hidden" class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-300">{{ __('Verstecken') }}</label>
    </div>
@endcan
