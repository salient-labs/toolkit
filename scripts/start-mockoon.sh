#!/usr/bin/env bash

set -euo pipefail

# die [<message>]
function die() {
    local s=$?
    printf '%s: %s\n' "${0##*/}" "${1-command failed}" >&2
    ((!s)) && exit 1 || exit "$s"
}

[[ ${BASH_SOURCE[0]} -ef scripts/start-mockoon.sh ]] ||
    die "must run from root of package folder"

seconds=${3-60}
[[ -r ${1-} ]] && [[ ${2-} =~ ^[1-9][0-9]+$ ]] && [[ $seconds =~ ^[0-9]+$ ]] ||
    die "usage: ${0##*/} <data_file> <port> [<seconds>]"

mockoon_path=tools/mockoon-cli/node_modules/.bin/mockoon-cli
[[ -x $mockoon_path ]] ||
    die "mockoon-cli is not installed"

if curl -sI "http://localhost:$2" &>/dev/null; then
    die "port $2 is already in use"
fi

"$mockoon_path" start --data "$1" --port "$2" --log-transaction &

i=-1
while :; do
    if ! jobs %% &>/dev/null; then
        status=0
        wait "$!" || status=$?
        die "mockoon-cli returned exit status $status"
    fi
    if ((++i == seconds)); then
        die "mockoon-cli server unresponsive after $i seconds"
    fi
    if curl -sI "http://localhost:$2" >/dev/null; then
        printf 'mockoon-cli server up after %d seconds\n' "$i"
        break
    fi
    sleep 1
done

disown -h
