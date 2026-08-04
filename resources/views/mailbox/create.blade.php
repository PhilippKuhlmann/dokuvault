<x-app-layout :$customer>
    <x-create.main :header="__('Neues E-Mail Postfach')" action="{{ route('mailbox.store', $customer) }}">

        <x-create.singlerow :label="__('Name')" name="name" />

        <x-create.singlerow :label="__('E-Mail Adresse')" type="email" name="mailAdress" />

        <x-create.doublerow :label1="__('Benutzername')" name1="username" :label2="__('Passwort')" name2="password" />

        <div class="flex flex-col mt-2">
            <x-input.label for="mailbox_provider_id" :value="__('Anbieter')" />
            <x-input.select id="mailbox_provider_id" name="mailbox_provider_id">
                @foreach ($mailboxProviders as $mailboxprovider)
                    <option value="{{ $mailboxprovider->id }}">{{ $mailboxprovider->name }}</option>
                @endforeach
            </x-input.select>
        </div>

        <x-create.hidden />

    </x-create.main>
</x-app-layout>
