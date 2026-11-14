package main

import (
	"encoding/json"
	"errors"
	"io"
	"net/http"
	"net/http/httptest"
	"net/netip"
	"sort"
	"strconv"
	"strings"
	"sync"
	"testing"
)

// fakeDevice answers like an SNMP agent from a fixed table.
type fakeDevice map[string]snmpValue

func text(oid, s string) snmpValue { return snmpValue{OID: oid, Text: s, Raw: []byte(s)} }

func (d fakeDevice) with(values ...snmpValue) fakeDevice {
	for _, v := range values {
		d[v.OID] = v
	}
	return d
}

type fakeClient struct{ d fakeDevice }

func (c fakeClient) get(oids ...string) (map[string]snmpValue, error) {
	out := map[string]snmpValue{}
	for _, o := range oids {
		if v, ok := c.d[o]; ok {
			out[o] = v
		}
	}
	return out, nil
}

func (c fakeClient) next(oid string) (snmpValue, error) {
	keys := make([]string, 0, len(c.d))
	for k := range c.d {
		keys = append(keys, k)
	}
	sort.Slice(keys, func(i, j int) bool { return oidLess(keys[i], keys[j]) })
	for _, k := range keys {
		if oidLess(oid, k) {
			return c.d[k], nil
		}
	}
	return snmpValue{}, errors.New("end of mib")
}

func (c fakeClient) close() {}

// oidLess compares OIDs number by number, as an SNMP agent orders them.
func oidLess(a, b string) bool {
	pa, pb := strings.Split(a, "."), strings.Split(b, ".")
	for i := 0; i < len(pa) && i < len(pb); i++ {
		x, _ := strconv.Atoi(pa[i])
		y, _ := strconv.Atoi(pb[i])
		if x != y {
			return x < y
		}
	}
	return len(pa) < len(pb)
}

func hpPrinter() fakeDevice {
	return fakeDevice{}.with(
		text(oidSysDescr, "HP ETHERNET MULTI-ENVIRONMENT"),
		snmpValue{OID: oidSysObjectID, Text: "1.3.6.1.4.1.11.2.3.9.1"},
		text(oidSysName, "HP-OG2 "),
		text("1.3.6.1.2.1.2.2.1.6.1", ""),
		snmpValue{OID: "1.3.6.1.2.1.2.2.1.6.2", Raw: []byte{0x3c, 0x52, 0x82, 0x11, 0x22, 0x33}},
		text("1.3.6.1.2.1.25.3.2.1.3.1", "HP LaserJet M480"),
		text("1.3.6.1.2.1.43.5.1.1.1.1", "x"),
		text("1.3.6.1.2.1.43.5.1.1.17.1", "CNB1234567\x00"),
		text("1.3.6.1.2.1.43.15.1.1.6.1.1", "2507252_052345"),
	)
}

func withSystem(objectID, name string, values ...snmpValue) fakeDevice {
	return fakeDevice{}.with(
		snmpValue{OID: oidSysObjectID, Text: objectID},
		text(oidSysName, name),
	).with(values...)
}

func recogniseFake(d fakeDevice) *scannedDevice {
	system, _ := fakeClient{d}.get(oidSysObjectID, oidSysName, oidSysDescr)
	return recognise(fakeClient{d}, system)
}

func TestRecognisesAPrinter(t *testing.T) {
	got := recogniseFake(hpPrinter())

	want := scannedDevice{Type: "printer", Name: "HP-OG2", Manufacturer: "HP", Model: "LaserJet M480", Serial: "CNB1234567", Firmware: "2507252_052345"}
	if got == nil || *got != want {
		t.Fatalf("got %+v, want %+v", got, want)
	}
}

func TestPrinterWithoutSerialIsKnownByItsMAC(t *testing.T) {
	d := hpPrinter()
	delete(d, "1.3.6.1.2.1.43.5.1.1.17.1")
	got := recogniseFake(d)

	// The loopback interface (empty address) is skipped.
	if got == nil || got.Serial != "" || got.MAC != "3c:52:82:11:22:33" {
		t.Fatalf("got %+v", got)
	}
}

func TestRecognisesAnAPCCard(t *testing.T) {
	got := recogniseFake(withSystem("1.3.6.1.4.1.318.1.3.27", "USV-Serverraum",
		text(oidApcModel, "Smart-UPS 1500"),
		text(oidApcSerial, "AS1234567890"),
		text(oidApcFirmware, "UPS 09.3 (ID18)"),
	))

	want := scannedDevice{Type: "ups", Name: "USV-Serverraum", Manufacturer: "APC", Model: "Smart-UPS 1500", Serial: "AS1234567890", Firmware: "UPS 09.3 (ID18)"}
	if got == nil || *got != want {
		t.Fatalf("got %+v, want %+v", got, want)
	}
}

func TestRecognisesAUPSByTheStandardMIB(t *testing.T) {
	got := recogniseFake(withSystem("1.3.6.1.4.1.705.1", "",
		text(oidUpsManufacturer, "EATON"),
		text(oidUpsModel, "EATON 5PX 1500"),
		text(oidUpsSoftware, "INV: 01.14.0026"),
		text("1.3.6.1.2.1.47.1.1.1.1.11.1", "G118F12345"),
	))

	want := scannedDevice{Type: "ups", Manufacturer: "EATON", Model: "5PX 1500", Serial: "G118F12345", Firmware: "INV: 01.14.0026"}
	if got == nil || *got != want {
		t.Fatalf("got %+v, want %+v", got, want)
	}
}

func TestIgnoresEverythingElse(t *testing.T) {
	// A switch: answers SNMP, but neither Printer- nor UPS-MIB.
	if got := recogniseFake(withSystem("1.3.6.1.4.1.9.1.1208", "sw-core", text("1.3.6.1.2.1.2.2.1.2.1", "Gi1/0/1"))); got != nil {
		t.Fatalf("got %+v", got)
	}
}

func TestWithinComparesWholeNumbers(t *testing.T) {
	if !within("1.3.6.1.2.1.43.5.1", oidPrinterMIB) || within("1.3.6.1.2.1.430.1", oidPrinterMIB) || within(oidPrinterMIB, oidPrinterMIB) {
		t.Fatal("within is wrong")
	}
}

func TestExpandNetworks(t *testing.T) {
	cases := map[string]int{"192.0.2.0/24": 254, "192.0.2.4/30": 2, "192.0.2.9/32": 1, "10.0.0.0/22": 1022}
	for n, want := range cases {
		got, err := expandNetworks([]string{n})
		if err != nil || len(got) != want {
			t.Errorf("%s: %d addresses, %v", n, len(got), err)
		}
	}

	// Host bits are cut off; each address once.
	got, _ := expandNetworks([]string{"192.0.2.77/24", "192.0.2.0/25"})
	if len(got) != 254 || got[0].String() != "192.0.2.1" || got[253].String() != "192.0.2.254" {
		t.Fatalf("got %d addresses from %s to %s", len(got), got[0], got[len(got)-1])
	}

	for _, bad := range []string{"10.0.0.0/16", "2001:db8::/120", "kein Netz"} {
		if _, err := expandNetworks([]string{bad}); err == nil {
			t.Errorf("%s should be refused", bad)
		}
	}
}

// fakeNetwork replaces dialSNMP: addresses with a device answer with the
// right community, everything else stays silent.
func fakeNetwork(t *testing.T, devices map[string]fakeDevice, community string) {
	t.Helper()
	old := dialSNMP
	dialSNMP = func(addr netip.Addr, c string) (snmpClient, error) {
		d, ok := devices[addr.String()]
		if !ok || c != community {
			return silent{}, nil
		}
		return fakeClient{d}, nil
	}
	t.Cleanup(func() { dialSNMP = old })
}

type silent struct{}

func (silent) get(...string) (map[string]snmpValue, error) { return nil, errors.New("timeout") }
func (silent) next(string) (snmpValue, error)              { return snmpValue{}, errors.New("timeout") }
func (silent) close()                                      {}

func TestScanTriesEveryCommunity(t *testing.T) {
	fakeNetwork(t, map[string]fakeDevice{
		"192.0.2.20": hpPrinter(),
		"192.0.2.5":  withSystem("1.3.6.1.4.1.318.1.3.27", "USV", text(oidApcModel, "Smart-UPS 750"), text(oidApcSerial, "AS1")),
	}, "geheim")

	addrs, _ := expandNetworks([]string{"192.0.2.0/24"})
	found := scanAddresses(addrs, []string{"public", "geheim"})

	if len(found) != 2 || found[0].IP != "192.0.2.5" || found[1].IP != "192.0.2.20" || found[1].Type != "printer" {
		t.Fatalf("got %+v", found)
	}
}

func TestADueScanIsSentAndReported(t *testing.T) {
	useTempDir(t)
	fakeNetwork(t, map[string]fakeDevice{"192.0.2.2": hpPrinter()}, "public")

	var mu sync.Mutex
	var scanned []scannedDevice
	var reported []runResult

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		body, _ := io.ReadAll(r.Body)
		mu.Lock()
		defer mu.Unlock()
		switch r.URL.Path {
		case "/api/agent/checkin":
			// Scripts not due, but a scan is - it runs on its own.
			w.Write([]byte(`{"roles":["windows-server","network-scan"],"run":false,"scan":{"networks":["192.0.2.0/30"],"communities":["public"]}}`))
		case "/api/agent/network-scan":
			var p struct{ Devices []scannedDevice }
			json.Unmarshal(body, &p)
			scanned = p.Devices
			w.Write([]byte(`{"status":"ok"}`))
		case "/api/agent/report":
			var p struct{ Results []runResult }
			json.Unmarshal(body, &p)
			reported = append(reported, p.Results...)
			w.Write([]byte(`{"status":"ok"}`))
		default:
			t.Errorf("unexpected request %s", r.URL.Path)
			w.WriteHeader(http.StatusNotFound)
		}
	}))
	defer srv.Close()

	did, err := runIfDue(validConfig(srv.URL), func(string, string) {}, false)
	if err != nil || did {
		t.Fatalf("did %v, err %v", did, err)
	}
	if len(scanned) != 1 || scanned[0].Serial != "CNB1234567" || scanned[0].IP != "192.0.2.2" {
		t.Fatalf("scanned %+v", scanned)
	}
	if len(reported) != 1 || reported[0].Role != "network-scan" || !reported[0].OK || !strings.HasPrefix(reported[0].Message, "1 Drucker, 0 USV") {
		t.Fatalf("reported %+v", reported)
	}
}
