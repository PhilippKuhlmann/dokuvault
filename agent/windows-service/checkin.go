package main

import (
	"bytes"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"os"
	"strings"
)

// checkin tells DokuVault which machine this is and what it detected, and
// gets back the agents it should run - ticked on the agent page. That is how
// two DCs of one domain both report as servers, but only one reports AD.
func checkin(cfg Config) ([]string, error) {
	hostname, _ := os.Hostname()
	payload, err := json.Marshal(map[string]any{
		"kind":       "windows",
		"machine_id": machineID(),
		"hostname":   hostname,
		"domain":     machineDomain(),
		"version":    version,
		"detected":   effectiveAgents(cfg),
	})
	if err != nil {
		return nil, err
	}

	endpoint := strings.TrimRight(cfg.URL, "/") + "/api/agent/checkin"
	req, err := http.NewRequest(http.MethodPost, endpoint, bytes.NewReader(payload))
	if err != nil {
		return nil, err
	}
	req.Header.Set("Authorization", "Bearer "+cfg.Token)
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Accept", "application/json")
	req.Header.Set("User-Agent", "DokuVault-Agent/"+version)

	resp, err := httpClient.Do(req)
	if err != nil {
		return nil, fmt.Errorf("DokuVault nicht erreichbar (%s): %w", endpoint, err)
	}
	defer resp.Body.Close()

	switch resp.StatusCode {
	case http.StatusOK:
	case http.StatusUnauthorized:
		return nil, errUnauthorized
	default:
		return nil, fmt.Errorf("Anmeldung: unerwartete Antwort %d", resp.StatusCode)
	}

	var answer struct {
		Roles []string `json:"roles"`
	}
	body, err := io.ReadAll(io.LimitReader(resp.Body, 64<<10))
	if err != nil {
		return nil, err
	}
	if err := json.Unmarshal(body, &answer); err != nil {
		return nil, fmt.Errorf("Anmeldung: Antwort nicht lesbar: %w", err)
	}

	var agents []string
	for _, a := range answer.Roles {
		if knownAgents[a] {
			agents = append(agents, a)
		}
	}
	return agents, nil
}

// assignedAgents: what DokuVault says to run. If it cannot be asked (older
// server without checkin, network hiccup), the detected agents run as
// before - better a report than none. An expired token stops everything.
func assignedAgents(cfg Config, log func(level, msg string)) ([]string, error) {
	agents, err := checkin(cfg)
	if errors.Is(err, errUnauthorized) {
		return nil, err
	}
	if err != nil {
		log("warn", err.Error()+" - nutze die erkannten Agenten")
		return effectiveAgents(cfg), nil
	}
	return agents, nil
}
