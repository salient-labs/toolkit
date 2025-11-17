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

function run_with_php_versions() {
    local php versions=()
    while [[ -z $1 ]] || [[ $1 == [78][0-9] ]]; do
        if type -P "php$1" >/dev/null; then
            versions[${#versions[@]}]=php$1
        fi
        shift
    done
    for php in "${versions[@]-php}"; do
        run "$php" "$@" || failed[${#failed[@]}]="$* on $php"
    done
}

function on_exit() {
    run scripts/stop-mockoon.sh || true

    if [[ ${failed+1} ]]; then
        printf '==> FAILED:\n'
        printf -- '- %s\n' "${failed[@]}"
        printf '\n'
    fi >&2
}

[[ ${BASH_SOURCE[0]} -ef scripts/run-tests.sh ]] ||
    die "must run from root of package folder"

failed=()

run scripts/generate.php --check
run php84 tools/php-cs-fixer check --diff --verbose
run tools/pretty-php --diff
run_with_php_versions 84 74 83 82 81 80 vendor/bin/phpstan
run scripts/stop-mockoon.sh || (($? == 1)) || die 'error stopping mockoon'
run scripts/start-mockoon.sh >/dev/null
trap on_exit EXIT
run_with_php_versions 84 74 83 82 81 80 vendor/bin/phpunit

[[ -z ${failed+1} ]]
