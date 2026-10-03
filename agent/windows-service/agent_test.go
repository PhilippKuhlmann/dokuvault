package main

import (
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func useTempDir(t *testing.T) {
	t.Helper()
	old := dataDir
	dataDir = t.TempDir()
	t.Cleanup(func() { dataDir = old })
}

func validConfig(url string) Config {
	return Config{URL: url, Token: "doc_test", Agents: []string{"windows-server", "windows-ad"}, IntervalMinutes: 60}
}

func TestConfigRoundTripAndValidation(t *testing.T) {
	useTempDir(t)

	if err := saveConfig(validConfig("https://doku.example")); err != nil {
		t.Fatalf("save: %v", err)
	}
	got, err := loadConfig()
	if err != nil || got.Token != "doc_test" || len(got.Agents) != 2 {
		t.Fatalf("load: %+v %v", got, err)
	}

	bad := []Config{
		{URL: "doku.example", Token: "doc_x", Agents: []string{"windows-ad"}, IntervalMinutes: 60},
		{URL: "https://doku.example", Token: "x", Agents: []string{"windows-ad"}, IntervalMinutes: 60},
		{URL: "https://doku.example", Token: "doc_x", Agents: []string{"unifi"}, IntervalMinutes: 60},
		{URL: "https://doku.example", Token: "doc_x", Agents: []string{"windows-ad"}, IntervalMinutes: 1},
	}
	for i, c := range bad {
		if c.Validate() == nil {
			t.Errorf("case %d should be rejected", i)
		}
	}
}

func TestSplitAgents(t *testing.T) {
	got := splitAgents(" Windows-Server, ,windows-ad ")
	if strings.Join(got, "|") != "windows-server|windows-ad" {
		t.Fatalf("got %v", got)
	}
}

func TestRunOnceFetchesWritesAndRunsEveryAgent(t *testing.T) {
	useTempDir(t)

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Header.Get("Authorization") != "Bearer doc_test" {
			w.WriteHeader(http.StatusUnauthorized)
			return
		}
		w.Write([]byte("Write-Host 'Grüße aus " + filepath.Base(r.URL.Path) + "'"))
	}))
	defer srv.Close()

	var ran []string
	oldRun := runScript
	runScript = func(path string) (string, error) {
		ran = append(ran, filepath.Base(path))
		return "ok", nil
	}
	defer func() { runScript = oldRun }()

	if err := runOnce(validConfig(srv.URL), func(string, string) {}); err != nil {
		t.Fatalf("runOnce: %v", err)
	}
	if strings.Join(ran, ",") != "windows-server.ps1,windows-ad.ps1" {
		t.Fatalf("ran %v", ran)
	}

	body, _ := os.ReadFile(filepath.Join(scriptsDir(), "windows-ad.ps1"))
	if !strings.HasPrefix(string(body), "\xEF\xBB\xBF") || !strings.Contains(string(body), "Grüße aus windows-ad") {
		t.Fatalf("script not written with BOM: %q", body)
	}
}

func TestExpiredTokenStopsAfterFirstAgent(t *testing.T) {
	useTempDir(t)

	calls := 0
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		calls++
		w.WriteHeader(http.StatusUnauthorized)
	}))
	defer srv.Close()

	var logged []string
	err := runOnce(validConfig(srv.URL), func(level, msg string) { logged = append(logged, level+": "+msg) })

	if err == nil || calls != 1 {
		t.Fatalf("expected one call and an error, got calls=%d err=%v", calls, err)
	}
	if !strings.Contains(strings.Join(logged, "\n"), "set-token") {
		t.Fatalf("log should tell how to fix it: %v", logged)
	}
}

// Same layout as AgentTokenController::dienstExe: JSON, uint32 LE, marker.
func TestReadEmbeddedConfig(t *testing.T) {
	dir := t.TempDir()
	json := `{"url":"https://doku.example","token":"doc_eingebettet"}`
	n := len(json)
	trailer := json + string([]byte{byte(n), byte(n >> 8), byte(n >> 16), byte(n >> 24)}) + embeddedMarker

	withCfg := filepath.Join(dir, "mit.exe")
	os.WriteFile(withCfg, append([]byte("MZ...pe image..."), trailer...), 0o600)

	cfg, ok, err := readEmbedded(withCfg)
	if err != nil || !ok || cfg.URL != "https://doku.example" || cfg.Token != "doc_eingebettet" {
		t.Fatalf("embedded: %+v ok=%v err=%v", cfg, ok, err)
	}

	plain := filepath.Join(dir, "ohne.exe")
	os.WriteFile(plain, []byte("MZ...pe image without config..."), 0o600)
	if _, ok, err := readEmbedded(plain); ok || err != nil {
		t.Fatalf("plain exe must report no config: ok=%v err=%v", ok, err)
	}
}

func TestAutoAgentsAreValidAndResolve(t *testing.T) {
	c := Config{URL: "https://doku.example", Token: "doc_x", Agents: []string{"auto"}, IntervalMinutes: 60}
	if err := c.Validate(); err != nil {
		t.Fatalf("auto should be valid: %v", err)
	}
	if got := effectiveAgents(c); len(got) == 0 || got[0] == "auto" {
		t.Fatalf("auto must resolve to real agents, got %v", got)
	}
}
