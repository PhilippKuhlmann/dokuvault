<?php

namespace App\Http\Controllers;

use App\Models\Concerns\HasCredentials;
use App\Models\Network;
use App\Models\Site;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Schema;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /** Cache je Tabelle, ob sie eine site_id-Spalte hat (spart Schema-Abfragen pro Request). */
    private static array $hasSiteColumn = [];

    /** Cache je Model, ob es Zugangsdaten verknüpfen kann. */
    private static array $hasCredentials = [];

    protected function getFilteredQuery($model, $customer)
    {
        $site = session()->get('site');

        // Standortfilter nur anwenden, wenn ein Standort gewählt ist UND dieser zum
        // aktuellen Kunden gehört. Sonst (nicht gesetzt / "all" / fremder Standort aus
        // einem vorherigen Kunden) alle Datensätze des Kunden zurückgeben.
        if ($site && $site !== 'all' && $customer->sites()->whereKey($site)->exists()
            && $this->tableHasSiteColumn($model)) {
            $query = $model::where('customer_id', $customer->id)->where('site_id', $site);
        } else {
            $query = $model::where('customer_id', $customer->id);
        }

        return $this->zugangsdatenVorladen($model, $query);
    }

    /**
     * Zugangsdaten mitladen, wo das Model sie hat: Die Listen zeigen sie je Gerät,
     * ohne Vorladen wären das 25 zusätzliche Abfragen pro Seite.
     */
    private function zugangsdatenVorladen($model, $query)
    {
        $hatZugangsdaten = self::$hasCredentials[$model] ??= in_array(
            HasCredentials::class, class_uses_recursive($model), true
        );

        return $hatZugangsdaten ? $query->with('credentialLinks.login') : $query;
    }

    /**
     * Nicht jedes Model hat einen Standortbezug (z. B. File, LicenseWindows, Backup).
     * Ohne diese Prüfung erzeugte ein gewählter Standort dort ein "where site_id = ?"
     * auf einer Tabelle ohne diese Spalte -> HTTP 500 unter MySQL.
     *
     * Das fiel in den Tests nicht auf: SQLite (Test-DB) behandelt den unbekannten,
     * doppelt gequoteten Bezeichner als String-Literal und liefert still 0 Treffer,
     * während MySQL (Entwicklung/Produktion) hart mit "Unknown column" abbricht.
     */
    private function tableHasSiteColumn($model): bool
    {
        $table = (new $model)->getTable();

        return self::$hasSiteColumn[$table] ??= Schema::hasColumn($table, 'site_id');
    }

    protected function getSitesForCustomer($customer)
    {
        return Site::where('customer_id', $customer->id)->get();
    }

    protected function getNetworksForCustomer($customer)
    {
        return Network::where('customer_id', $customer->id)->get();
    }
}
