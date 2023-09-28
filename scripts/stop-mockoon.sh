#!/usr/bin/env bash

set -euo pipefail

# die [<message>]
function die() {
    local s=$?
    printf '%s: %s\n' "${0##*/}" "${1-command failed}" >&2
    ((!s)) && exit 1 || exit "$s"
}

[[ ${BASH_SOURCE[0]} -ef scripts/stop-mockoon.sh ]] ||
    die "must run from root of package folder"

mockoon_path=tools/mockoon-cli/node_modules/.bin/mockoon-cli

status=0
pkill -f "$mockoon_path" || status=$?

case "$status" in
0)
    echo "mockoon-cli stopped successfully"
    ;;
1)
    echo "mockoon-cli not running"
    ;;
*)
    die "pkill failed with exit status $status"
    ;;
esac
