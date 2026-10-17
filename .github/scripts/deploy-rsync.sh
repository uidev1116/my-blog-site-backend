#!/usr/bin/env bash
# Sync the files managed in this repository to an a-blog cms site root with ONE rsync.
#
#   deploy-rsync.sh <destination> [extra rsync options, e.g. --dry-run]
#
# <destination> is user@host:/abs/path/to/site-root (or a local directory for testing).
# Only the paths allow-listed below are sent or deleted; every other file on the receiving side
# (core, config.server.php, .env, license.php, root .htaccess, archives, media, storage, ...)
# is excluded and therefore protected from --delete.
set -euo pipefail

DEST="${1:?destination required}"
shift || true

cd "$(git rev-parse --show-toplevel)"
STAGE="$(mktemp -d)"
# (the temp dir is left for the OS / ephemeral CI runner to clean up)

# 1. Stage: lay the repository out the way it must look on the server.
rsync -a --exclude='node_modules/' --exclude='/extension/plugins/' ablogcms/ "$STAGE/"
mkdir -p "$STAGE/extension/plugins"

PLUGIN_FILTERS=()
for dir in plugins/*/; do
  name=$(basename "$dir")
  src="plugins/$name/src"
  [ -d "$src" ] || { echo "skip plugin $name (no src)"; continue; }
  # An uninitialised submodule would otherwise wipe the plugin on the server.
  [ -n "$(ls -A "$src")" ] || { echo "ERROR: $src is empty" >&2; exit 1; }
  mkdir -p "$STAGE/extension/plugins/$name"
  rsync -a "$src/" "$STAGE/extension/plugins/$name/"
  PLUGIN_FILTERS+=(--include="/extension/plugins/$name/***")
done

# 2. Filters (first match wins). Everything not listed is excluded => protected from --delete.
FILTERS=(
  --exclude='node_modules/' --exclude='.vscode/' --exclude='.eslintcache' --exclude='.stylelintcache'
  --exclude='.env' --exclude='.DS_Store'
  # theme
  --include='/themes/' --include='/themes/develop/***'
  # extension
  --include='/extension/' --include='/extension/acms/***' --include='/extension/schema/***'
  --include='/extension/plugins/'
  "${PLUGIN_FILTERS[@]}"
  # single files
  --include='/config.user.php'
  --include='/private/' --include='/private/config.system.yaml' --include='/private/.htaccess'
  # directory guards (.htaccess only; the data inside these directories is never touched)
  --include='/archives/' --include='/archives/.htaccess'
  # cache/.htaccess is deliberately NOT synced (a-blog cms docs: do not overwrite it on update; the server's differs)
  --include='/cron/' --include='/cron/.htaccess'
  --include='/media/' --include='/media/.htaccess'
  --include='/storage/' --include='/storage/.htaccess'
  --exclude='*'
)

# NOTE: modern rsync refuses to write through symlinks inside the destination, so the managed
# directories on the server must be real directories (the deploy workflow checks this first).
rsync -rlptz --delete --human-readable --itemize-changes --omit-dir-times --chmod=D755,F644 \
  "${FILTERS[@]}" "$@" "$STAGE/" "$DEST/"
