<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Fortschritt eines Durchlaufs durch den Dokumentations-Assistenten (siehe
 * App\Livewire\DocumentationWizard). Kein Inventar-Objekt, daher keine
 * SoftDeletes und kein Eintrag in config('custom.trashables').
 */
class DocumentationRun extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'completed_steps' => 'array',
        'skipped_steps' => 'array',
        'created_records' => 'array',
        'completed_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Merkt sich eine angelegte ID unter dem jeweiligen Schritt-Schlüssel,
     * fuer die Abschlussuebersicht am Ende des Durchlaufs.
     */
    public function recordCreated(string $stepKey, int $id): void
    {
        $records = $this->created_records ?? [];
        $records[$stepKey] ??= [];
        $records[$stepKey][] = $id;

        $this->update(['created_records' => $records]);
    }

    /**
     * Erfasst und übersprungen schließen sich aus: Wird ein Schritt erfasst,
     * fällt er aus der Übersprungen-Liste (und umgekehrt in markStepSkipped).
     * Sonst könnte ein Schritt nach Zurückspringen in beiden Listen stehen und
     * die Abschluss-Zählung ihn doppelt zählen.
     */
    public function markStepCompleted(string $stepKey): void
    {
        $completed = $this->completed_steps ?? [];
        if (! in_array($stepKey, $completed, true)) {
            $completed[] = $stepKey;
        }

        $this->update([
            'completed_steps' => $completed,
            'skipped_steps' => array_values(array_diff($this->skipped_steps ?? [], [$stepKey])),
        ]);
    }

    public function markStepSkipped(string $stepKey): void
    {
        $skipped = $this->skipped_steps ?? [];
        if (! in_array($stepKey, $skipped, true)) {
            $skipped[] = $stepKey;
        }

        $this->update([
            'skipped_steps' => $skipped,
            'completed_steps' => array_values(array_diff($this->completed_steps ?? [], [$stepKey])),
        ]);
    }
}
