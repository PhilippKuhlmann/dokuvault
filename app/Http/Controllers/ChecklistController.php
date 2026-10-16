<?php

namespace App\Http\Controllers;

use App\Models\Checklist;
use App\Models\Customer;
use App\Support\ChecklistAdScript;
use App\Support\ChecklistBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * On- and offboarding checklists of a customer.
 *
 * Three rights: checklist_manage (IT works through the lists), checklist_request
 * (the customer reports a new employee) and checklist_approve (the customer's
 * management approves sensitive drives). Each of them may see the lists.
 */
class ChecklistController extends Controller
{
    private const VIEW_RIGHTS = ['checklist_manage', 'checklist_request', 'checklist_approve'];

    public function index(Customer $customer)
    {
        abort_unless(Gate::any(self::VIEW_RIGHTS), 403);

        $open = $customer->checklists()->open()->withCount([
            'items', 'items as done_count' => fn ($query) => $query->whereNotNull('done_at'),
            'items as approval_count' => fn ($query) => $query->where('needs_approval', true)->whereNull('approved_at'),
        ])->orderBy('reference_date')->get();

        $completed = $customer->checklists()->completed()->latest('completed_at')->limit(50)->get();

        return view('checklist.index', compact('customer', 'open', 'completed'));
    }

    public function show(Customer $customer, Checklist $checklist)
    {
        abort_unless(Gate::any(self::VIEW_RIGHTS), 403);

        return view('checklist.show', compact('customer', 'checklist'));
    }

    /**
     * The PowerShell script for the AD part. Every download gets a new key;
     * the previous script can no longer report back.
     */
    public function adScript(Request $request, Customer $customer, Checklist $checklist)
    {
        Gate::authorize('checklist_manage');
        abort_unless(filled($checklist->username), 422, __('Für diese Checkliste ist kein Benutzername hinterlegt.'));

        $token = $checklist->issueScriptToken($request->user());
        $script = ChecklistAdScript::render($checklist->fresh(['items', 'customer']), $token);

        activity()
            ->event('checklist_script_download')
            ->performedOn($checklist)
            ->causedBy($request->user())
            ->withProperties(['objekt' => $checklist->fullName(), 'attributes' => ['IP' => $request->ip()]])
            ->log('AD-Skript heruntergeladen');

        return response($script, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.ChecklistAdScript::filename($checklist).'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function createOnboarding(Customer $customer)
    {
        abort_unless(Gate::any(['checklist_request', 'checklist_manage']), 403);

        return view('checklist.onboarding', compact('customer'));
    }

    public function createOffboarding(Customer $customer)
    {
        Gate::authorize('checklist_manage');

        $adUsers = $customer->adusers()->where('enabled', true)->orderBy('lastName')->orderBy('firstName')->orderBy('username')->get();

        return view('checklist.offboarding', compact('customer', 'adUsers'));
    }

    public function storeOffboarding(Request $request, Customer $customer, ChecklistBuilder $builder)
    {
        Gate::authorize('checklist_manage');

        $data = $request->validate([
            'ad_user_id' => ['required', 'integer'],
            'reference_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'ad_user_id' => __('AD-Benutzer'),
            'reference_date' => __('Letzter Arbeitstag'),
        ]);

        $adUser = $customer->adusers()->findOrFail($data['ad_user_id']);
        $checklist = $builder->startOffboarding($customer, $adUser, $data['reference_date'], $request->user(), $data['note'] ?? null);

        return redirect()->route('checklist.show', [$customer, $checklist])
            ->with('success', __('Offboarding gestartet.'));
    }

    public function settings(Customer $customer)
    {
        Gate::authorize('checklist_manage');

        return view('checklist.settings', compact('customer'));
    }

    public function destroy(Customer $customer, Checklist $checklist)
    {
        Gate::authorize('checklist_manage');

        $checklist->delete();

        return redirect()->route('checklist.index', $customer)->with('success', __('Checkliste gelöscht.'));
    }
}
