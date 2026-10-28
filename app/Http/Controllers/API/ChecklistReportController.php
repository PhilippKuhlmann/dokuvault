<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ADGroup;
use App\Models\ADUser;
use App\Models\Checklist;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The AD script of a checklist reports what it did.
 *
 * Authenticated by the key in the downloaded script: one checklist, limited
 * time, only its hash is stored. The report may only tick items of that
 * checklist that carry an action and are not waiting for approval - a
 * script cannot complete anything else, even with a forged item list.
 *
 * The AD user is documented right away: name, email, groups and the
 * password (encrypted by the model). Its objectGUID becomes the agent
 * identifier, so the next run of the AD agent finds the same entry
 * instead of creating a second one.
 */
class ChecklistReportController extends Controller
{
    public function __invoke(Request $request)
    {
        $checklist = Checklist::forScriptToken($request->bearerToken());
        abort_unless($checklist, 401, 'Unbekannter, abgelaufener oder schon benutzter Schlüssel. Das Skript in DokuVault neu herunterladen.');

        $data = $request->validate([
            'identifier' => ['nullable', 'string', 'max:64'],
            'username' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'firstName' => ['nullable', 'string', 'max:100'],
            'lastName' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'enabled' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'max:255'],
            'groups' => ['nullable', 'array'],
            'groups.*' => ['string', 'max:255'],
            'items' => ['nullable', 'array'],
            'items.*' => ['integer'],
            'errors' => ['nullable', 'array'],
            'errors.*' => ['string', 'max:1000'],
        ]);

        // The key belongs to one checklist and so to one user. Without this,
        // a report could name any other AD user of the customer and overwrite
        // their documented password, email and groups.
        abort_unless($this->sameUsername($data['username'], $checklist->username), 422, 'Der Bericht gehört zu einem anderen Benutzer.');

        $done = DB::transaction(function () use ($checklist, $data) {
            $adUser = $this->documentUser($checklist, $data);

            $items = $checklist->items()->whereIn('id', $data['items'] ?? [])->whereNotNull('action')->whereNull('done_at')->get()
                ->reject->awaitsApproval();

            foreach ($items as $item) {
                $item->update(['done_at' => now(), 'done_by' => $checklist->script_requested_by]);
            }

            // One key, one report: afterwards the key is gone. It stayed valid
            // for 48 hours before, and every further report could rewrite the
            // linked AD user again. A rerun needs a freshly downloaded script.
            $checklist->update([
                'ad_user_id' => $adUser->id,
                'script_reported_at' => now(),
                'script_token_hash' => null,
                'script_token_expires_at' => null,
            ]);
            $checklist->refreshCompletion();

            return $items->count();
        });

        activity()
            ->event('checklist_script')
            ->performedOn($checklist)
            ->causedBy($checklist->script_requested_by ? User::find($checklist->script_requested_by) : null)
            ->withProperties([
                'objekt' => $checklist->fullName(),
                'attributes' => array_filter([
                    'Erledigt' => $done,
                    'Fehler' => implode(' | ', $data['errors'] ?? []) ?: null,
                    'IP' => request()->ip(),
                ]),
            ])
            ->log('AD-Skript ausgeführt');

        return response()->json(['ok' => true, 'done' => $done]);
    }

    /** Creates or updates the AD user in the documentation, with groups and password. */
    private function documentUser(Checklist $checklist, array $data): ADUser
    {
        $customerId = $checklist->customer_id;

        $adUser = ($checklist->ad_user_id ? ADUser::where('customer_id', $customerId)->find($checklist->ad_user_id) : null)
            // By objectGUID only if it is the same user - an identifier of
            // someone else must not redirect the write.
            ?? (filled($data['identifier'] ?? null)
                ? ADUser::where('customer_id', $customerId)->where('agent_identifier', $data['identifier'])->get()
                    ->first(fn (ADUser $found) => $this->sameUsername($found->username, $checklist->username))
                : null)
            ?? ADUser::where('customer_id', $customerId)->where('username', $data['username'])->first()
            ?? new ADUser(['customer_id' => $customerId]);

        $adUser->fill(array_filter([
            'username' => $data['username'],
            'firstName' => $data['firstName'] ?? null,
            'lastName' => $data['lastName'] ?? null,
            'email' => $data['email'] ?? null,
            'agent_identifier' => $data['identifier'] ?? null,
        ], fn ($value) => filled($value)));

        $adUser->enabled = $data['enabled'] ?? $checklist->isOnboarding();

        if (filled($data['password'] ?? null)) {
            $adUser->password = $data['password'];
        }

        $adUser->save();

        $groups = ADGroup::where('customer_id', $customerId)->whereIn('name', $data['groups'] ?? [])->pluck('id');
        $checklist->isOnboarding()
            ? $adUser->groups()->syncWithoutDetaching($groups)
            : $adUser->groups()->detach($groups);

        return $adUser;
    }

    /** SamAccountName comes back from AD in its own spelling. */
    private function sameUsername(?string $a, ?string $b): bool
    {
        return filled($a) && mb_strtolower($a) === mb_strtolower((string) $b);
    }
}
