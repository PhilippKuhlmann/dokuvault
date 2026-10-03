package main

import (
	"encoding/json"
	"errors"
	"fmt"
	"net/url"
	"os"
	"path/filepath"
	"strings"
)

// Config is what "install" writes and the service reads on every run.
// It holds the agent token, so the folder is locked down to SYSTEM and
// Administrators (see lockDown in service_windows.go).
type Config struct {
	URL             string   `json:"url"`
	Token           string   `json:"token"`
	Agents          []string `json:"agents"`
	IntervalMinutes int      `json:"interval_minutes"`
}

// dataDir is a variable so tests can point it at a temp folder.
var dataDir = defaultDataDir()

func defaultDataDir() string {
	base := os.Getenv("ProgramData")
	if base == "" {
		base = `C:\ProgramData`
	}
	return filepath.Join(base, "DokuVault")
}

func configPath() string { return filepath.Join(dataDir, "config.json") }
func scriptsDir() string { return filepath.Join(dataDir, "scripts") }
func logPath() string    { return filepath.Join(dataDir, "agent.log") }

// knownAgents are the ones the server hands out to the service
// (AgentSkript::fuerDienst). Checked here too, so a typo fails at install
// time and not silently every hour.
var knownAgents = map[string]bool{
	"windows-server": true,
	"windows-ad":     true,
	"hyperv":         true,
	"windows-client": true,
}

func (c Config) Validate() error {
	u, err := url.Parse(c.URL)
	if err != nil || (u.Scheme != "https" && u.Scheme != "http") || u.Host == "" {
		return fmt.Errorf("ungueltige URL %q (erwartet z. B. https://doku.firma.de)", c.URL)
	}
	if !strings.HasPrefix(c.Token, "doc_") {
		return errors.New("ungueltiger Token (beginnt mit doc_)")
	}
	if len(c.Agents) == 0 {
		return errors.New("mindestens ein Agent noetig (-agents auto oder windows-server,windows-ad)")
	}
	if isAuto(c.Agents) {
		return validateInterval(c.IntervalMinutes)
	}
	for _, a := range c.Agents {
		if !knownAgents[a] {
			return fmt.Errorf("unbekannter Agent %q (moeglich: windows-server, windows-ad, hyperv, windows-client)", a)
		}
	}
	return validateInterval(c.IntervalMinutes)
}

func validateInterval(minutes int) error {
	if minutes < 5 {
		return errors.New("Intervall mindestens 5 Minuten")
	}
	return nil
}

// "auto": the agents follow the roles of the machine (detectAgents), checked
// again before every run - a DC promoted later or Hyper-V installed later
// starts reporting without reinstalling.
func isAuto(agents []string) bool {
	return len(agents) == 1 && agents[0] == "auto"
}

func effectiveAgents(c Config) []string {
	if isAuto(c.Agents) {
		return detectAgents()
	}
	return c.Agents
}

func loadConfig() (Config, error) {
	var c Config
	data, err := os.ReadFile(configPath())
	if err != nil {
		return c, fmt.Errorf("Konfiguration nicht lesbar (%s) - zuerst install ausfuehren: %w", configPath(), err)
	}
	if err := json.Unmarshal(data, &c); err != nil {
		return c, fmt.Errorf("Konfiguration beschaedigt: %w", err)
	}
	return c, c.Validate()
}

func saveConfig(c Config) error {
	if err := c.Validate(); err != nil {
		return err
	}
	// Folder first, locked down before any file is written - the files then
	// inherit its permissions (see prepareDataDir in service_windows.go).
	if err := prepareDataDir(); err != nil {
		return err
	}
	data, err := json.MarshalIndent(c, "", "  ")
	if err != nil {
		return err
	}
	return os.WriteFile(configPath(), data, 0o600)
}

// splitAgents turns "windows-server, windows-ad" into a clean list.
func splitAgents(s string) []string {
	var out []string
	for _, p := range strings.Split(s, ",") {
		if p = strings.TrimSpace(strings.ToLower(p)); p != "" {
			out = append(out, p)
		}
	}
	return out
}
