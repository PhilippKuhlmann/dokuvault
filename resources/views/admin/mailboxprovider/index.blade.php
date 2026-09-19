<x-admin-layout>

    <div class="flex w-full pl-3 pt-3 gap-3">
        <x-adminkachel :label="__('Mail Anbieter Gesamt')">
            {{ $mailboxprovidersCount }}
        </x-adminkachel>

    </div>



    <x-sitetopmenu />

<div class="m-3">
    <x-table.main>
        <x-table.head :labels="['Name', 'POP3', 'IMAP', 'SMTP', '', ]" />

        <x-table.body>

            @foreach ($mailboxproviders as $mailboxprovider)

                <x-table.datarow
                    :values="[
                        $mailboxprovider->name,
                        $mailboxprovider->pop3server . ':' . $mailboxprovider->pop3port,
                        $mailboxprovider->imapserver . ':' . $mailboxprovider->imapport,
                        $mailboxprovider->smtpserver . ':' . $mailboxprovider->smtpport,

                    ]"

                    editUrl="/{{ Request::path() }}/{{ $mailboxprovider->id }}/edit"
                    can="admin_catalog"
                />

            @endforeach

        </x-table.body>
    </x-table.main>
    <div class="mt-5 mb-10">
        {{ $mailboxproviders->links() }}
    </div>

</div>

</x-admin-layout>
