<!-- Das Logo aus dem Quelltext, nicht von der Webseite: dieselbe Quelle wie
     das Abbild selbst. Aendert es sich im Projekt, folgt die Seite. -->
![DokuVault](https://raw.githubusercontent.com/PhilippKuhlmann/dokuvault/main/public/logo.svg)

# DokuVault

IT-Dokumentation für Systemhäuser und interne IT: Kunden, Standorte, Server,
VMs, Netze, Zugangsdaten, Lizenzen, Racks — an einer Stelle, durchsuchbar, mit
Änderungsprotokoll und PDF-Export.

**[dokuvault.de](https://dokuvault.de)** · **[Live-Demo ohne Installation](https://doku.dokuvault.de)** · **[Quelltext auf GitHub](https://github.com/PhilippKuhlmann/dokuvault)**

## Ausprobieren

Nur ansehen? Die [Live-Demo](https://doku.dokuvault.de) läuft ohne Installation.
Für den eigenen Rechner geht es hier weiter.

Am einfachsten mit einer Datenbank daneben — `docker-compose.yml`:

```yaml
services:
  app:
    image: <BENUTZER>/dokuvault
    ports: ["8000:8000"]
    environment:
      APP_ENV: local          # legt beim ersten Start Demo-Daten an
      APP_URL: http://localhost:8000
      LOG_CHANNEL: stderr
      DB_CONNECTION: mysql
      DB_HOST: db
      DB_DATABASE: dokuvault
      DB_USERNAME: dokuvault
      DB_PASSWORD: dokuvault
    volumes:
      - daten:/app/storage
    depends_on: [db]

  db:
    image: mariadb:11
    environment:
      MARIADB_DATABASE: dokuvault
      MARIADB_USER: dokuvault
      MARIADB_PASSWORD: dokuvault
      MARIADB_ROOT_PASSWORD: dokuvault
    volumes:
      - db:/var/lib/mysql

volumes:
  daten:
  db:
```

Dann `docker compose up` und <http://localhost:8000>.

## Erster Start

Der Container wartet auf die Datenbank, migriert und startet. Ist die Datenbank
noch leer, werden Startdaten angelegt — mit `APP_ENV=local` zusätzlich ein
vollständiger Demo-Kunde.

**Die Demo-Anmeldung lautet `admin` / `password`.** Sie ist zum Ansehen
gedacht. Wer das Bild über einen Probelauf hinaus betreibt, setzt
`APP_ENV=production` und legt einen eigenen Zugang an.

## Umgebungsvariablen

| Variable | Bedeutung |
|---|---|
| `APP_ENV` | `local` legt Demo-Daten an, `production` nur die Startdaten |
| `APP_URL` | Adresse, unter der die Anwendung erreichbar ist |
| `APP_KEY` | Verschlüsselungsschlüssel. Ohne Angabe wird beim ersten Start einer erzeugt |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Datenbank (MySQL oder MariaDB) |
| `LOG_CHANNEL` | `stderr` empfohlen, damit `docker logs` die Fehler zeigt |

**Den `APP_KEY` festlegen und aufheben**, sobald es um echte Daten geht:
Zugangsdaten und Kennwörter liegen damit verschlüsselt in der Datenbank. Ein
neuer Schlüssel macht sie unlesbar.

Was überleben soll, gehört in ein Volume auf `/app/storage` — hochgeladene
Dateien, Sitzungen, Protokolle.

## Mehr dazu

Alles Weitere — was DokuVault kann, wie es betrieben wird und woran gerade
gearbeitet wird — steht auf **[dokuvault.de](https://dokuvault.de)** und im
[Quelltext](https://github.com/PhilippKuhlmann/dokuvault).

## Wofür das Bild gedacht ist

Ein Container mit **nginx und php-fpm**, gehalten von supervisord. nginx
liefert CSS, JavaScript und Bilder selbst aus, PHP bekommt nur die Anfragen,
die es braucht; Opcache ist an. Damit trägt das Bild auch mehrere Nutzer
gleichzeitig.

Sauberer wären zwei Container — einer je Prozess. Dann wäre das Abbild allein
aber nicht lauffähig, und genau das soll es sein: `docker run`, und es steht.

## Markierungen

- `latest` — der jeweils letzte Stand von `main`
- `YY.MM.DD` — ein fester Stand, passend zum Changelog

Gebaut für `amd64` und `arm64`.
