<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\User;
use App\Notifications\ExpiryNotice;
use App\Support\ExpiringItems;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sends the expiry mail to everyone who has it switched on.
 *
 * Each item is mailed twice at most: when it enters the warning period and
 * when it has expired. expiry_notices remembers what was sent; a renewed
 * certificate has a new date and starts over.
 *
 * Items are recorded only after the mail went out. A broken mail server
 * therefore means "tomorrow again", not "never".
 */
class NotifyExpiry extends Command
{
    protected $signature = 'expiry:notify {--dry-run : Only show who would get how many entries}';

    protected $description = 'Mails expiring certificates, domains, licences and warranties to the users who asked for it';

    public function handle(): int
    {
        $kinds = Setting::expiryMailKinds();
        $failed = 0;

        $users = User::where('expiry_mail', true)
            ->whereNull('deactivated_at')
            ->whereNotNull('email')
            ->with('role.permissions')
            ->get();

        foreach ($users as $user) {
            $new = $this->newItems($user, $kinds);

            if ($new->isEmpty()) {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line($user->username.': '.$new->count());

                continue;
            }

            try {
                // The recipient's language - the scheduler has no request to take it from.
                $user->notify((new ExpiryNotice($new))->locale(array_key_exists((string) $user->locale, config('custom.locales', [])) ? $user->locale : Setting::sprache()));
            } catch (Throwable $e) {
                report($e);
                $this->error($user->username.': '.$e->getMessage());
                $failed++;

                continue;
            }

            $this->remember($user, $new);
            $this->info($user->username.': '.$new->count());
        }

        // A year after its date nothing will match a notice again - the item
        // was renewed (new date) or deleted.
        if (! $this->option('dry-run')) {
            DB::table('expiry_notices')->where('due_date', '<', now()->subYear()->toDateString())->delete();
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function newItems(User $user, array $kinds)
    {
        $items = ExpiringItems::forUser($user, $kinds);

        if ($items->isEmpty()) {
            return $items;
        }

        $sent = DB::table('expiry_notices')->where('user_id', $user->id)
            ->get(['subject_type', 'subject_id', 'stage', 'due_date'])
            ->map(fn ($n) => self::key($n->subject_type, $n->subject_id, $n->stage, substr($n->due_date, 0, 10)))
            ->flip();

        return $items->reject(fn ($i) => $sent->has(
            self::key($i['subject_type'], $i['subject_id'], $i['stage'], $i['date']->toDateString())
        ))->values();
    }

    private function remember(User $user, $items): void
    {
        DB::table('expiry_notices')->insertOrIgnore($items->map(fn ($i) => [
            'user_id' => $user->id,
            'subject_type' => $i['subject_type'],
            'subject_id' => $i['subject_id'],
            'stage' => $i['stage'],
            'due_date' => $i['date']->toDateString(),
            'created_at' => now(),
        ])->all());
    }

    private static function key(string $type, int $id, string $stage, string $date): string
    {
        return $type.'|'.$id.'|'.$stage.'|'.$date;
    }
}
