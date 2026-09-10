<div class="min-h-screen flex flex-col justify-center items-center px-4 py-8 bg-linear-to-br from-chathams-blue-50 via-cerulean-50 to-hawkes-blue-100 dark:from-gray-900 dark:via-gray-900 dark:to-gray-800">
    <div class="mb-6 text-chathams-blue-800 dark:text-gray-300">
        {{ $logo }}
    </div>

    <div class="w-full sm:max-w-md px-6 py-6 bg-white dark:bg-gray-800 shadow-xs border border-gray-200 dark:border-gray-700 rounded-xl">
        {{ $slot }}
    </div>
</div>
