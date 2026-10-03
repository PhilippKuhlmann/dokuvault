package main

import (
	"bytes"
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"os"
	"strings"
)

// checkUpdate asks DokuVault whether a newer agent exists and, if so,
// replaces the exe in place. Returns true when the exe was replaced - the
// service then restarts itself to run the new one.
//
// Replacing a running exe: Windows does not allow overwriting it, but it
// does allow renaming it. The current one becomes .old (deleted on the next
// start), the new one takes its name.
func checkUpdate(cfg Config) (bool, string, error) {
	// A development build has no version to compare - never replace it.
	if version == "dev" {
		return false, "", nil
	}

	endpoint := strings.TrimRight(cfg.URL, "/") + "/api/agent/update/windows?version=" + url.QueryEscape(version)
	req, err := http.NewRequest(http.MethodGet, endpoint, nil)
	if err != nil {
		return false, "", err
	}
	req.Header.Set("Authorization", "Bearer "+cfg.Token)
	req.Header.Set("User-Agent", "DokuVault-Agent/"+version)

	resp, err := httpClient.Do(req)
	if err != nil {
		return false, "", fmt.Errorf("Update-Pruefung: DokuVault nicht erreichbar: %w", err)
	}
	defer resp.Body.Close()

	switch resp.StatusCode {
	case http.StatusNoContent:
		return false, "", nil
	case http.StatusUnauthorized:
		return false, "", errUnauthorized
	case http.StatusOK:
	default:
		return false, "", fmt.Errorf("Update-Pruefung: unerwartete Antwort %d", resp.StatusCode)
	}

	body, err := io.ReadAll(io.LimitReader(resp.Body, 64<<20))
	if err != nil {
		return false, "", err
	}
	if err := verifyUpdate(body, resp.Header.Get("X-Agent-Sha256")); err != nil {
		return false, "", err
	}

	exe, err := os.Executable()
	if err != nil {
		return false, "", err
	}
	if err := swapBinary(exe, body); err != nil {
		return false, "", err
	}
	return true, resp.Header.Get("X-Agent-Version"), nil
}

// verifyUpdate: the checksum must match and it must be a Windows program -
// a truncated download or an error page must never replace the agent.
func verifyUpdate(body []byte, wantSha string) error {
	sum := sha256.Sum256(body)
	if wantSha == "" || !strings.EqualFold(hex.EncodeToString(sum[:]), wantSha) {
		return fmt.Errorf("Update verworfen: Pruefsumme stimmt nicht")
	}
	if !bytes.HasPrefix(body, []byte("MZ")) {
		return fmt.Errorf("Update verworfen: keine Windows-Programmdatei")
	}
	return nil
}

func swapBinary(exe string, body []byte) error {
	neu, alt := exe+".new", exe+".old"

	if err := os.WriteFile(neu, body, 0o755); err != nil {
		return err
	}
	_ = os.Remove(alt)
	if err := os.Rename(exe, alt); err != nil {
		_ = os.Remove(neu)
		return fmt.Errorf("Update: laufende exe nicht umbenennbar: %w", err)
	}
	if err := os.Rename(neu, exe); err != nil {
		// Put the old one back - better the old agent than none.
		_ = os.Rename(alt, exe)
		return fmt.Errorf("Update: neue exe nicht einsetzbar: %w", err)
	}
	return nil
}

// cleanupOldBinary removes the .old left behind by the last update.
func cleanupOldBinary() {
	if exe, err := os.Executable(); err == nil {
		_ = os.Remove(exe + ".old")
	}
}
