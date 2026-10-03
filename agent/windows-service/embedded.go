package main

import (
	"encoding/binary"
	"encoding/json"
	"errors"
	"io"
	"os"
)

// embeddedMarker must match AgentTokenController::EXE_KENNUNG. DokuVault
// appends JSON + uint32 length (little endian) + marker to the exe it hands
// out after creating a token; Windows ignores data after the PE image.
const embeddedMarker = "DVCFG001"

type embeddedConfig struct {
	URL   string `json:"url"`
	Token string `json:"token"`
}

// readEmbedded looks for a configuration at the end of the given file.
// ok is false for a plain exe without one - not an error.
func readEmbedded(path string) (cfg embeddedConfig, ok bool, err error) {
	f, err := os.Open(path)
	if err != nil {
		return cfg, false, err
	}
	defer f.Close()

	fi, err := f.Stat()
	if err != nil {
		return cfg, false, err
	}
	tail := int64(len(embeddedMarker) + 4)
	if fi.Size() < tail {
		return cfg, false, nil
	}

	buf := make([]byte, tail)
	if _, err := f.ReadAt(buf, fi.Size()-tail); err != nil {
		return cfg, false, err
	}
	if string(buf[4:]) != embeddedMarker {
		return cfg, false, nil
	}

	n := int64(binary.LittleEndian.Uint32(buf[:4]))
	if n <= 0 || n > 64<<10 || n > fi.Size()-tail {
		return cfg, false, errors.New("eingebettete Konfiguration beschaedigt")
	}

	data := make([]byte, n)
	if _, err := f.ReadAt(data, fi.Size()-tail-n); err != nil && err != io.EOF {
		return cfg, false, err
	}
	if err := json.Unmarshal(data, &cfg); err != nil {
		return cfg, false, errors.New("eingebettete Konfiguration beschaedigt")
	}
	return cfg, true, nil
}
