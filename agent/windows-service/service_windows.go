//go:build windows

package main

import (
	"fmt"
	"io"
	"os"
	"os/exec"
	"path/filepath"
	"time"

	"golang.org/x/sys/windows/svc"
	"golang.org/x/sys/windows/svc/eventlog"
	"golang.org/x/sys/windows/svc/mgr"
)

const (
	serviceName    = "DokuVaultAgent"
	serviceDisplay = "DokuVault Agent"
	serviceArg     = "service"
)

// installService copies the exe next to the config, locks the folder down
// and registers an auto-start service running as LocalSystem. Running it
// again replaces an existing installation (new URL, agents, interval).
func installService() error {
	exe, err := installBinary()
	if err != nil {
		return err
	}
	if err := lockDown(dataDir); err != nil {
		return err
	}

	m, err := mgr.Connect()
	if err != nil {
		return fmt.Errorf("Dienstverwaltung nicht erreichbar (als Administrator ausfuehren?): %w", err)
	}
	defer m.Disconnect()

	if old, err := m.OpenService(serviceName); err == nil {
		stopAndWait(old)
		_ = old.Delete()
		old.Close()
		// The SCM removes the entry asynchronously.
		time.Sleep(2 * time.Second)
	}

	s, err := m.CreateService(serviceName, exe, mgr.Config{
		DisplayName: serviceDisplay,
		Description: "Meldet Server-, AD- und Hyper-V-Daten regelmaessig an DokuVault.",
		StartType:   mgr.StartAutomatic,
	}, serviceArg)
	if err != nil {
		return fmt.Errorf("Dienst nicht anlegbar: %w", err)
	}
	defer s.Close()

	// Restart after a crash, so a single hiccup does not end the reporting.
	_ = s.SetRecoveryActions([]mgr.RecoveryAction{
		{Type: mgr.ServiceRestart, Delay: time.Minute},
		{Type: mgr.ServiceRestart, Delay: 5 * time.Minute},
	}, 24*60*60)

	_ = eventlog.Remove(serviceName)
	if err := eventlog.InstallAsEventCreate(serviceName, eventlog.Error|eventlog.Warning|eventlog.Info); err != nil {
		appendLog("warn", "Ereignisquelle nicht anlegbar: "+err.Error())
	}

	return s.Start()
}

// installBinary copies the running exe into the data folder: the service
// must not depend on the Downloads folder staying as it is.
func installBinary() (string, error) {
	src, err := os.Executable()
	if err != nil {
		return "", err
	}
	dst := filepath.Join(dataDir, "dokuvault-agent.exe")
	if same, _ := filepath.Abs(src); same == dst {
		return dst, nil
	}

	in, err := os.Open(src)
	if err != nil {
		return "", err
	}
	defer in.Close()

	out, err := os.Create(dst)
	if err != nil {
		return "", fmt.Errorf("%s nicht schreibbar (Dienst noch aktiv?): %w", dst, err)
	}
	if _, err := io.Copy(out, in); err != nil {
		out.Close()
		return "", err
	}
	return dst, out.Close()
}

// lockDown: only SYSTEM (S-1-5-18) and Administrators (S-1-5-32-544) may
// read the folder - config.json holds the agent token. SIDs instead of
// names, so it works on a German Windows as well.
func lockDown(dir string) error {
	out, err := exec.Command("icacls", dir,
		"/inheritance:r",
		"/grant:r", "*S-1-5-18:(OI)(CI)F",
		"/grant:r", "*S-1-5-32-544:(OI)(CI)F",
		"/T", "/Q").CombinedOutput()
	if err != nil {
		return fmt.Errorf("Rechte auf %s nicht setzbar: %v %s", dir, err, out)
	}
	return nil
}

func uninstallService() error {
	m, err := mgr.Connect()
	if err != nil {
		return fmt.Errorf("Dienstverwaltung nicht erreichbar (als Administrator ausfuehren?): %w", err)
	}
	defer m.Disconnect()

	s, err := m.OpenService(serviceName)
	if err != nil {
		return nil // nothing installed
	}
	defer s.Close()

	stopAndWait(s)
	if err := s.Delete(); err != nil {
		return err
	}
	_ = eventlog.Remove(serviceName)
	return nil
}

func stopAndWait(s *mgr.Service) {
	if _, err := s.Control(svc.Stop); err != nil {
		return
	}
	for i := 0; i < 30; i++ {
		st, err := s.Query()
		if err != nil || st.State == svc.Stopped {
			return
		}
		time.Sleep(time.Second)
	}
}

func serviceStatus() string {
	m, err := mgr.Connect()
	if err != nil {
		return "unbekannt (" + err.Error() + ")"
	}
	defer m.Disconnect()

	s, err := m.OpenService(serviceName)
	if err != nil {
		return "nicht eingerichtet"
	}
	defer s.Close()

	st, err := s.Query()
	if err != nil {
		return "unbekannt"
	}
	switch st.State {
	case svc.Running:
		return "laeuft"
	case svc.Stopped:
		return "gestoppt"
	default:
		return fmt.Sprintf("Zustand %d", st.State)
	}
}

func runService() error {
	return svc.Run(serviceName, &handler{})
}

type handler struct{}

func (h *handler) Execute(_ []string, req <-chan svc.ChangeRequest, status chan<- svc.Status) (bool, uint32) {
	status <- svc.Status{State: svc.StartPending}

	elog, err := eventlog.Open(serviceName)
	if err != nil {
		elog = nil
	}
	log := func(level, msg string) {
		appendLog(level, msg)
		if elog == nil {
			return
		}
		switch level {
		case "error":
			_ = elog.Error(1, msg)
		case "warn":
			_ = elog.Warning(2, msg)
		default:
			_ = elog.Info(3, msg)
		}
	}
	if elog != nil {
		defer elog.Close()
	}

	status <- svc.Status{State: svc.Running, Accepts: svc.AcceptStop | svc.AcceptShutdown}
	log("info", "Dienst gestartet, Version "+version)

	// Config read before every run: set-token and a re-install take effect
	// without restarting the service.
	run := func() time.Duration {
		cfg, err := loadConfig()
		if err != nil {
			log("error", err.Error())
			return time.Hour
		}
		if err := runOnce(cfg, log); err != nil {
			log("warn", err.Error())
		}
		return time.Duration(cfg.IntervalMinutes) * time.Minute
	}

	timer := time.NewTimer(run())
	defer timer.Stop()

	for {
		select {
		case <-timer.C:
			timer.Reset(run())
		case c := <-req:
			switch c.Cmd {
			case svc.Interrogate:
				status <- c.CurrentStatus
			case svc.Stop, svc.Shutdown:
				log("info", "Dienst beendet")
				status <- svc.Status{State: svc.StopPending}
				return false, 0
			}
		}
	}
}
