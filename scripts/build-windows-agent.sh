#!/usr/bin/env bash
# Builds the DokuVault Windows service (agent/windows-service) into
# public/downloads/dokuvault-agent.exe, which the agent page offers for
# download. Committed like public/build - the server needs no Go.
set -euo pipefail

cd "$(dirname "$0")/../agent/windows-service"

version="$(date +%y.%m.%d)-$(git rev-parse --short HEAD)"

go vet ./...
GOOS=windows GOARCH=amd64 go vet ./...
go test ./...

GOOS=windows GOARCH=amd64 CGO_ENABLED=0 \
    go build -trimpath -ldflags "-s -w -X main.version=${version}" \
    -o ../../public/downloads/dokuvault-agent.exe .

echo "public/downloads/dokuvault-agent.exe (${version})"
