#!/usr/bin/env bash
#
# Builds a production-ready distributable zip of the plugin:
#   - Copies plugin source + admin-facing docs only (no dev tooling)
#   - Installs Composer dependencies fresh with --no-dev --optimize-autoloader
#   - Zips the result as build/kodr-secure-referral-archive-<version>.zip
#
# Usage: bin/build.sh

set -euo pipefail

PLUGIN_SLUG="kodr-secure-referral-archive"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

VERSION="$(php -r '
$contents = file_get_contents($argv[1]);
if (preg_match("/^\s*\*\s*Version:\s*([0-9A-Za-z.\-]+)/m", $contents, $matches)) {
    echo $matches[1];
} else {
    echo "0.0.0";
}
' "$ROOT_DIR/kodr-secure-referral-archive.php")"

BUILD_DIR="$ROOT_DIR/build"
STAGE_DIR="$BUILD_DIR/$PLUGIN_SLUG"
ZIP_PATH="$BUILD_DIR/${PLUGIN_SLUG}-${VERSION}.zip"

echo "Building ${PLUGIN_SLUG} v${VERSION}"

rm -rf "$BUILD_DIR"
mkdir -p "$STAGE_DIR"

# Copy everything except dev-only tooling, VCS metadata, and anything that
# could contain real credentials or sensitive local fixtures.
rsync -a "$ROOT_DIR"/ "$STAGE_DIR"/ \
    --exclude='.git' \
    --exclude='.github' \
    --exclude='.gitignore' \
    --exclude='.vscode' \
    --exclude='.idea' \
    --exclude='.DS_Store' \
    --exclude='build' \
    --exclude='docs' \
    --exclude='tests' \
    --exclude='vendor' \
    --exclude='node_modules' \
    --exclude='phpunit.xml' \
    --exclude='.phpunit.cache' \
    --exclude='.phpunit.result.cache' \
    --exclude='README.md' \
    --exclude='composer.json' \
    --exclude='composer.lock' \
    --exclude='wp-config.php' \
    --exclude='*.zip'

# Install production-only dependencies with an optimized autoloader, then
# remove the Composer manifests themselves — they aren't needed at runtime
# and aren't useful once vendor/ is already built.
cp "$ROOT_DIR/composer.json" "$STAGE_DIR/composer.json"
cp "$ROOT_DIR/composer.lock" "$STAGE_DIR/composer.lock"
(cd "$STAGE_DIR" && composer install --no-dev --optimize-autoloader --no-interaction --quiet)
rm -f "$STAGE_DIR/composer.json" "$STAGE_DIR/composer.lock"
rm -rf "$STAGE_DIR/vendor/bin"

(cd "$BUILD_DIR" && zip -rq "$(basename "$ZIP_PATH")" "$PLUGIN_SLUG")

echo "Built: $ZIP_PATH"
