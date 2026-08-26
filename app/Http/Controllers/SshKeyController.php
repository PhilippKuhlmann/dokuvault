<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\SshKey;

/**
 * Nur die Liste: Anlegen, Bearbeiten und Loeschen laufen ueber das Modal
 * (App\Livewire\ObjektFormular), wie bei den uebrigen umgestellten Typen.
 */
class SshKeyController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', SshKey::class);

        return view('sshkey.index', compact('customer'));
    }
}
