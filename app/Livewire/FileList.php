<?php

namespace App\Livewire;

use App\Livewire\Concerns\ScopedToCustomer;
use App\Models\Customer;
use App\Models\File;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The file list with search, filters and sorting.
 *
 * It used to be a fixed list, oldest first. At a customer with a hundred
 * files "where is the maintenance contract from 2024?" was a paging exercise -
 * and the file uploaded last stood right at the back.
 */
class FileList extends Component
{
    use ScopedToCustomer;
    use WithPagination;

    #[Locked]
    public int $customerId;

    /** Free text across label and extension. */
    #[Url(except: '')]
    public string $suche = '';

    /** Limit to one kind, PDFs only for instance. */
    #[Url(except: '')]
    public string $art = '';

    /** The last X days only. 0 means everything. */
    #[Url(except: 0)]
    public int $tage = 0;

    /** Sortierung: neueste, aelteste, name, groesse. */
    #[Url(except: 'neueste')]
    public string $sortierung = 'neueste';

    public function mount(Customer $customer): void
    {
        Gate::authorize('viewAny', File::class);
        $this->onlyOwnCustomer($customer->id);

        $this->customerId = $customer->id;
    }

    public function updated(string $eigenschaft): void
    {
        if (in_array($eigenschaft, ['suche', 'art', 'tage', 'sortierung'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['suche', 'art', 'tage', 'sortierung']);
        $this->resetPage();
    }

    /**
     * Delete a file - together with the file on disk.
     *
     * The id is checked against the customer: otherwise another customer's
     * file could be deleted with a foreign id.
     */
    public function delete(int $id): void
    {
        Gate::authorize('delete', File::class);

        $datei = File::where('customer_id', $this->customerId)->find($id);

        // abort instead of findOrFail: that yields a clean 404 instead of an
        // exception passed through.
        abort_unless($datei !== null, 404);

        Storage::disk('local')->delete($datei->file_path);
        $datei->delete();

        $this->dispatch('notice', text: __('Datei gelöscht.'));
    }

    public function render()
    {
        $abfrage = File::where('customer_id', $this->customerId)
            ->when($this->suche !== '', fn ($a) => $a->whereEnthaelt(['name', 'extension'], $this->suche))
            ->when($this->tage > 0, fn ($a) => $a->where('created_at', '>=', $this->grenze()))
            ->when($this->art !== '', fn ($a) => $this->limitToType($a));

        $abfrage = match ($this->sortierung) {
            'aelteste' => $abfrage->oldest(),
            'name' => $abfrage->orderBy('name'),
            // Without a stored size (a legacy file) to the back instead of the
            // front: an empty figure is not a small file.
            'groesse' => $abfrage->orderByRaw('size IS NULL')->orderByDesc('size'),
            default => $abfrage->latest(),
        };

        return view('livewire.file-list', [
            'customer' => Customer::findOrFail($this->customerId),
            'files' => $abfrage->paginate(Setting::seiteListe()),
            'gesamt' => File::where('customer_id', $this->customerId)->count(),
            'arten' => collect(config('custom.file_arten'))->map(fn ($a) => $a[0])->all(),
            'gefiltert' => $this->suche !== '' || $this->art !== '' || $this->tage > 0,
        ]);
    }

    /**
     * Limit to one kind.
     *
     * "datei" is the remainder - everything in no extension list. That cannot
     * be expressed as a list, only as its opposite.
     */
    private function limitToType($abfrage)
    {
        // Compared in lower case: the extension is stored as it was uploaded -
        // "Vertrag.PDF" is a PDF file and should not be missing from the
        // filter. On SQLite (tests) IN otherwise compares letter for letter.
        // buchstabengetreu.
        $spalte = DB::raw('LOWER(extension)');

        if ($this->art === 'datei') {
            return $abfrage->whereNotIn($spalte, File::bekannteEndungen());
        }

        return $abfrage->whereIn($spalte, File::extensionsForType($this->art));
    }

    /**
     * From when files are shown.
     *
     * "today" means today, not "the last 24 hours" - the same rule as in the
     * log.
     */
    private function grenze(): Carbon
    {
        return $this->tage === 1 ? now()->startOfDay() : now()->subDays($this->tage);
    }
}
