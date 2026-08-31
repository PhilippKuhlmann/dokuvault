<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ein Admin-Controller, an dessen Eintraegen ein Bild haengt.
 *
 * Der Rack-Katalog und die Geraetemodelle behandeln es gleich: hochladen,
 * ersetzen, entfernen, ausliefern. Das Model bringt Ordner und Route mit
 * (siehe App\Models\Concerns\HatBild), hier steht der Weg dorthin.
 */
trait PflegtBilder
{
    /**
     * Die geprueften Werte ohne die Upload-Felder - die gehoeren nicht in die
     * Spalten, sondern in eine Datei.
     */
    protected function stammdaten(FormRequest $request): array
    {
        return Arr::except($request->validated(), ['image', 'image_remove']);
    }

    /**
     * Ein neues Bild ablegen oder das vorhandene entfernen.
     *
     * Erst die alte Datei weg, sonst bleibt bei jedem Wechsel eine liegen, die
     * niemand mehr findet.
     */
    protected function bildPflegen(FormRequest $request, Model $eintrag, array $daten): array
    {
        if ($request->hasFile('image')) {
            $eintrag->bildLoeschen();
            $daten['image_path'] = $request->file('image')->store($eintrag::BILDORDNER, 'local');
        } elseif ($request->boolean('image_remove')) {
            $eintrag->bildLoeschen();
            $daten['image_path'] = null;
        }

        return $daten;
    }

    /**
     * Das hinterlegte Bild ausliefern.
     *
     * nosniff und eine feste Content-Type-Angabe: Der Browser soll die Datei
     * als Bild behandeln und nicht selbst raten, was sie sein koennte.
     */
    protected function bildAusliefern(Model $eintrag)
    {
        $pfad = $eintrag->image_path;

        abort_if($pfad === null || ! Storage::disk('local')->exists($pfad), 404);

        return response(Storage::disk('local')->get($pfad), Response::HTTP_OK, [
            'Content-Type' => Storage::disk('local')->mimeType($pfad),
            'X-Content-Type-Options' => 'nosniff',
            // Aendert sich selten, steht aber in jedem Rack.
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
