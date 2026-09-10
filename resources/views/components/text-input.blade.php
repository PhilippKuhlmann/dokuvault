@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 focus:border-cerulean-500 focus:ring-cerulean-500 rounded-lg shadow-xs']) }}>
