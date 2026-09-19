<x-admin-layout>

    <div class="flex w-full pl-3 pt-3 gap-3">
        <x-adminkachel :label="__('Kunden Gesamt')">
            {{ $customersCount }}
        </x-adminkachel>

    </div>



    <x-sitetopmenu />

<div class="m-3">
    <x-table.main>
        <x-table.head :labels="['Name', 'KD-Nr.', 'URL', '', ]" />

        <x-table.body>

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
                    ]"

                    editUrl="/{{ Request::path() }}/{{ $customer->id }}/edit"
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
