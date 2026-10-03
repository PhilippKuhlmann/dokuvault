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
	"time"
)

// pollInterval: how often the service asks DokuVault whether to run. The
// run interval itself is set per agent on the agent page.
const pollInterval = 5 * time.Minute

// checkinAnswer is what DokuVault answers. Run is nil from a DokuVault
// before 26.10.03, which decided nothing about timing.
type checkinAnswer struct {
	Agents []string
	Run    *bool
}

// checkin tells DokuVault which machine this is and what it detected, and
// gets back the agents it should run - ticked on the agent page. That is how
// two DCs of one domain both report as servers, but only one reports AD.
func checkin(cfg Config) (checkinAnswer, error) {
	hostname, _ := os.Hostname()
	payload, err := json.Marshal(map[string]any{
		"kind":       "windows",
		"machine_id": machineID(),
		"hostname":   hostname,
		"domain":     machineDomain(),
		"version":    version,
		"detected":   effectiveAgents(cfg),
		"interval":   cfg.IntervalMinutes,
	})
	if err != nil {
		return checkinAnswer{}, err
	}

	endpoint := strings.TrimRight(cfg.URL, "/") + "/api/agent/checkin"
	req, err := http.NewRequest(http.MethodPost, endpoint, bytes.NewReader(payload))
	if err != nil {
		return checkinAnswer{}, err
	}
	req.Header.Set("Authorization", "Bearer "+cfg.Token)
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Accept", "application/json")
	req.Header.Set("User-Agent", "DokuVault-Agent/"+version)

	resp, err := httpClient.Do(req)
	if err != nil {
		return checkinAnswer{}, fmt.Errorf("DokuVault nicht erreichbar (%s): %w", endpoint, err)
	}
	defer resp.Body.Close()

	switch resp.StatusCode {
	case http.StatusOK:
	case http.StatusUnauthorized:
		return checkinAnswer{}, errUnauthorized
	default:
		return checkinAnswer{}, fmt.Errorf("Anmeldung: unerwartete Antwort %d", resp.StatusCode)
	}

	var answer struct {
		Roles []string `json:"roles"`
		Run   *bool    `json:"run"`
	}
	body, err := io.ReadAll(io.LimitReader(resp.Body, 64<<10))
	if err != nil {
		return checkinAnswer{}, err
	}
	if err := json.Unmarshal(body, &answer); err != nil {
		return checkinAnswer{}, fmt.Errorf("Anmeldung: Antwort nicht lesbar: %w", err)
	}

	out := checkinAnswer{Run: answer.Run}
	for _, a := range answer.Roles {
		if knownAgents[a] {
			out.Agents = append(out.Agents, a)
		}
	}
	return out, nil
}

// lastLocalRun: when this process last ran the agents. Only for timing
// without DokuVault's answer (older server, network hiccup).
var lastLocalRun time.Time

func dueLocally(cfg Config) bool {
	return time.Since(lastLocalRun) >= time.Duration(cfg.IntervalMinutes)*time.Minute
}

// assignedAgents: what DokuVault says to run, and whether now. If it cannot
// be asked (network hiccup), the detected agents run on the local interval -
// better a report than none. An expired token stops everything. force: run
// regardless of timing (run-once).
func assignedAgents(cfg Config, log func(level, msg string), force bool) ([]string, bool, error) {
	answer, err := checkin(cfg)
	if errors.Is(err, errUnauthorized) {
		return nil, false, err
	}
	if err != nil {
		log("warn", err.Error()+" - nutze die erkannten Agenten")
		return effectiveAgents(cfg), force || dueLocally(cfg), nil
	}
	if answer.Run == nil {
		return answer.Agents, force || dueLocally(cfg), nil
	}
	return answer.Agents, force || *answer.Run, nil
}
