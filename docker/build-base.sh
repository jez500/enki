#!/usr/bin/env bash
# Builds and pushes jez500/enki-base for linux/amd64 only (local use).
# Multi-platform (amd64 + arm64) builds run automatically via GitHub Actions
# when Dockerfile.base changes on main.
set -euo pipefail

REPO="jez500/enki-base"

docker buildx build \
    -f Dockerfile.base \
    --platform "linux/amd64" \
    --target php-builder \
    -t "${REPO}:php-builder" \
    --push \
    .

docker buildx build \
    -f Dockerfile.base \
    --platform "linux/amd64" \
    --target frontend-builder \
    -t "${REPO}:frontend-builder" \
    --push \
    .

docker buildx build \
    -f Dockerfile.base \
    --platform "linux/amd64" \
    --target runtime \
    -t "${REPO}:runtime" \
    --push \
    .

echo "Done."
