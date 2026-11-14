package main

import (
	"fmt"
	"net/netip"
	"strings"
	"time"

	"github.com/gosnmp/gosnmp"
)

// SNMP v2c with a short timeout: most addresses of a network do not answer
// at all, and each of those costs timeout x (retries + 1) per community.
// One second and one retry make a /24 take about ten seconds with 64
// workers. v2c only - every printer and UPS card of the last fifteen years
// speaks it; v3 follows when a device needs it.
const (
	snmpTimeout = time.Second
	snmpRetries = 1
)

type goSNMPClient struct {
	g *gosnmp.GoSNMP
}

func newGoSNMPClient(addr netip.Addr, community string) (snmpClient, error) {
	g := &gosnmp.GoSNMP{
		Target:    addr.String(),
		Port:      161,
		Community: community,
		Version:   gosnmp.Version2c,
		Timeout:   snmpTimeout,
		Retries:   snmpRetries,
		MaxOids:   gosnmp.MaxOids,
	}
	if err := g.Connect(); err != nil {
		return nil, err
	}
	return &goSNMPClient{g: g}, nil
}

func (c *goSNMPClient) get(oids ...string) (map[string]snmpValue, error) {
	packet, err := c.g.Get(oids)
	if err != nil {
		return nil, err
	}
	out := map[string]snmpValue{}
	for _, v := range packet.Variables {
		if sv, ok := toValue(v); ok {
			out[sv.OID] = sv
		}
	}
	return out, nil
}

func (c *goSNMPClient) next(oid string) (snmpValue, error) {
	packet, err := c.g.GetNext([]string{oid})
	if err != nil {
		return snmpValue{}, err
	}
	if len(packet.Variables) == 0 {
		return snmpValue{}, fmt.Errorf("keine Antwort auf %s", oid)
	}
	sv, ok := toValue(packet.Variables[0])
	if !ok {
		return snmpValue{}, fmt.Errorf("Ende der MIB nach %s", oid)
	}
	return sv, nil
}

func (c *goSNMPClient) close() {
	if c.g.Conn != nil {
		c.g.Conn.Close()
	}
}

// toValue: one variable as text, or false for "not there" (noSuchObject,
// noSuchInstance, endOfMibView).
func toValue(v gosnmp.SnmpPDU) (snmpValue, bool) {
	sv := snmpValue{OID: strings.TrimPrefix(v.Name, ".")}

	switch v.Type {
	case gosnmp.NoSuchObject, gosnmp.NoSuchInstance, gosnmp.EndOfMibView, gosnmp.Null:
		return sv, false
	case gosnmp.OctetString:
		b, _ := v.Value.([]byte)
		sv.Raw = b
		sv.Text = string(b)
	case gosnmp.ObjectIdentifier:
		s, _ := v.Value.(string)
		sv.Text = strings.TrimPrefix(s, ".")
	default:
		sv.Text = fmt.Sprint(v.Value)
	}
	return sv, true
}
