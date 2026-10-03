#!/usr/bin/env bash
# Builds the DokuVault Windows service (agent/windows-service) into
# public/downloads/dokuvault-agent.exe, which the agent page offers for
# download. Committed like public/build - the server needs no Go.
set -euo pipefail

cd "$(dirname "$0")/../agent/windows-service"

version="$(date +%y.%m.%d-%H%M)-$(git rev-parse --short HEAD)"
# Numeric for the Windows file properties: 26.10.03 -> 26.10.3.0
numeric="$(date +%y).$((10#$(date +%m))).$((10#$(date +%d))).0"

go vet ./...
GOOS=windows GOARCH=amd64 go vet ./...
go test ./...

# Logo and file properties (winres/): written into rsrc_windows_amd64.syso,
# which go build links into the Windows exe by itself.
go run github.com/tc-hib/go-winres@v0.3.3 make --arch amd64 \
    --file-version "${numeric}" --product-version "${numeric}"

GOOS=windows GOARCH=amd64 CGO_ENABLED=0 \
    go build -trimpath -ldflags "-s -w -X main.version=${version}" \
    -o ../../public/downloads/dokuvault-agent.exe .

# Installed services compare against this (GET /api/agent/update/windows)
# and replace themselves when it differs.
printf '%s\n' "${version}" > ../../public/downloads/dokuvault-agent.version

echo "public/downloads/dokuvault-agent.exe (${version})"
