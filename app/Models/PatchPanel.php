<?php

namespace App\Models;

use App\Models\Concerns\IsMountable;
use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A documented patch panel. Unlike the catalog placeholder of the same name
 * it belongs to a customer and carries its ports - only that makes it
 * possible to answer where a network outlet sits.
 */
class PatchPanel extends Model
{
    use HasFactory, SoftDeletes;
    use IsMountable;
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

    public function ports()
    {
        return $this->hasMany(PatchPort::class)->orderBy('number');
    }

    /**
     * Creates missing port rows so that 1..port_count exist without gaps.
     * Existing rows stay untouched - that is where the documentation is.
     */
    public function syncPorts(): void
    {
        $vorhanden = $this->ports()->pluck('number')->all();

        $fehlende = collect(range(1, $this->port_count))
            ->diff($vorhanden)
            ->map(fn (int $number) => [
                'customer_id' => $this->customer_id,
                'patch_panel_id' => $this->id,
                'number' => $number,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        if ($fehlende) {
            PatchPort::insert($fehlende);
        }

        // Remove surplus ports. The PatchPanelController allows shrinking
        // only when nothing is documented there.
        $this->ports()->where('number', '>', $this->port_count)->delete();
    }

    /** Port numbers above $portCount that have something documented in them. */
    public function documentedPortsAbove(int $portCount): array
    {
        return $this->ports()
            ->where('number', '>', $portCount)
            ->get()
            ->filter(fn (PatchPort $port) => $port->isDocumented())
            ->pluck('number')
            ->all();
    }

    /**
     * Why the port count cannot shrink to $portCount, or null if it can.
     *
     * Shrinking would delete the upper port rows. As long as something is
     * documented there, better refuse than discard silently.
     */
    public function shrinkError(int $portCount): ?string
    {
        $betroffen = $this->documentedPortsAbove($portCount);

        if (! $betroffen) {
            return null;
        }

        // "Port 5 is" vs. "Ports 5, 6 and 12 are"
        $liste = count($betroffen) === 1
            ? 'Port '.$betroffen[0].' ist'
            : 'Ports '.implode(', ', array_slice($betroffen, 0, -1)).' und '.end($betroffen).' sind';

        return 'Die Portanzahl kann nicht auf '.$portCount.' verkleinert werden – '.$liste.' noch dokumentiert.';
    }
}
