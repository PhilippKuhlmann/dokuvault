package main

import (
	"context"
	"errors"
	"fmt"
	"io"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"time"
)

// errUnauthorized: token expired or revoked. Reported loudly - the service
// keeps running, but nothing arrives in DokuVault until set-token.
var errUnauthorized = errors.New("Token abgelehnt (abgelaufen oder widerrufen) - mit 'dokuvault-agent.exe set-token -token doc_...' erneuern")

const scriptTimeout = 10 * time.Minute

var httpClient = &http.Client{Timeout: 60 * time.Second}

// fetchScript gets the current script of one agent, URL and token already
// filled in by the server. Fetched before every run, so script updates
// reach the service without a new download.
func fetchScript(cfg Config, agent string) ([]byte, error) {
	endpoint := strings.TrimRight(cfg.URL, "/") + "/api/agent/script/" + agent

	req, err := http.NewRequest(http.MethodGet, endpoint, nil)
	if err != nil {
		return nil, err
	}
	req.Header.Set("Authorization", "Bearer "+cfg.Token)
	req.Header.Set("User-Agent", "DokuVault-Agent/"+version)

	resp, err := httpClient.Do(req)
	if err != nil {
		return nil, fmt.Errorf("DokuVault nicht erreichbar (%s): %w", endpoint, err)
	}
	defer resp.Body.Close()

	switch resp.StatusCode {
	case http.StatusOK:
		return io.ReadAll(io.LimitReader(resp.Body, 5<<20))
	case http.StatusUnauthorized:
		return nil, errUnauthorized
	case http.StatusNotFound:
		return nil, fmt.Errorf("Agent %q wird vom Server nicht (mehr) angeboten", agent)
	default:
		return nil, fmt.Errorf("unerwartete Antwort %d von %s", resp.StatusCode, endpoint)
	}
}

// writeScript stores the script locally. Written by this process, the file
// carries no "downloaded from the internet" mark - RemoteSigned runs it
// without Unblock-File. A UTF-8 BOM, because Windows PowerShell 5.1 reads
// BOM-less files as ANSI and would garble umlauts.
func writeScript(agent string, body []byte) (string, error) {
	if err := os.MkdirAll(scriptsDir(), 0o700); err != nil {
		return "", err
	}
	path := filepath.Join(scriptsDir(), agent+".ps1")
	bom := []byte{0xEF, 0xBB, 0xBF}
	if len(body) >= 3 && string(body[:3]) == string(bom) {
		bom = nil
	}
	return path, os.WriteFile(path, append(bom, body...), 0o600)
}

// runScript runs one script with powershell.exe and returns its output.
// No change to the execution policy: under AllSigned (GPO) this fails, and
// the output says why.
var runScript = func(path string) (string, error) {
	ctx, cancel := context.WithTimeout(context.Background(), scriptTimeout)
	defer cancel()

	cmd := exec.CommandContext(ctx, "powershell.exe", "-NoProfile", "-NonInteractive", "-File", path)
	out, err := cmd.CombinedOutput()
	if ctx.Err() == context.DeadlineExceeded {
		return string(out), fmt.Errorf("Abbruch nach %s", scriptTimeout)
	}
	return string(out), err
}

// runOnce asks DokuVault which agents to run (assignedAgents) and runs each
// once, due or not (run-once on the command line). One failing agent does not stop the others; the returned error
// summarises all failures.
func runOnce(cfg Config, log func(level, msg string)) error {
	_, err := runIfDue(cfg, log, true)
	return err
}

// runIfDue is the service's poll: ask DokuVault, run only when due (or
// force). Returns whether it ran.
func runIfDue(cfg Config, log func(level, msg string), force bool) (bool, error) {
	agents, due, err := assignedAgents(cfg, log, force)
	if err != nil {
		log("error", err.Error())
		return false, err
	}
	if !due {
		return false, nil
	}
	lastLocalRun = time.Now()
	return true, runAgents(cfg, agents, log)
}

func runAgents(cfg Config, agents []string, log func(level, msg string)) error {
	if len(agents) == 0 {
		log("info", "Keine Aufgaben zugewiesen (in DokuVault unter Agenten anhaken)")
		return nil
	}

	var failed []string
	var results []runResult

	for _, agent := range agents {
		var out string
		body, err := fetchScript(cfg, agent)
		if err == nil {
			var path string
			if path, err = writeScript(agent, body); err == nil {
				out, err = runScript(path)
				if out = strings.TrimSpace(out); out != "" {
					log("info", agent+": "+out)
				}
			}
		}

		if err != nil {
			log("error", agent+": "+err.Error())
			failed = append(failed, agent)
			// The same token serves every agent - no point asking again,
			// nor reporting with it.
			if errors.Is(err, errUnauthorized) {
				return fmt.Errorf("fehlgeschlagen: %s", strings.Join(failed, ", "))
			}
			results = append(results, runResult{Role: agent, OK: false, Message: strings.TrimSpace(out + "\n" + err.Error())})
			continue
		}
		log("info", agent+": gemeldet")
		results = append(results, runResult{Role: agent, OK: true, Message: out})
	}

	// Not fatal: the data is in DokuVault already, only the status is missing.
	if err := sendReport(cfg, results); err != nil {
		log("warn", "Ergebnis nicht gemeldet: "+err.Error())
	}

	if len(failed) > 0 {
		return fmt.Errorf("fehlgeschlagen: %s", strings.Join(failed, ", "))
	}
	return nil
}
