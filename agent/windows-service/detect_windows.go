//go:build windows

package main

import (
	"os"
	"strings"
	"syscall"

	"golang.org/x/sys/windows"
	"golang.org/x/sys/windows/registry"
	"golang.org/x/sys/windows/svc/mgr"
)

// detectAgents picks the agents from the roles of this machine:
//
//	ProductType WinNT    -> workstation       -> windows-client
//	ProductType ServerNT -> member server     -> windows-server
//	ProductType LanmanNT -> domain controller -> windows-server + windows-ad
//	service vmms present -> Hyper-V host      -> + hyperv (servers only)
//
// Read before every run, so later role changes are picked up.
func detectAgents() []string {
	productType := "ServerNT"
	if k, err := registry.OpenKey(registry.LOCAL_MACHINE, `SYSTEM\CurrentControlSet\Control\ProductOptions`, registry.QUERY_VALUE); err == nil {
		if v, _, err := k.GetStringValue("ProductType"); err == nil {
			productType = v
		}
		k.Close()
	}

	if strings.EqualFold(productType, "WinNT") {
		return []string{"windows-client"}
	}

	agents := []string{"windows-server"}
	if strings.EqualFold(productType, "LanmanNT") {
		agents = append(agents, "windows-ad")
	}
	if serviceExists("vmms") {
		agents = append(agents, "hyperv")
	}
	return agents
}

func serviceExists(name string) bool {
	m, err := mgr.Connect()
	if err != nil {
		return false
	}
	defer m.Disconnect()

	s, err := m.OpenService(name)
	if err != nil {
		return false
	}
	s.Close()
	return true
}

func isElevated() bool {
	return windows.GetCurrentProcessToken().IsElevated()
}

// relaunchElevated starts this exe again with the UAC prompt ("runas").
// The elevated copy does the work in its own window.
func relaunchElevated(args string) error {
	exe, err := os.Executable()
	if err != nil {
		return err
	}
	verb, _ := syscall.UTF16PtrFromString("runas")
	file, _ := syscall.UTF16PtrFromString(exe)
	params, _ := syscall.UTF16PtrFromString(args)
	return windows.ShellExecute(0, verb, file, params, nil, windows.SW_NORMAL)
}
