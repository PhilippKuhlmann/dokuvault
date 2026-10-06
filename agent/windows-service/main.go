// dokuvault-agent: runs the DokuVault PowerShell agents as a Windows service.
//
// It does not collect anything itself. Every interval it fetches the current
// script of each configured agent from DokuVault (GET /api/agent/script/...)
// and runs it - the scripts in resources/agents/ stay the single source, and
// updates arrive without reinstalling.
package main

import (
	"flag"
	"fmt"
	"os"
	"strings"
)

// version is set at build time (-ldflags "-X main.version=...").
var version = "dev"

const usage = `DokuVault-Agent %s

Am einfachsten: die in DokuVault (Agenten) nach dem Erzeugen eines Tokens
heruntergeladene Datei doppelklicken - sie richtet sich selbst ein.

Befehle (als Administrator):
  install   -url https://doku.firma.de -token doc_... [-agents auto] [-interval 60]
            Konfiguration schreiben, Dienst "DokuVault Agent" einrichten, starten, einmal sofort melden.
            -agents auto (Standard) waehlt nach den Rollen des Rechners; sonst z. B. windows-server,windows-ad.
  run-once  Alle Agenten jetzt einmal ausfuehren, Ausgabe hier im Fenster.
  status    Dienststatus und Konfiguration (ohne Token) anzeigen.
  set-token -token doc_...   Neuen Token eintragen (nach Ablauf oder Erneuern).
  uninstall Dienst und C:\ProgramData\DokuVault entfernen.

Agenten: windows-server, windows-ad, hyperv, windows-client, veeam, windows-backup
`

// installEmbeddedArg: the elevated relaunch after a double click.
const installEmbeddedArg = "install-embedded"

func main() {
	// Double click: no arguments. An exe downloaded from DokuVault carries
	// URL and token at its end - then it installs itself.
	if len(os.Args) < 2 || os.Args[1] == installEmbeddedArg {
		if err := doubleClick(); err != nil {
			fmt.Fprintln(os.Stderr, "Fehler:", err)
			pause()
			os.Exit(1)
		}
		return
	}

	// Started by the service control manager.
	if os.Args[1] == serviceArg {
		if err := runService(); err != nil {
			appendLog("error", err.Error())
			os.Exit(1)
		}
		return
	}

	if err := command(os.Args[1], os.Args[2:]); err != nil {
		fmt.Fprintln(os.Stderr, "Fehler:", err)
		os.Exit(1)
	}
}

func command(name string, args []string) error {
	switch name {
	case "install":
		fs := flag.NewFlagSet("install", flag.ContinueOnError)
		u := fs.String("url", "", "Adresse von DokuVault")
		token := fs.String("token", "", "Agent-Token (doc_...)")
		agents := fs.String("agents", "auto", "auto (nach Rollen des Rechners) oder Agenten, durch Komma getrennt")
		interval := fs.Int("interval", 60, "Intervall in Minuten (mindestens 5) - Vorschlag, danach in DokuVault einstellbar")
		if err := fs.Parse(args); err != nil {
			return err
		}
		cfg := Config{URL: strings.TrimRight(*u, "/"), Token: *token, Agents: splitAgents(*agents), IntervalMinutes: *interval}
		if err := saveConfig(cfg); err != nil {
			return err
		}
		if err := installService(); err != nil {
			return err
		}
		fmt.Printf("Dienst eingerichtet: %s alle %d Minuten an %s.\n", strings.Join(effectiveAgents(cfg), ", "), cfg.IntervalMinutes, cfg.URL)
		fmt.Println("Erster Lauf laeuft im Dienst; Protokoll:", logPath())
		return nil

	case "run-once":
		cfg, err := loadConfig()
		if err != nil {
			return err
		}
		return runOnce(cfg, func(level, msg string) {
			fmt.Printf("[%s] %s\n", level, msg)
			appendLog(level, msg)
		})

	case "status":
		cfg, err := loadConfig()
		if err != nil {
			return err
		}
		agenten := strings.Join(cfg.Agents, ", ")
		if isAuto(cfg.Agents) {
			agenten = "auto (erkannt: " + strings.Join(detectAgents(), ", ") + ")"
		}
		fmt.Printf("Dienst:    %s\nURL:       %s\nAgenten:   %s\nIntervall: %d Minuten (ohne Verbindung zu DokuVault; sonst dort eingestellt)\nProtokoll: %s\n",
			serviceStatus(), cfg.URL, agenten, cfg.IntervalMinutes, logPath())
		return nil

	case "set-token":
		fs := flag.NewFlagSet("set-token", flag.ContinueOnError)
		token := fs.String("token", "", "neuer Agent-Token (doc_...)")
		if err := fs.Parse(args); err != nil {
			return err
		}
		cfg, err := loadConfig()
		// A config whose only problem is the old token is still usable.
		if err != nil && cfg.URL == "" {
			return err
		}
		cfg.Token = *token
		if err := saveConfig(cfg); err != nil {
			return err
		}
		fmt.Println("Token gespeichert - gilt ab dem naechsten Lauf.")
		return nil

	case "uninstall":
		// Also started from Settings > Apps, without admin rights: ask via
		// UAC, the elevated copy continues in its own window.
		if !isElevated() {
			return relaunchElevated("uninstall")
		}
		if err := uninstallService(); err != nil {
			return err
		}
		// Usually runs from the copy inside dataDir, which cannot delete
		// itself while running: config and token go now, the folder right
		// after this process has ended.
		if err := os.RemoveAll(dataDir); err != nil {
			if err := removeDataDirLater(); err != nil {
				fmt.Println("Dienst entfernt. Rest in", dataDir, "bitte von Hand loeschen:", err)
				pause()
				return nil
			}
		}
		fmt.Println("DokuVault Agent deinstalliert.")
		pause()
		return nil

	case "version", "-v", "--version":
		fmt.Println(version)
		return nil
	}

	fmt.Printf(usage, version)
	return fmt.Errorf("unbekannter Befehl %q", name)
}

// doubleClick installs from the configuration embedded in the exe. Without
// one (the plain exe), it explains how to use it instead of flashing a
// console window shut.
func doubleClick() error {
	fmt.Printf("DokuVault-Agent %s\n\n", version)

	exe, err := os.Executable()
	if err != nil {
		return err
	}
	emb, ok, err := readEmbedded(exe)
	if err != nil {
		return err
	}
	if !ok {
		fmt.Printf(usage, version)
		fmt.Println("\nDiese Datei enthaelt keine Zugangsdaten. Den vorkonfigurierten Download gibt es")
		fmt.Println("in DokuVault unter Agenten, direkt nach dem Erzeugen eines Tokens.")
		pause()
		return nil
	}

	// A service needs administrator rights: ask via UAC, the elevated copy
	// continues in its own window.
	if !isElevated() {
		fmt.Println("Fordere Administratorrechte an ...")
		return relaunchElevated(installEmbeddedArg)
	}

	cfg := Config{URL: emb.URL, Token: emb.Token, Agents: []string{"auto"}, IntervalMinutes: 60}
	if err := saveConfig(cfg); err != nil {
		return err
	}
	if err := installService(); err != nil {
		return err
	}

	fmt.Println("Dienst \"DokuVault Agent\" eingerichtet.")
	fmt.Println("Meldet an:  ", cfg.URL)
	fmt.Println("Erkannt:    ", strings.Join(detectAgents(), ", "))
	fmt.Println("Intervall:   stuendlich - aendern und sofort melden in DokuVault unter Agenten")
	fmt.Println("Protokoll:  ", logPath())
	fmt.Println("\nDer erste Lauf startet innerhalb der naechsten Minute im Hintergrund.")
	pause()
	return nil
}

// pause keeps a double-clicked console window open until it was read.
func pause() {
	fmt.Print("\nEnter druecken zum Schliessen ...")
	_, _ = fmt.Scanln()
}
