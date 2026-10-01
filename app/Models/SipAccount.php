<?php

namespace App\Models;

use App\Models\Concerns\HasCredentials;
use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * A SIP account at a provider: where the phone numbers live.
 */
class SipAccount extends Model
{
    use HasCredentials;
    use HasFactory, SoftDeletes;
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function phoneSystem()
    {
        return $this->belongsTo(PhoneSystem::class);
    }

    public function internetConnection()
    {
        return $this->belongsTo(InternetConnection::class);
    }

    /** "SIP-Trunk (Anlagenanschluss)" instead of the raw key. */
    public function accountTypeLabel(): ?string
    {
        return config('custom.sip_account_types')[$this->account_type ?? ''] ?? null;
    }

    public function isTrunk(): bool
    {
        return $this->account_type === 'trunk';
    }

    /** Short form for headers: "040 123456-0 … -99" or the single numbers. */
    public function numbersSummary(): ?string
    {
        if ($this->isTrunk()) {
            return trim(($this->main_number ?? '').($this->number_range ? ' ('.$this->number_range.')' : '')) ?: null;
        }

        $nummern = $this->numberList();

        return $nummern->isEmpty() ? null : $nummern->implode(', ');
    }

    /**
     * The single numbers, one per line in the form, empty lines dropped.
     *
     * @return Collection<int, string>
     */
    public function numberList()
    {
        return collect(preg_split('/\R/', (string) $this->numbers))->map(fn ($n) => trim($n))->filter()->values();
    }

    public function phoneSystemLabel(): ?string
    {
        $anlage = $this->phoneSystem;

        return $anlage ? trim($anlage->manufacturer.' '.$anlage->model) ?: '#'.$anlage->id : null;
    }

    public function internetConnectionLabel(): ?string
    {
        $anschluss = $this->internetConnection;

        return $anschluss ? trim($anschluss->provider.' '.$anschluss->product) ?: '#'.$anschluss->id : null;
    }
}
