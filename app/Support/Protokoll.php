<?php

namespace App\Support;

use App\Models\AgentToken;
use Illuminate\Database\Eloquent\Model;

/**
 * Wie ein Protokolleintrag seinen Verursacher zeigt.
 *
 * Verursacher ist entweder ein Benutzer oder ein Agent-Token - beide haben ein
 * Feld 'name', und ein Tokenname wie "Werkstatt" saehe in der Spalte aus wie
 * ein Mensch. Deshalb bekommt der Agent seine eigene Schreibweise.
 *
 * Bleibt "System" fuer alles ohne Verursacher: Seeder, Konsolenbefehle,
 * geplante Aufgaben. Dort gibt es wirklich niemanden.
 */
class Protokoll
{
    public static function verursacher(?Model $causer): string
    {
        if (! $causer) {
            return __('System');
        }

        if ($causer instanceof AgentToken) {
            // Ein Token ohne Bezeichnung ist erlaubt - dann bleibt die Nummer.
            return __('Agent').' · '.($causer->name ?: 'Token #'.$causer->id);
        }

        return (string) ($causer->name ?? __('System'));
    }
}
