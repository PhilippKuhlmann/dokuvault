<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The list page of a kind from config/forms.php - servers, printers,
 * domains and the rest.
 *
 * Every one of these pages is the same: check the right to list the kind,
 * then render the Livewire list, which carries the form as well. There used
 * to be one controller and one view per kind, 38 of each, differing in
 * nothing but the name.
 *
 * A kind whose page is more than that keeps a controller of its own and
 * stands in OWN_PAGE.
 */
class ObjectListController extends Controller
{
    /**
     * Kinds from config/forms.php with a page of their own: a filter (site),
     * a download (the licences) or a list built differently (SSH keys).
     */
    public const OWN_PAGE = ['site', 'sshkey', 'licensesoftware', 'licensewindows', 'licenseaccess'];

    /** The kinds this controller lists - each one becomes the route "{kind}.index". */
    public static function types(): array
    {
        return array_values(array_diff(array_keys(config('forms')), self::OWN_PAGE));
    }

    public function __invoke(Customer $customer, string $type): View
    {
        abort_unless(in_array($type, self::types(), true), 404);

        Gate::authorize($type.'_viewAny');

        return view('object.index', compact('customer', 'type'));
    }
}
