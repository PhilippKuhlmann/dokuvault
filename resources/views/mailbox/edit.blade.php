<x-app-layout :$customer>
    <x-create.main :header="__('E-Mail Postfach bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('mailbox.update', [$customer, $mailbox]) }}">
        @method('PATCH')

        <x-create.singlerow :label="__('Name')" name="name" :default="$mailbox->name" />

        <x-create.singlerow :label="__('E-Mail Adresse')" name="mailAdress" type="email" :default="$mailbox->mailAdress" />

        <x-create.doublerow :label1="__('Benutzername')" name1="username" :default1="$mailbox->username" :label2="__('Passwort')" name2="password" :default2="$mailbox->password" />

        <div class="flex flex-col mt-2">
            <x-input.label for="mailbox_provider_id" :value="__('Anbieter')" />
            <x-input.select id="mailbox_provider_id" name="mailbox_provider_id">
                @foreach ($mailboxProviders as $mailboxprovider)
                    <option {{ $mailboxprovider->id == $mailbox->mailboxProvider?->id ? 'selected' : '' }} value="{{ $mailboxprovider->id }}">{{ $mailboxprovider->name }}</option>
                @endforeach
            </x-input.select>
        </div>

        <x-edit.hidden hidden="{{ $mailbox->hidden }}" />

    </x-create.main>

    @can('mailbox_delete')
        <x-deletecard action="{{ route('mailbox.destroy', [$customer, $mailbox]) }}" />
    @endcan

</x-app-layout>
