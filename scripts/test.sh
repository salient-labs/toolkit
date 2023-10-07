#!/usr/bin/env bash

set -euo pipefail

# die [<message>]
function die() {
    local s=$?
    printf '%s: %s\n' "${0##*/}" "${1-command failed}" >&2
    ((!s)) && exit 1 || exit "$s"
}

# run <command> [<argument>...]
function run() {
    printf '==> running:%s\n' "$(printf ' %q' "$@")" >&2
    "$@"
    printf '\n' >&2
}

[[ ${BASH_SOURCE[0]} -ef scripts/test.sh ]] ||
    die "must run from root of package folder"

run scripts/generate.php --check
run vendor/bin/pretty-php --diff
run scripts/stop-mockoon.sh || (($? == 1)) || die 'error stopping mockoon'
run scripts/start-mockoon.sh tests/fixtures/.mockoon/JsonPlaceholderApi.json 3001 >/dev/null
trap 'run scripts/stop-mockoon.sh' EXIT
run vendor/bin/phpunit
