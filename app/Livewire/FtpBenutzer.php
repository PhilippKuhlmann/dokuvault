<?php

namespace App\Livewire;

use App\Models\FTPServer;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Die Zugaenge eines FTP-Servers - als Block im Bearbeiten-Modal.
 *
 * Dasselbe Muster wie die IP-Adressen am Geraet: Der Server steht oben, seine
 * Benutzer darunter. Vorher war jeder Zugang eine eigene Server-Zeile mit
 * demselben Host.
 */
class FtpBenutzer extends Component
{
    // Skalar statt Model-Instanz, wie bei den uebrigen Bloecken: robust
    // gegenueber Livewire-Hydration.
    #[Locked]
    public int $serverId;

    #[Locked]
    public int $customerId;

    /** Im Modal bringt der Rahmen das Padding mit - dann keins vom Block. */
    public bool $randlos = false;

    public string $username = '';

    public string $password = '';

    public string $note = '';

    public function mount($model, $customer, bool $randlos = false): void
    {
        $this->serverId = $model->id;
        $this->customerId = $customer->id;
        $this->randlos = $randlos;
    }

    /**
     * Der Server, immer ueber den Kunden geholt.
     *
     * Oeffentliche Eigenschaften lassen sich im Browser aendern - deshalb bei
     * jeder Aktion pruefen und nicht nur beim Aufbau.
     */
    protected function server(): FTPServer
    {
        Gate::authorize('ftpserver_update');

        $server = FTPServer::findOrFail($this->serverId);

        $nutzer = auth()->user();
        abort_if($nutzer->customer_id && $nutzer->customer_id !== $server->customer_id, 403);
        abort_if($server->customer_id !== $this->customerId, 403);

        return $server;
    }

    public function hinzufuegen(): void
    {
        $server = $this->server();

        $geprueft = $this->validate([
            'username' => ['required', 'max:255'],
            'password' => ['nullable', 'max:255'],
            'note' => ['nullable', 'max:255'],
        ], [], [
            'username' => __('Benutzername'),
            'password' => __('Passwort'),
            'note' => __('Notiz'),
        ]);

        $server->users()->create([
            'customer_id' => $this->customerId,
            'username' => $geprueft['username'],
            'password' => $geprueft['password'] ?: null,
            'note' => $geprueft['note'] ?: null,
        ]);

        $this->reset('username', 'password', 'note');

        // Die Liste um diesen Block herum zeigt die Zugaenge in ihrer Spalte -
        // ohne diese Meldung bliebe sie auf dem alten Stand.
        $this->dispatch('geraet-geaendert');
    }

    public function entfernen(int $id): void
    {
        $server = $this->server();

        // Ueber die Beziehung, nicht ueber die Id allein: Sonst liesse sich mit
        // einer fremden Id der Zugang eines anderen Servers loeschen.
        //
        // abort statt findOrFail: Das liefert einen sauberen 404 statt einer
        // durchgereichten Ausnahme.
        $zugang = $server->users()->find($id);
        abort_unless($zugang !== null, 404);

        $zugang->delete();

        $this->dispatch('geraet-geaendert');
    }

    public function render()
    {
        return view('livewire.ftp-benutzer', [
            'benutzer' => FTPServer::findOrFail($this->serverId)
                ->users()->orderBy('username')->get(),
        ]);
    }
}
