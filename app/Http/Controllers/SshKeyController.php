<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\SshKey;

/**
 * The list only: creating, editing and deleting run through the modal
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
