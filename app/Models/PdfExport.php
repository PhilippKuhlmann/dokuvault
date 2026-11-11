<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCustomer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A job for a customer's PDF export.
 *
 * Not an inventory object, hence no soft delete and no trash: a
 * abgeholtes oder abgelaufenes PDF verschwindet einfach.
 */
class PdfExport extends Model
{
    use BelongsToCustomer;

    public const OFFEN = 'offen';

    public const LAEUFT = 'laeuft';

    public const FERTIG = 'fertig';

    public const FEHLER = 'fehler';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'finished_at' => 'datetime',
        'size' => 'integer',
    ];

    /**
     * The file holds every password of a customer in plain text - it goes
     * with its row, wherever the row is deleted from.
     */
    protected static function booted(): void
    {
        static::deleting(function (PdfExport $export) {
            if ($export->path) {
                Storage::disk('local')->delete($export->path);
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function istFertig(): bool
    {
        return $this->status === self::FERTIG;
    }

    public function laeuftNoch(): bool
    {
        return in_array($this->status, [self::OFFEN, self::LAEUFT], true);
    }

    /**
     * Has the job been waiting too long without anyone working on it?
     *
     * If the cron line is missing on the server it stays on "open" forever -
     * then the display should say so instead of spinning endlessly.
     */
    public function haengt(): bool
    {
        return $this->laeuftNoch() && $this->created_at?->lt(now()->subMinutes(5));
    }

    public function groesseLesbar(): ?string
    {
        return $this->size ? number_format($this->size / 1048576, 1, ',', '.').' MB' : null;
    }
}
