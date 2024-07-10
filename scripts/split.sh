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
}

function split() {
    local ref
    ref=$(run git subtree split -P "$local_prefix$1" -b "$2") &&
        run git push "$remote_prefix$2.git" "$ref:refs/heads/$release_branch"
}

[[ ${BASH_SOURCE[0]} -ef scripts/split.sh ]] ||
    die "must run from root of package folder"

release_branch=main
local_prefix=src/Toolkit/
remote_prefix=git@github.com:salient-labs/toolkit-

dirty=$(git status --porcelain)
[[ -z $dirty ]] ||
    die "working tree is dirty"

branch=$(git rev-parse --abbrev-ref HEAD)
[[ $branch == "$release_branch" ]] ||
    die "invalid branch (expected $release_branch): $branch"

run git fetch origin

ref=$(git rev-parse --verify HEAD)
remote_ref=$(git rev-parse --verify "origin/$release_branch")
[[ $ref == "$remote_ref" ]] ||
    die "$branch is out of sync with origin/$release_branch"

{
    split Cache cache
    split Cli cli
    split Collection collections
    split Console console
    split Container container
    split Contract contracts
    split Core core
    split Curler curler
    split Db db
    split Http http
    split Iterator iterators
    split PHPDoc phpdoc
    split PHPStan phpstan
    split Polyfill polyfills
    split Sli sli
    split Sync sync
    split Utility utils

    exit
}
