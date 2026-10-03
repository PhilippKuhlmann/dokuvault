//go:build !windows

package main

import "errors"

// Stubs so the platform-neutral part (config, fetch, run loop) builds and is
// tested on macOS/Linux. The service itself only exists on Windows.

const serviceArg = "service"

var errNotWindows = errors.New("nur unter Windows verfuegbar")

func installService() error   { return errNotWindows }
func uninstallService() error { return errNotWindows }
func serviceStatus() string   { return "nur unter Windows" }
func runService() error       { return errNotWindows }

// Role detection needs the Windows registry; elsewhere a plain server.
func detectAgents() []string             { return []string{"windows-server"} }
func isElevated() bool                   { return true }
func relaunchElevated(args string) error { return errNotWindows }
