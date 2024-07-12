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

# split <component> <package>
function split() {
    local ref
    ref=$(do_split "$local_prefix$1" "$2") || return
    if [[ -z $tag ]]; then
        run git push --force \
            "$remote_prefix$2" \
            "$ref:refs/heads/$release_branch"
    else
        run git tag --no-sign --force "$tag" "$ref" &&
            run git push --force \
                "$remote_prefix$2" \
                "$tag"
    fi
}

# do_split <dir> <branch>
function do_split() {
    if ((has_splitsh_lite)); then
        run splitsh-lite -prefix "$1" -target "refs/heads/$2"
    else
        run git subtree split -P "$1" -b "$2"
    fi
}

[[ ${BASH_SOURCE[0]} -ef scripts/split.sh ]] ||
    die "must run from root of package folder"

tag=${1-}
[[ $tag == v[0-9]* ]] || tag=

release_branch=main
local_prefix=src/Toolkit/
remote_prefix=https://github.com/salient-labs/toolkit-

has_splitsh_lite=1
type -P splitsh-lite >/dev/null || has_splitsh_lite=0

dirty=$(git status --porcelain)
[[ -z $dirty ]] ||
    die "working tree is dirty"

ref=$(git rev-parse --verify HEAD)
if [[ -z $tag ]]; then
    branch=$(git rev-parse --abbrev-ref HEAD)
    [[ $branch == "$release_branch" ]] ||
        die "invalid branch (expected $release_branch): $branch"

    run git fetch origin

    remote_ref=$(git rev-parse --verify "origin/$release_branch") &&
        [[ $ref == "$remote_ref" ]] ||
        die "$branch is out of sync with origin/$release_branch"
else
    tag_ref=$(git rev-parse --verify "${tag}^{commit}") &&
        [[ $ref == "$tag_ref" ]] ||
        die "$tag is not checked out"

    # Work with a temporary copy of the repo to preserve its tags and branches
    temp_dir=$(mktemp -d)
    trap 'run rm -rf "$temp_dir"' EXIT

    repo_dir=$(pwd -P)
    run cp -a "$repo_dir" "$temp_dir/repo"
    repo_dir=$temp_dir/repo

    git() { command git -C "$repo_dir" "$@"; }
    splitsh-lite() { command splitsh-lite -path "$repo_dir" "$@"; }
fi

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
