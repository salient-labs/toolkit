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
    local s=0
    "$@" || s=$?
    printf '\n' >&2
    return "$s"
}

[[ ${BASH_SOURCE[0]} -ef scripts/update-changelog.sh ]] ||
    die "must run from root of package folder"

changelog_path=tools/changelog
[[ -x $changelog_path ]] ||
    die "salient/changelog is not installed"

run "$changelog_path" \
    --output CHANGELOG.md \
    lkrms/php-util
