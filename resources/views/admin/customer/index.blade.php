<x-admin-layout>

    <div class="flex w-full pl-3 pt-3 gap-3">
        <x-adminkachel :label="__('Kunden Gesamt')">
            {{ $customersCount }}
        </x-adminkachel>

    </div>



    <x-sitetopmenu />

<div class="m-3">
    <x-table.main>
        <x-table.head :labels="['Name', 'KD-Nr.', 'URL', 'Zugriff', '', ]" />

        <x-table.body>

            {{-- No pencil where the user's team only reads (CustomerAccess). --}}
            @foreach ($customers as $customer)

                <x-table.datarow
                    :values="[
                        $customer->name,
                        $customer->customer_number,
                        {{-- Unter "pfad", nicht unter "url": Damit steht der
                             kurze Weg da und nicht die volle Adresse - und die
                             Tabelle muss nicht raten, ob ein fuehrender
                             Schraegstrich einen Pfad meint. --}}
                        'pfad' => '/'.$customer->slug,
                        {{-- Teams: only their members see the customer. --}}
                        $customer->teams->isEmpty() ? __('alle Benutzer') : __('nur :teams', ['teams' => $customer->teams->pluck('name')->join(', ')]),
                    ]"

                    :editUrl="\App\Support\CustomerAccess::canWrite(auth()->user(), $customer->id) ? '/'.Request::path().'/'.$customer->id.'/edit' : null"
                    can="admin_customer"
                />

            @endforeach

        </x-table.body>
    </x-table.main>
    <div class="mt-5 mb-10">
        {{ $customers->links() }}
    </div>

</div>



</x-admin-layout>
