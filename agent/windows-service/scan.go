package main

// The network scan: printers and UPS in the networks chosen on the agent
// page, found by SNMP. Unlike the other roles this is no PowerShell script -
// asking 1024 addresses one after another would take minutes there, and
// Windows has no usable SNMP client of its own.
//
// Only SNMP, no ping and no ARP: what is recognised here (Printer-MIB,
// UPS-MIB, APC) answers SNMP anyway, and an address that does not answer
// is of no interest. Behind a router that is all there is in any case - a
// MAC address never crosses one.
//
// Documentation, not monitoring: identity and firmware, never toner,
// counters or battery state.

import (
	"bytes"
	"encoding/json"
	"fmt"
	"net/http"
	"net/netip"
	"sort"
	"strings"
	"sync"
	"time"
)

// scanJob is what DokuVault hands out with the checkin when a scan is due.
type scanJob struct {
	Networks    []string `json:"networks"`
	Communities []string `json:"communities"`
}

// scannedDevice is one printer or UPS as DokuVault takes it.
type scannedDevice struct {
	Type         string `json:"type"`
	IP           string `json:"ip"`
	Serial       string `json:"serial,omitempty"`
	MAC          string `json:"mac,omitempty"`
	Name         string `json:"name,omitempty"`
	Manufacturer string `json:"manufacturer,omitempty"`
	Model        string `json:"model,omitempty"`
	Firmware     string `json:"firmware,omitempty"`
}

const (
	// scanMinPrefix: the largest network taken, as on the server - a /22.
	scanMinPrefix = 22
	// scanMaxAddresses: across all networks of one job.
	scanMaxAddresses = 4096
	// scanWorkers: addresses asked at the same time.
	scanWorkers = 64
	// scanMaxCommunities: as on the server.
	scanMaxCommunities = 5
)

// snmpValue is one answer: the OID it belongs to and its value as text.
// Raw keeps the bytes of an OCTET STRING - a MAC address is not text.
type snmpValue struct {
	OID  string
	Text string
	Raw  []byte
}

// snmpClient asks one device. get returns only the OIDs that exist;
// next returns the first value after oid (one step of a walk).
type snmpClient interface {
	get(oids ...string) (map[string]snmpValue, error)
	next(oid string) (snmpValue, error)
	close()
}

// dialSNMP opens a client for one address and community. Replaced in tests.
var dialSNMP = func(addr netip.Addr, community string) (snmpClient, error) {
	return newGoSNMPClient(addr, community)
}

// OIDs. System group first: what answers there speaks SNMP at all.
const (
	oidSysDescr    = "1.3.6.1.2.1.1.1.0"
	oidSysObjectID = "1.3.6.1.2.1.1.2.0"
	oidSysName     = "1.3.6.1.2.1.1.5.0"

	// Printer-MIB (RFC 3805). The table index differs between vendors, so
	// these are walked one step instead of read at ".1".
	oidPrinterMIB      = "1.3.6.1.2.1.43"
	oidPrtSerial       = "1.3.6.1.2.1.43.5.1.1.17"
	oidPrtFirmware     = "1.3.6.1.2.1.43.15.1.1.6"
	oidHrDeviceDescr   = "1.3.6.1.2.1.25.3.2.1.3"
	oidEntSerial       = "1.3.6.1.2.1.47.1.1.1.1.11"
	oidEntFirmware     = "1.3.6.1.2.1.47.1.1.1.1.9"
	oidIfPhysAddress   = "1.3.6.1.2.1.2.2.1.6"
	oidEnterprisesRoot = "1.3.6.1.4.1."

	// UPS-MIB (RFC 1628).
	oidUpsManufacturer = "1.3.6.1.2.1.33.1.1.1.0"
	oidUpsModel        = "1.3.6.1.2.1.33.1.1.2.0"
	oidUpsSoftware     = "1.3.6.1.2.1.33.1.1.3.0"
	oidUpsAgentSw      = "1.3.6.1.2.1.33.1.1.4.0"

	// APC PowerNet-MIB - APC network cards often answer only here.
	oidApcModel    = "1.3.6.1.4.1.318.1.1.1.1.1.1.0"
	oidApcFirmware = "1.3.6.1.4.1.318.1.1.1.1.2.1.0"
	oidApcSerial   = "1.3.6.1.4.1.318.1.1.1.1.2.3.0"
)

// vendors by the enterprise number in sysObjectID - SNMP has no standard
// "manufacturer" for printers.
var vendors = map[string]string{
	"11":    "HP",
	"236":   "Samsung",
	"253":   "Xerox",
	"318":   "APC",
	"367":   "Ricoh",
	"534":   "Eaton",
	"641":   "Lexmark",
	"705":   "Eaton",
	"935":   "PowerWalker",
	"1129":  "Toshiba",
	"1248":  "Epson",
	"1347":  "Kyocera",
	"1602":  "Canon",
	"2001":  "OKI",
	"2385":  "Sharp",
	"2435":  "Brother",
	"18334": "Konica Minolta",
	"26696": "Riello",
}

// expandNetworks turns the job's networks into addresses: without network
// and broadcast address, nothing larger than a /22, IPv4 only, each address
// once, at most scanMaxAddresses.
func expandNetworks(networks []string) ([]netip.Addr, error) {
	seen := map[netip.Addr]bool{}
	var out []netip.Addr

	for _, n := range networks {
		prefix, err := netip.ParsePrefix(strings.TrimSpace(n))
		if err != nil || !prefix.Addr().Is4() {
			return nil, fmt.Errorf("Netz %q ist kein IPv4-Netz", n)
		}
		if prefix.Bits() < scanMinPrefix {
			return nil, fmt.Errorf("Netz %s ist groesser als /%d", n, scanMinPrefix)
		}
		prefix = prefix.Masked()

		first, last := prefix.Addr(), lastAddr(prefix)
		if prefix.Bits() <= 30 {
			first, last = first.Next(), last.Prev()
		}
		for a := first; a.IsValid() && a.Compare(last) <= 0; a = a.Next() {
			if seen[a] {
				continue
			}
			if len(out) >= scanMaxAddresses {
				return out, nil
			}
			seen[a] = true
			out = append(out, a)
		}
	}
	return out, nil
}

func lastAddr(p netip.Prefix) netip.Addr {
	b := p.Addr().As4()
	host := 32 - p.Bits()
	v := uint32(b[0])<<24 | uint32(b[1])<<16 | uint32(b[2])<<8 | uint32(b[3])
	v |= (1 << host) - 1
	return netip.AddrFrom4([4]byte{byte(v >> 24), byte(v >> 16), byte(v >> 8), byte(v)})
}

// scanAddresses asks every address, scanWorkers at a time, and returns
// what it recognised, sorted by address.
func scanAddresses(addrs []netip.Addr, communities []string) []scannedDevice {
	if len(communities) == 0 {
		communities = []string{"public"}
	}
	if len(communities) > scanMaxCommunities {
		communities = communities[:scanMaxCommunities]
	}

	jobs := make(chan netip.Addr)
	var mu sync.Mutex
	var found []scannedDevice
	var wg sync.WaitGroup

	for i := 0; i < scanWorkers; i++ {
		wg.Add(1)
		go func() {
			defer wg.Done()
			for a := range jobs {
				if d := probe(a, communities); d != nil {
					mu.Lock()
					found = append(found, *d)
					mu.Unlock()
				}
			}
		}()
	}
	for _, a := range addrs {
		jobs <- a
	}
	close(jobs)
	wg.Wait()

	sort.Slice(found, func(i, j int) bool {
		return netip.MustParseAddr(found[i].IP).Less(netip.MustParseAddr(found[j].IP))
	})
	return found
}

// probe tries the communities in turn until one answers, then recognises
// the device. nil: no answer, or neither printer nor UPS.
func probe(addr netip.Addr, communities []string) *scannedDevice {
	for _, community := range communities {
		c, err := dialSNMP(addr, community)
		if err != nil {
			continue
		}
		system, err := c.get(oidSysObjectID, oidSysName, oidSysDescr)
		if err != nil || len(system) == 0 {
			c.close()
			continue
		}
		d := recognise(c, system)
		c.close()
		if d != nil {
			d.IP = addr.String()
		}
		return d
	}
	return nil
}

// recognise decides what the device is and reads what the documentation
// needs. A printer has the Printer-MIB; a UPS the UPS-MIB or APC's own.
func recognise(c snmpClient, system map[string]snmpValue) *scannedDevice {
	vendor := vendorOf(system[oidSysObjectID].Text)
	name := clean(system[oidSysName].Text)

	if v, err := c.next(oidPrinterMIB); err == nil && within(v.OID, oidPrinterMIB) {
		d := &scannedDevice{Type: "printer", Name: name, Manufacturer: vendor}
		d.Serial = firstUnder(c, oidPrtSerial)
		if d.Serial == "" {
			d.Serial = firstUnder(c, oidEntSerial)
		}
		d.Model = firstUnder(c, oidHrDeviceDescr)
		if d.Model == "" {
			d.Model = clean(system[oidSysDescr].Text)
		}
		d.Model = withoutVendor(d.Model, d.Manufacturer)
		d.Firmware = firstUnder(c, oidPrtFirmware)
		if d.Firmware == "" {
			d.Firmware = firstUnder(c, oidEntFirmware)
		}
		if d.Serial == "" {
			d.MAC = firstMAC(c)
		}
		return d
	}

	ups, err := c.get(oidUpsManufacturer, oidUpsModel, oidUpsSoftware, oidUpsAgentSw, oidApcModel, oidApcFirmware, oidApcSerial)
	if err != nil {
		return nil
	}
	model := firstText(ups, oidApcModel, oidUpsModel)
	if model == "" {
		return nil
	}
	d := &scannedDevice{
		Type:         "ups",
		Name:         name,
		Manufacturer: firstText(ups, oidUpsManufacturer),
		Model:        model,
		Serial:       firstText(ups, oidApcSerial),
		Firmware:     firstText(ups, oidApcFirmware, oidUpsSoftware, oidUpsAgentSw),
	}
	if d.Manufacturer == "" {
		d.Manufacturer = vendor
	}
	d.Model = withoutVendor(d.Model, d.Manufacturer)
	if d.Serial == "" {
		d.Serial = firstUnder(c, oidEntSerial)
	}
	if d.Serial == "" {
		d.MAC = firstMAC(c)
	}
	return d
}

// vendorOf: "1.3.6.1.4.1.11.2.3.9.1" -> "HP".
func vendorOf(objectID string) string {
	id := strings.TrimPrefix(objectID, ".")
	if !strings.HasPrefix(id, oidEnterprisesRoot) {
		return ""
	}
	number, _, _ := strings.Cut(strings.TrimPrefix(id, oidEnterprisesRoot), ".")
	return vendors[number]
}

// within: whether oid lies below root (not merely starts with its digits:
// 1.3.6.1.2.1.430 is not below 1.3.6.1.2.1.43).
func within(oid, root string) bool {
	return strings.HasPrefix(strings.TrimPrefix(oid, "."), root+".")
}

// firstUnder: the first non-empty value of a table column, walking a few
// steps - the first row is sometimes empty (a hub, a virtual device).
func firstUnder(c snmpClient, root string) string {
	oid := root
	for i := 0; i < 8; i++ {
		v, err := c.next(oid)
		if err != nil || !within(v.OID, root) {
			return ""
		}
		if t := clean(v.Text); t != "" {
			return t
		}
		oid = v.OID
	}
	return ""
}

// firstMAC: the first real hardware address in ifPhysAddress - the
// loopback interface has none.
func firstMAC(c snmpClient) string {
	oid := oidIfPhysAddress
	for i := 0; i < 16; i++ {
		v, err := c.next(oid)
		if err != nil || !within(v.OID, oidIfPhysAddress) {
			return ""
		}
		if len(v.Raw) == 6 && !bytes.Equal(v.Raw, make([]byte, 6)) {
			return fmt.Sprintf("%02x:%02x:%02x:%02x:%02x:%02x", v.Raw[0], v.Raw[1], v.Raw[2], v.Raw[3], v.Raw[4], v.Raw[5])
		}
		oid = v.OID
	}
	return ""
}

func firstText(values map[string]snmpValue, oids ...string) string {
	for _, o := range oids {
		if t := clean(values[o].Text); t != "" {
			return t
		}
	}
	return ""
}

// clean: printable text without padding. Some devices pad with blanks or
// end their strings in NUL bytes.
func clean(s string) string {
	s = strings.Map(func(r rune) rune {
		if r < 32 || r == 127 {
			return -1
		}
		return r
	}, s)
	return strings.TrimSpace(s)
}

// withoutVendor: "HP LaserJet M480" with manufacturer HP -> "LaserJet M480".
func withoutVendor(model, vendor string) string {
	if vendor == "" {
		return model
	}
	if rest, ok := strings.CutPrefix(model, vendor+" "); ok {
		return strings.TrimSpace(rest)
	}
	return model
}

// runScan scans, sends what it found and reports the outcome as the role
// "network-scan" - shown on the agent page like every other role.
func runScan(cfg Config, job scanJob, log func(level, msg string)) {
	started := time.Now()
	result := runResult{Role: "network-scan", OK: true}

	addrs, err := expandNetworks(job.Networks)
	if err == nil {
		found := scanAddresses(addrs, job.Communities)
		err = sendScan(cfg, found)
		result.Message = scanSummary(found, len(addrs), len(job.Networks), time.Since(started))
	}
	if err != nil {
		result.OK = false
		result.Message = strings.TrimSpace(result.Message + "\n" + err.Error())
		log("error", "network-scan: "+err.Error())
	} else {
		log("info", "network-scan: "+result.Message)
	}

	if err := sendReport(cfg, []runResult{result}); err != nil {
		log("warn", "Ergebnis nicht gemeldet: "+err.Error())
	}
}

func scanSummary(found []scannedDevice, addresses, networks int, took time.Duration) string {
	printers, ups := 0, 0
	for _, d := range found {
		if d.Type == "printer" {
			printers++
		} else {
			ups++
		}
	}
	return fmt.Sprintf("%d Drucker, %d USV - %d Adressen in %d Netzen, %s",
		printers, ups, addresses, networks, took.Round(time.Second))
}

// sendScan hands the result to DokuVault, which updates known devices and
// lists new ones under "Neu gefunden".
func sendScan(cfg Config, found []scannedDevice) error {
	if found == nil {
		found = []scannedDevice{}
	}
	payload, err := json.Marshal(map[string]any{
		"machine_id": machineID(),
		"devices":    found,
	})
	if err != nil {
		return err
	}

	endpoint := strings.TrimRight(cfg.URL, "/") + "/api/agent/network-scan"
	req, err := http.NewRequest(http.MethodPost, endpoint, bytes.NewReader(payload))
	if err != nil {
		return err
	}
	req.Header.Set("Authorization", "Bearer "+cfg.Token)
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Accept", "application/json")
	req.Header.Set("User-Agent", "DokuVault-Agent/"+version)

	resp, err := httpClient.Do(req)
	if err != nil {
		return fmt.Errorf("DokuVault nicht erreichbar (%s): %w", endpoint, err)
	}
	defer resp.Body.Close()

	switch resp.StatusCode {
	case http.StatusOK:
		return nil
	case http.StatusUnauthorized:
		return errUnauthorized
	default:
		return fmt.Errorf("Scan-Ergebnis: unerwartete Antwort %d", resp.StatusCode)
	}
}
