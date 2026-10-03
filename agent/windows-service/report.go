package main

import (
	"bytes"
	"encoding/json"
	"fmt"
	"net/http"
	"strings"
)

// runResult is the outcome of one agent in one run.
type runResult struct {
	Role    string `json:"role"`
	OK      bool   `json:"ok"`
	Message string `json:"message,omitempty"`
}

// maxMessage: DokuVault keeps the last 1000 characters; sending more than a
// few KB of script output is pointless.
const maxMessage = 4000

// sendReport tells DokuVault how the run went, per agent - shown on the
// agent page and the dashboard. An older DokuVault without the endpoint
// answers 404; that is not worth more than a warning.
func sendReport(cfg Config, results []runResult) error {
	if len(results) == 0 {
		return nil
	}
	for i := range results {
		if m := results[i].Message; len(m) > maxMessage {
			results[i].Message = m[len(m)-maxMessage:]
		}
	}

	payload, err := json.Marshal(map[string]any{
		"machine_id": machineID(),
		"results":    results,
	})
	if err != nil {
		return err
	}

	endpoint := strings.TrimRight(cfg.URL, "/") + "/api/agent/report"
	req, err := http.NewRequest(http.MethodPost, endpoint, bytes.NewReader(payload))
	if err != nil {
		return err
	}
	req.Header.Set("Authorization", "Bearer "+cfg.Token)
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Accept", "application/json")
	req.Header.Set("User-Agent", "DokuVault-Agent/"+version)

	resp, err := httpClient.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return fmt.Errorf("Antwort %d", resp.StatusCode)
	}
	return nil
}
