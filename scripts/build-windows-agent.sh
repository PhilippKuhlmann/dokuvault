#!/usr/bin/env bash
# Builds the DokuVault Windows service (agent/windows-service) into
# public/downloads/dokuvault-agent.exe, which the agent page offers for
# download. Committed like public/build - the server needs no Go.
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
cd "${root}/agent/windows-service"

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

out=../../public/downloads/dokuvault-agent.exe
GOOS=windows GOARCH=amd64 CGO_ENABLED=0 \
    go build -trimpath -ldflags "-s -w -X main.version=${version}" \
    -o "${out}.unsigned" .

# Code signing (Authenticode) - optional. Without it SmartScreen and some
# virus scanners warn about the download. Set in scripts/signing.env (not in
# git) or the environment:
#   SIGN_PFX=/path/to/certificate.pfx       code signing certificate
#   SIGN_PASSWORD_FILE=/path/to/password    its password, one line
#   SIGN_TIMESTAMP=http://timestamp.digicert.com   (default)
# The timestamp keeps the signature valid after the certificate expires.
# DokuVault appends URL and token to the exe on download; it puts them
# inside the signature table so the signature stays valid
# (App\Support\ExeTag).
signing_env="${root}/scripts/signing.env"
[ -f "${signing_env}" ] && . "${signing_env}"
if [ -n "${SIGN_PFX:-}" ]; then
    command -v osslsigncode >/dev/null || { echo "osslsigncode missing (brew install osslsigncode)" >&2; exit 1; }
    rm -f "${out}"   # osslsigncode does not overwrite
    osslsigncode sign -pkcs12 "${SIGN_PFX}" -readpass "${SIGN_PASSWORD_FILE:?SIGN_PASSWORD_FILE missing}" \
        -n "DokuVault Agent" -i "https://github.com/PhilippKuhlmann/dokuvault" \
        -t "${SIGN_TIMESTAMP:-http://timestamp.digicert.com}" -h sha256 \
        -in "${out}.unsigned" -out "${out}" >/dev/null
    rm "${out}.unsigned"
    osslsigncode verify -in "${out}" >/dev/null 2>&1 \
        || echo "!!! signed, but the chain does not verify here (self-signed test certificate?)" >&2
    echo "signed with ${SIGN_PFX}"
else
    mv "${out}.unsigned" "${out}"
    echo "!!! unsigned - set SIGN_PFX to sign" >&2
fi

# Installed services compare against this (GET /api/agent/update/windows)
# and replace themselves when it differs.
printf '%s\n' "${version}" > ../../public/downloads/dokuvault-agent.version

echo "public/downloads/dokuvault-agent.exe (${version})"
