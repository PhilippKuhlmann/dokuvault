<?php

namespace App\Http\Controllers;

use App\Http\Requests\MailboxRequest;
use App\Models\Customer;
use App\Models\Mailbox;
use App\Models\MailboxProvider;

class MailboxController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Mailbox::class);

        $customer->load('mailboxes.mailboxProvider');

        return view('mailbox.index', [
            'customer' => $customer,
        ]);
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', Mailbox::class);

        return view('mailbox.create', [
            'customer' => $customer,
            'mailboxProviders' => MailboxProvider::all(),
        ]);
    }

    public function store(Customer $customer, MailboxRequest $request)
    {
        $this->authorize('create', Mailbox::class);

        $customer->mailboxes()->create($request->validated());

        return redirect(route('mailbox.index', $customer));
    }

    public function edit(Customer $customer, Mailbox $mailbox)
    {
        $this->authorize('update', Mailbox::class);

        return view('mailbox.edit', [
            'customer' => $customer,
            'mailbox' => $mailbox,
            'mailboxProviders' => MailboxProvider::all(),
        ]);
    }

    public function update(Customer $customer, Mailbox $mailbox, MailboxRequest $request)
    {
        $this->authorize('update', Mailbox::class);

        $mailbox->update($request->validated());

        return redirect(route('mailbox.index', $customer));
    }

    public function destroy(Customer $customer, Mailbox $mailbox)
    {
        $this->authorize('delete', Mailbox::class);

        $mailbox->delete();

        return redirect(route('mailbox.index', $customer));
    }
}
