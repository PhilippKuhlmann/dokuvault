<?php

namespace App\Http\Controllers;

use App\Models\Concerns\HasCredentials;
use App\Models\Concerns\HasIpAddresses;
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

    /** Cache je Model, ob es weitere IP-Adressen führen kann. */
    private static array $hasIpAddresses = [];

    /** Cache je Model und Relation, ob es sie gibt (Rack, Betriebssystem, Standort). */
    private static array $hatRelation = [];

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

        if ($hatZugangsdaten) {
            $query->with('credentialLinks.login');
        }

        // Dieselbe Ueberlegung fuer die weiteren IP-Adressen: Sie stehen jetzt in
        // der Liste und wuerden sonst je Geraet eine eigene Abfrage kosten.
        $hatAdressen = self::$hasIpAddresses[$model] ??= in_array(
            HasIpAddresses::class, class_uses_recursive($model), true
        );

        if ($hatAdressen) {
            $query->with('ipAddresses.network');
        }

        // Dasselbe fuer den Einbauort, das Betriebssystem und den Standort.
        // Gemessen an einer Liste mit 22 Switches: 216 Abfragen fuer eine Seite
        // mit 25 Zeilen, weil einbauort() je Geraet Rack und Einbau einzeln
        // nachlud. Mit diesen drei Relationen sind es 54. Lokal faellt der
        // Unterschied kaum auf - mit Netzwerk zwischen Anwendung und Datenbank
        // sind es Sekunden.
        foreach (['rackItem.rack', 'operatingSystem', 'site'] as $relation) {
            $erste = explode('.', $relation)[0];

            if (self::$hatRelation[$model.'::'.$erste] ??= method_exists($model, $erste)) {
                $query->with($relation);
            }
        }

        return $query;
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
