#!/usr/bin/env bash
set -euo pipefail
# Disposable native-Linux Git fixture; no commits, private inputs or database use.
test ! -e "$2"
mkdir -p "$2"
git clone --no-hardlinks --quiet "$1" "$2/release"
git -C "$2/release" status --porcelain -z --untracked-files=all > "$2/clean.bin"
mv "$2/release/storage" "$2/shared-storage"
ln -s ../shared-storage "$2/release/storage"
test -L "$2/release/storage"
test -d "$2/release/storage"
test "$(realpath "$2/release/storage")" = "$(realpath "$2/shared-storage")"
git -C "$2/release" status --porcelain -z --untracked-files=all > "$2/shared.bin"
printf '\nfixture-source-change\n' >> "$2/release/README.md"
git -C "$2/release" status --porcelain -z --untracked-files=all > "$2/source-dirty.bin"
printf 'fixture-unexpected-file\n' > "$2/release/unexpected-source.txt"
git -C "$2/release" status --porcelain -z --untracked-files=all > "$2/untracked.bin"
git -C "$2/release" add -u -- storage
git -C "$2/release" status --porcelain -z --untracked-files=all > "$2/staged.bin"
printf 'Native Linux storage-link fixture captured.\n'
