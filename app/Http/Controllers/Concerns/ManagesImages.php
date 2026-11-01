<?php

namespace App\Http\Controllers\Concerns;

use App\Support\ImageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ein Admin-Controller, an dessen Eintraegen ein Bild haengt.
 *
 * The rack catalog and the device models treat it the same way: upload,
 * replace, remove, serve. The model brings folder and route with it (see
 * App\Models\Concerns\HasImage), here stands the way there.
 */
trait ManagesImages
{
    /**
     * The validated values without the upload fields - those do not belong in
     * the columns but in a file.
     */
    protected function stammdaten(FormRequest $request): array
    {
        return Arr::except($request->validated(), ['image', 'image_remove']);
    }

    /**
     * Store a new image or remove the existing one.
     *
     * The old file first, or one stays behind with every change that
     * niemand mehr findet.
     */
    protected function handleImage(FormRequest $request, Model $eintrag, array $daten): array
    {
        if ($request->hasFile('image')) {
            $eintrag->deleteImage();
            $daten['image_path'] = $request->file('image')->store($eintrag::BILDORDNER, 'local');
        } elseif ($request->boolean('image_remove')) {
            $eintrag->deleteImage();
            $daten['image_path'] = null;
        }

        return $daten;
    }

    /**
     * Serve the stored image.
     *
     * nosniff and a fixed content type: the browser should treat the file as
     * an image and not guess for itself what it might be.
     */
    protected function serveImage(Model $eintrag)
    {
        $pfad = $eintrag->image_path;

        abort_if($pfad === null || ! Storage::disk('local')->exists($pfad), 404);

        return response(Storage::disk('local')->get($pfad), Response::HTTP_OK, [
            'Content-Type' => ImageType::fuer($pfad),
            'X-Content-Type-Options' => 'nosniff',
            // Rarely changes but stands in every rack.
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
