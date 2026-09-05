<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Die Kennung, unter der ein Agent seine Objekte wiederfindet.
 *
 * Server, VMs, Computer und die AD-Objekte haben diese Spalte seit ihrer
 * ersten Migration; die sechs Tabellen hier bekommen sie jetzt, weil der
 * UniFi- und der Microsoft-365-Agent sie fuellen.
 *
 * Ohne sie muesste ein Agent seine Objekte am Namen wiedererkennen. Der Name
 * ist aber genau das, was der Kunde aendert - eine umbenannte SSID oder ein
 * umgetaufter Switch waere beim naechsten Lauf ein zweiter Eintrag statt eine
 * Aktualisierung. Die Kennung stammt vom Quellsystem (MAC, UniFi-Id,
 * Graph-Objekt-Id) und bleibt.
 */
return new class extends Migration
{
    protected array $tabellen = [
        'network_switches',
        'accesspoints',
        'wifis',
        'mailboxes',
        'domains',
        'license_software',
    ];

    public function up(): void
    {
        foreach ($this->tabellen as $tabelle) {
            Schema::table($tabelle, function (Blueprint $table) {
                // Index: der Upsert sucht bei jedem Lauf ueber diese Spalte.
                $table->string('agent_identifier')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tabellen as $tabelle) {
            Schema::table($tabelle, function (Blueprint $table) {
                $table->dropColumn('agent_identifier');
            });
        }
    }
};
