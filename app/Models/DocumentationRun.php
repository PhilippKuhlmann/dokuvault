<?php

namespace App\Models;

use App\Models\Concerns\OwnedByCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Progress of one run through the documentation wizard (see
 * App\Livewire\DocumentationWizard). Kein Inventar-Objekt, daher keine
 * SoftDeletes and no entry in config('custom.trashables').
 */
class DocumentationRun extends Model
{
    use HasFactory;
    use OwnedByCustomer;

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
     * Remembers a created id under the respective step key, for the summary
     * at the end of the run.
     */
    public function recordCreated(string $stepKey, int $id): void
    {
        $records = $this->created_records ?? [];
        $records[$stepKey] ??= [];
        $records[$stepKey][] = $id;

        $this->update(['created_records' => $records]);
    }

    /**
     * Captured and skipped are mutually exclusive: if a step is captured it
     * drops out of the skipped list (and the other way round in
     * markStepSkipped). Otherwise a step could stand in both lists after
     * jumping back and the final count would count it twice.
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
