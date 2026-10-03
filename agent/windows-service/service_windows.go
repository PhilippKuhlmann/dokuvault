//go:build windows

package main

import (
	"fmt"
	"io"
	"os"
	"os/exec"
	"path/filepath"
	"syscall"
	"time"

	"golang.org/x/sys/windows/registry"
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
	m, err := mgr.Connect()
	if err != nil {
		return fmt.Errorf("Dienstverwaltung nicht erreichbar (als Administrator ausfuehren?): %w", err)
	}
	defer m.Disconnect()

	// Stop an existing installation before copying: its exe is in use while
	// the service runs, and the copy would fail.
	if old, err := m.OpenService(serviceName); err == nil {
		stopAndWait(old)
		_ = old.Delete()
		old.Close()
		// The SCM removes the entry asynchronously.
		time.Sleep(2 * time.Second)
	}

	// The folder is already locked down by saveConfig (prepareDataDir).
	exe, err := installBinary()
	if err != nil {
		return err
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

	// Not fatal: the service works without it, only the entry under
	// Settings > Apps would be missing.
	if err := registerUninstall(exe); err != nil {
		appendLog("warn", "Eintrag unter Apps nicht anlegbar: "+err.Error())
	}

	return s.Start()
}

const uninstallKey = `SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\` + serviceName

// registerUninstall lists the agent under Settings > Apps and in "Programs
// and Features", with logo and an uninstall button that runs
// "dokuvault-agent.exe uninstall".
func registerUninstall(exe string) error {
	k, _, err := registry.CreateKey(registry.LOCAL_MACHINE, uninstallKey, registry.SET_VALUE)
	if err != nil {
		return err
	}
	defer k.Close()

	size := uint32(0)
	if fi, err := os.Stat(exe); err == nil {
		size = uint32(fi.Size() / 1024)
	}

	values := map[string]string{
		"DisplayName":     serviceDisplay,
		"DisplayIcon":     exe + ",0",
		"DisplayVersion":  version,
		"Publisher":       "DokuVault",
		"InstallLocation": dataDir,
		"UninstallString": `"` + exe + `" uninstall`,
	}
	for name, value := range values {
		if err := k.SetStringValue(name, value); err != nil {
			return err
		}
	}
	_ = k.SetDWordValue("NoModify", 1)
	_ = k.SetDWordValue("NoRepair", 1)
	_ = k.SetDWordValue("EstimatedSize", size)
	return nil
}

func removeUninstallEntry() {
	_ = registry.DeleteKey(registry.LOCAL_MACHINE, uninstallKey)
}

// restartServiceLater starts the service again a few seconds after this
// process has stopped - so the new exe runs. A detached cmd, because a
// service cannot start itself while it is still stopping.
func restartServiceLater() error {
	cmd := exec.Command("cmd.exe", "/C", "ping -n 6 127.0.0.1 >nul & sc start "+serviceName)
	cmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true, CreationFlags: 0x00000008} // DETACHED_PROCESS
	return cmd.Start()
}

// removeDataDirLater deletes the data folder a few seconds after this
// process has ended: uninstall usually runs from the copy inside it, and a
// running exe cannot delete itself.
func removeDataDirLater() error {
	cmd := exec.Command("cmd.exe", "/C", "ping -n 4 127.0.0.1 >nul & rmdir /S /Q \""+dataDir+"\"")
	cmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true, CreationFlags: 0x00000008} // DETACHED_PROCESS
	return cmd.Start()
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

// prepareDataDir creates the folder and locks it down: only SYSTEM
// (S-1-5-18) and Administrators (S-1-5-32-544) - config.json holds the agent
// token. SIDs instead of names, so it works on a German Windows as well.
//
// Only the folder gets explicit permissions; files inherit them through
// (OI)(CI). The first version applied this with /T to the files as well:
// a file takes no (OI)(CI) entries, so after /inheritance:r it was left with
// none at all - "Access is denied" even for administrators. Files from such
// a run are reset to inheriting first; as owner (Administrators) we may.
func prepareDataDir() error {
	if err := os.MkdirAll(dataDir, 0o700); err != nil {
		return err
	}

	if entries, _ := os.ReadDir(dataDir); len(entries) > 0 {
		if out, err := exec.Command("icacls", filepath.Join(dataDir, "*"), "/reset", "/T", "/C", "/Q").CombinedOutput(); err != nil {
			appendLog("warn", fmt.Sprintf("Rechte der vorhandenen Dateien nicht zuruecksetzbar: %v %s", err, out))
		}
	}

	out, err := exec.Command("icacls", dataDir,
		"/inheritance:r",
		"/grant:r", "*S-1-5-18:(OI)(CI)F",
		"/grant:r", "*S-1-5-32-544:(OI)(CI)F",
		"/Q").CombinedOutput()
	if err != nil {
		return fmt.Errorf("Rechte auf %s nicht setzbar: %v %s", dataDir, err, out)
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
	removeUninstallEntry()
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

	// After an update: remove the replaced exe, and refresh the entry under
	// Apps so it shows the new version.
	cleanupOldBinary()
	if exe, err := os.Executable(); err == nil {
		_ = registerUninstall(exe)
	}

	// Config read before every run: set-token and a re-install take effect
	// without restarting the service. Before collecting, the agent checks
	// for a newer version of itself; if it replaced its exe, it stops and
	// restarts with the new one.
	run := func() (time.Duration, bool) {
		cfg, err := loadConfig()
		if err != nil {
			log("error", err.Error())
			return time.Hour, false
		}
		if neu, neueVersion, err := checkUpdate(cfg); err != nil {
			log("warn", err.Error())
		} else if neu {
			log("info", "Update auf Version "+neueVersion+" installiert - Dienst startet neu")
			return 0, true
		}
		if err := runOnce(cfg, log); err != nil {
			log("warn", err.Error())
		}
		return time.Duration(cfg.IntervalMinutes) * time.Minute, false
	}

	restart := func() (bool, uint32) {
		if err := restartServiceLater(); err != nil {
			log("error", "Neustart nach Update nicht moeglich: "+err.Error())
		}
		status <- svc.Status{State: svc.StopPending}
		return false, 0
	}

	pause, updated := run()
	if updated {
		return restart()
	}
	timer := time.NewTimer(pause)
	defer timer.Stop()

	for {
		select {
		case <-timer.C:
			pause, updated := run()
			if updated {
				return restart()
			}
			timer.Reset(pause)
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
