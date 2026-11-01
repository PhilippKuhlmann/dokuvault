<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\ImageType;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the installation's own logos.
 *
 * Gepflegt werden sie in App\Livewire\AdminAllgemein - hier geht es nur
 * about handing out the stored file.
 *
 * The files lie on the local disk and go out through logo(), not through
 * public/storage: the app stores every file privately and passes it through
 * a controller - a symlink would be the only special case and
 * muesste auf jedem Server zusaetzlich eingerichtet werden.
 */
class BrandingController extends Controller
{
    /**
     * Serve a logo - without signing in, it stands on the sign-in page too.
     *
     * The place comes from the allowlist, never raw from the URL: otherwise
     * the setting key would be determined from outside. The parameter is
     * named in English like the URL - the values themselves (login, header, favicon)
     * waren es schon.
     *
     * nosniff and a fixed content type: the browser should treat the file as
     * an image and not guess for itself what it might be.
     */
    public function logo(string $placement)
    {
        abort_unless(in_array($placement, Setting::LOGO_STELLEN, true), 404);

        $pfad = Setting::logoPfad($placement);

        abort_if($pfad === null || ! Storage::disk('local')->exists($pfad), 404);

        return response(Storage::disk('local')->get($pfad), Response::HTTP_OK, [
            'Content-Type' => ImageType::fuer($pfad),
            'X-Content-Type-Options' => 'nosniff',
            // The logo rarely changes but stands on every page.
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
