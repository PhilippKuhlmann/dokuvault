<?php

namespace App\Http\Controllers;

use App\Http\Requests\MailboxProviderRequest;
use App\Models\MailboxProvider;
use App\Models\Setting;

class MailboxProviderController extends Controller
{
    public function index()
    {
        $mailboxproviders = MailboxProvider::paginate(Setting::seiteAdmin());
        $mailboxprovidersCount = MailboxProvider::all()->count();

        return view('admin.mailboxprovider.index', compact('mailboxproviders', 'mailboxprovidersCount'));
    }

    public function create()
    {
        return view('admin.mailboxprovider.create');
    }

    public function store(MailboxProviderRequest $request)
    {
        MailboxProvider::create($request->validated());

        return redirect(route('admin.mailboxprovider.index'));
    }

    public function edit(MailboxProvider $mailboxprovider)
    {
        return view('admin.mailboxprovider.edit', compact('mailboxprovider'));
    }

    public function update(MailboxProvider $mailboxprovider, MailboxProviderRequest $request)
    {
        $mailboxprovider->update($request->validated());

        return redirect(route('admin.mailboxprovider.index', $mailboxprovider));
    }
}
