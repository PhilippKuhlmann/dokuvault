#!/usr/bin/env bash
# Builds the DokuVault Windows service (agent/windows-service) into
# public/downloads/dokuvault-agent.exe, which the agent page offers for
# download. Committed like public/build - the server needs no Go.
set -euo pipefail

#
#   scripts/build-windows-agent.sh                  build
#   scripts/build-windows-agent.sh --if-outdated    build only if the sources
#       changed since the exe was built, and only if Go is installed (deploy.sh)

root="$(cd "$(dirname "$0")/.." && pwd)"
cd "${root}/agent/windows-service"

# The version comes from the last commit of the sources, not from the clock:
# building the same sources twice - here and again on the server - gives
# the same version, so installed services do not update for nothing. In UTC,
# so a Mac and a server in another time zone agree.
if [ -n "$(git status --porcelain -- .)" ]; then
    echo "agent/windows-service has uncommitted changes - commit first, the version comes from the commit" >&2
    exit 1
fi
version="$(TZ=UTC git log -1 --date=format-local:%y.%m.%d-%H%M --format=%cd-%h -- .)"
# Numeric for the Windows file properties: 26.10.03 -> 26.10.3.0
IFS=. read -r yy mm dd <<<"${version%%-*}"
numeric="${yy}.$((10#${mm})).$((10#${dd})).0"

if [ "${1:-}" = "--if-outdated" ]; then
    if [ "$(cat ../../public/downloads/dokuvault-agent.version 2>/dev/null)" = "${version}" ]; then
        echo "Windows agent up to date (${version})"
        exit 0
    fi
    if ! command -v go >/dev/null; then
        echo "!!! Windows agent outdated, but Go is not installed - keeping the committed exe" >&2
        exit 0
    fi
fi

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
