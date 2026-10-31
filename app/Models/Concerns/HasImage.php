<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;

/**
 * A model with a stored image.
 *
 * The rack catalog and the device models both carry one, and both treat it
 * the same way: the file lies privately on the local disk and goes out
 * through a controller - like every file in this app. A symlink into public
 * would be the only special case and would have to be set up on every
 * server.
 *
 * The model brings two pieces of information:
 *
 *   BILDORDNER    folder on the local disk
 *   BILDROUTE     name of the route that serves the image
 */
trait HasImage
{
    /**
     * Otherwise a file would be left behind for every deleted entry that
     * nobody finds any more.
     */
    public static function bootHasImage(): void
    {
        static::deleting(fn ($eintrag) => $eintrag->deleteImage());
    }

    /** Adresse des Bildes, oder null. */
    public function imageUrl(): ?string
    {
        return $this->image_path ? route(static::BILDROUTE, $this) : null;
    }

    /** Absolute path of the image file - for the PDF export, which cannot do HTTP. */
    public function imagePath(): ?string
    {
        return $this->hasImageFile() ? Storage::disk('local')->path($this->image_path) : null;
    }

    /** Remove the stored file. The column stays untouched. */
    public function deleteImage(): void
    {
        if ($this->hasImageFile()) {
            Storage::disk('local')->delete($this->image_path);
        }
    }

    /** Is there really a file at the recorded address? */
    private function hasImageFile(): bool
    {
        return (bool) $this->image_path && Storage::disk('local')->exists($this->image_path);
    }
}
