#!/usr/bin/env bash
# Builds and pushes the jez500/enki-base images for linux/amd64 and linux/arm64.
# Run this whenever system-level dependencies change (apk packages, PHP extensions).
set -euo pipefail

REPO="jez500/enki-base"
PLATFORMS="linux/amd64,linux/arm64"

docker buildx build \
    -f Dockerfile.base \
    --platform "${PLATFORMS}" \
    --target php-builder \
    -t "${REPO}:php-builder" \
    --push \
    .

docker buildx build \
    -f Dockerfile.base \
    --platform "${PLATFORMS}" \
    --target frontend-builder \
    -t "${REPO}:frontend-builder" \
    --push \
    .

docker buildx build \
    -f Dockerfile.base \
    --platform "${PLATFORMS}" \
    --target runtime \
    -t "${REPO}:runtime" \
    --push \
    .

echo "Done."
