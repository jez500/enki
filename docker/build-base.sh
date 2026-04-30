#!/usr/bin/env bash
# Builds and pushes the jez500/enki-base images.
# Run this whenever system-level dependencies change (apk packages, PHP extensions).
set -euo pipefail

REPO="jez500/enki-base"

echo "Building ${REPO}:php-builder …"
docker build -f Dockerfile.base --target php-builder -t "${REPO}:php-builder" .

echo "Building ${REPO}:frontend-builder …"
docker build -f Dockerfile.base --target frontend-builder -t "${REPO}:frontend-builder" .

echo "Building ${REPO}:runtime …"
docker build -f Dockerfile.base --target runtime -t "${REPO}:runtime" .

echo "Pushing …"
docker push "${REPO}:php-builder"
docker push "${REPO}:frontend-builder"
docker push "${REPO}:runtime"

echo "Done."
