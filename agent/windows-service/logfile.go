package main

import (
	"fmt"
	"os"
	"sync"
	"time"
)

// A plain text log next to the config, besides the Windows event log: easy
// to send along when something does not arrive. Rotated at 2 MB, one old
// file kept - an hourly service must not fill the disk over the years.
const maxLogSize = 2 << 20

var logMu sync.Mutex

func appendLog(level, msg string) {
	logMu.Lock()
	defer logMu.Unlock()

	if fi, err := os.Stat(logPath()); err == nil && fi.Size() > maxLogSize {
		_ = os.Rename(logPath(), logPath()+".1")
	}

	f, err := os.OpenFile(logPath(), os.O_CREATE|os.O_APPEND|os.O_WRONLY, 0o600)
	if err != nil {
		return
	}
	defer f.Close()
	fmt.Fprintf(f, "%s [%s] %s\n", time.Now().Format(time.RFC3339), level, msg)
}
