package main

import (
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"errors"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strings"
	"testing"
	"time"
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
		// An older server without checkin: the configured agents run.
		if r.URL.Path == "/api/agent/checkin" {
			w.WriteHeader(http.StatusNotFound)
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

func TestRunOnceRunsOnlyTheAgentsDokuVaultAssigns(t *testing.T) {
	useTempDir(t)

	var sent map[string]any
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path == "/api/agent/checkin" {
			_ = json.NewDecoder(r.Body).Decode(&sent)
			// The second DC: server yes, AD no - and nothing unknown.
			w.Write([]byte(`{"roles":["windows-server","something-else"]}`))
			return
		}
		w.Write([]byte("Write-Host ok"))
	}))
	defer srv.Close()

	var ran []string
	oldRun := runScript
	runScript = func(path string) (string, error) {
		ran = append(ran, filepath.Base(path))
		return "", nil
	}
	defer func() { runScript = oldRun }()

	if err := runOnce(validConfig(srv.URL), func(string, string) {}); err != nil {
		t.Fatalf("runOnce: %v", err)
	}
	if strings.Join(ran, ",") != "windows-server.ps1" {
		t.Fatalf("ran %v", ran)
	}
	if sent["kind"] != "windows" || sent["machine_id"] != "test-machine" || sent["hostname"] == "" {
		t.Fatalf("checkin sent %v", sent)
	}
}

func TestNoAssignedAgentsRunsNothing(t *testing.T) {
	useTempDir(t)

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path == "/api/agent/checkin" {
			w.Write([]byte(`{"roles":[]}`))
			return
		}
		t.Errorf("unexpected request %s", r.URL.Path)
	}))
	defer srv.Close()

	if err := runOnce(validConfig(srv.URL), func(string, string) {}); err != nil {
		t.Fatalf("runOnce: %v", err)
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

func TestVerifyUpdateRejectsWrongChecksumAndNonExe(t *testing.T) {
	exe := []byte("MZ fake windows program")
	sum := sha256.Sum256(exe)
	if err := verifyUpdate(exe, hex.EncodeToString(sum[:])); err != nil {
		t.Fatalf("valid update rejected: %v", err)
	}
	if verifyUpdate(exe, "deadbeef") == nil {
		t.Fatal("wrong checksum must be rejected")
	}
	page := []byte("<html>error</html>")
	pageSum := sha256.Sum256(page)
	if verifyUpdate(page, hex.EncodeToString(pageSum[:])) == nil {
		t.Fatal("a non-exe must be rejected even with matching checksum")
	}
}

func TestSwapBinaryReplacesAndKeepsOld(t *testing.T) {
	dir := t.TempDir()
	exe := filepath.Join(dir, "dokuvault-agent.exe")
	os.WriteFile(exe, []byte("MZ old"), 0o755)

	if err := swapBinary(exe, []byte("MZ new")); err != nil {
		t.Fatalf("swap: %v", err)
	}
	if got, _ := os.ReadFile(exe); string(got) != "MZ new" {
		t.Fatalf("exe not replaced: %q", got)
	}
	if got, _ := os.ReadFile(exe + ".old"); string(got) != "MZ old" {
		t.Fatalf("old exe not kept: %q", got)
	}
}

func TestCheckUpdateSaysNoContentWhenCurrent(t *testing.T) {
	old := version
	version = "26.10.03-test"
	defer func() { version = old }()

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Query().Get("version") != "26.10.03-test" || r.Header.Get("Authorization") != "Bearer doc_test" {
			t.Errorf("unexpected request %s", r.URL)
		}
		w.WriteHeader(http.StatusNoContent)
	}))
	defer srv.Close()

	updated, _, err := checkUpdate(validConfig(srv.URL))
	if err != nil || updated {
		t.Fatalf("expected no update, got updated=%v err=%v", updated, err)
	}
}

func TestCheckUpdateRejectsTamperedDownload(t *testing.T) {
	old := version
	version = "26.10.03-test"
	defer func() { version = old }()

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("X-Agent-Sha256", "0000")
		w.Write([]byte("MZ not what the checksum says"))
	}))
	defer srv.Close()

	if updated, _, err := checkUpdate(validConfig(srv.URL)); err == nil || updated {
		t.Fatalf("tampered download must not be installed: updated=%v err=%v", updated, err)
	}
}

func TestRunOnceReportsTheOutcomePerAgent(t *testing.T) {
	useTempDir(t)

	var report struct {
		MachineID string      `json:"machine_id"`
		Results   []runResult `json:"results"`
	}
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch r.URL.Path {
		case "/api/agent/checkin":
			w.Write([]byte(`{"roles":["windows-server","windows-ad"]}`))
		case "/api/agent/report":
			_ = json.NewDecoder(r.Body).Decode(&report)
			w.Write([]byte(`{"status":"ok"}`))
		default:
			w.Write([]byte("Write-Host ok"))
		}
	}))
	defer srv.Close()

	oldRun := runScript
	runScript = func(path string) (string, error) {
		if filepath.Base(path) == "windows-ad.ps1" {
			return "Get-ADUser: Zugriff verweigert", errors.New("exit status 1")
		}
		return "Server gemeldet", nil
	}
	defer func() { runScript = oldRun }()

	if err := runOnce(validConfig(srv.URL), func(string, string) {}); err == nil {
		t.Fatal("expected the failed AD run as error")
	}

	if report.MachineID != "test-machine" || len(report.Results) != 2 {
		t.Fatalf("report %+v", report)
	}
	if !report.Results[0].OK || report.Results[0].Message != "Server gemeldet" {
		t.Fatalf("server result %+v", report.Results[0])
	}
	if report.Results[1].OK || !strings.Contains(report.Results[1].Message, "Zugriff verweigert") || !strings.Contains(report.Results[1].Message, "exit status 1") {
		t.Fatalf("ad result %+v", report.Results[1])
	}
}

func dueServer(t *testing.T, answer string) *httptest.Server {
	t.Helper()
	return httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch r.URL.Path {
		case "/api/agent/checkin":
			w.Write([]byte(answer))
		case "/api/agent/report":
			w.Write([]byte(`{"status":"ok"}`))
		default:
			w.Write([]byte("Write-Host ok"))
		}
	}))
}

func TestRunIfDueRunsOnlyWhenDokuVaultSaysSo(t *testing.T) {
	useTempDir(t)
	ran := 0
	oldRun := runScript
	runScript = func(string) (string, error) { ran++; return "", nil }
	defer func() { runScript = oldRun }()

	notYet := dueServer(t, `{"roles":["windows-server"],"run":false,"interval":60}`)
	defer notYet.Close()
	if did, err := runIfDue(validConfig(notYet.URL), func(string, string) {}, false); err != nil || did || ran != 0 {
		t.Fatalf("not due: did=%v ran=%d err=%v", did, ran, err)
	}

	// run-once runs anyway.
	if err := runOnce(validConfig(notYet.URL), func(string, string) {}); err != nil || ran != 1 {
		t.Fatalf("forced: ran=%d err=%v", ran, err)
	}

	now := dueServer(t, `{"roles":["windows-server"],"run":true,"interval":60}`)
	defer now.Close()
	if did, err := runIfDue(validConfig(now.URL), func(string, string) {}, false); err != nil || !did || ran != 2 {
		t.Fatalf("due: did=%v ran=%d err=%v", did, ran, err)
	}
}

func TestOlderServerWithoutRunUsesTheLocalInterval(t *testing.T) {
	useTempDir(t)
	ran := 0
	oldRun := runScript
	runScript = func(string) (string, error) { ran++; return "", nil }
	defer func() { runScript = oldRun }()

	srv := dueServer(t, `{"roles":["windows-server"]}`)
	defer srv.Close()

	lastLocalRun = time.Time{}
	runIfDue(validConfig(srv.URL), func(string, string) {}, false)
	runIfDue(validConfig(srv.URL), func(string, string) {}, false)
	if ran != 1 {
		t.Fatalf("expected one run within the interval, got %d", ran)
	}
}
