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

// machineID identifies this machine to DokuVault - one token serves many
// machines. MachineGuid survives renames and is set by Windows setup.
func machineID() string {
	k, err := registry.OpenKey(registry.LOCAL_MACHINE, `SOFTWARE\Microsoft\Cryptography`, registry.QUERY_VALUE|registry.WOW64_64KEY)
	if err != nil {
		return ""
	}
	defer k.Close()
	v, _, _ := k.GetStringValue("MachineGuid")
	return v
}

// machineDomain is the DNS domain of the machine (empty outside a domain):
// one AD report per domain is enough.
func machineDomain() string {
	k, err := registry.OpenKey(registry.LOCAL_MACHINE, `SYSTEM\CurrentControlSet\Services\Tcpip\Parameters`, registry.QUERY_VALUE)
	if err != nil {
		return ""
	}
	defer k.Close()
	v, _, _ := k.GetStringValue("Domain")
	return v
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
