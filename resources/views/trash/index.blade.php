<x-app-layout :$customer>

    <div class="p-3 sm:p-5">
        <div class="text-2xl font-CoconPro text-gray-900 dark:text-gray-100 mb-4">Papierkorb</div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                <thead class="text-xs uppercase tracking-wide text-gray-500 bg-gray-50 border-b border-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600">
                    <tr>
                        <th class="py-2.5 px-4 font-semibold">Typ</th>
                        <th class="py-2.5 px-4 font-semibold">Name</th>
                        <th class="py-2.5 px-4 font-semibold">Gelöscht am</th>
                        <th class="py-2.5 px-4 font-semibold"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr class="bg-white border-b border-gray-100 last:border-0 hover:bg-gray-50 transition-colors dark:bg-gray-800 dark:border-gray-700 dark:hover:bg-gray-700/50">
                            <td class="py-2.5 px-4">{{ $item['label'] }}</td>
                            <td class="py-2.5 px-4 text-gray-900 dark:text-gray-100">{{ $item['name'] }}</td>
                            <td class="py-2.5 px-4 whitespace-nowrap">{{ $item['deleted_at']->format('d.m.Y H:i') }}</td>
                            <td class="py-2.5 px-4 text-right">
                                <form method="POST" action="{{ route('trash.restore', [$customer, $item['type'], $item['id']]) }}">
                                    @csrf
                                    <x-input.button type="submit" size="sm" label="Wiederherstellen" />
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-6 px-4 text-center text-gray-400">Der Papierkorb ist leer</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</x-app-layout>
