#!/bin/bash
set -euo pipefail

cd "$(dirname "$0")"
docker run --rm --user "$(id -u):$(id -g)" -v "$PWD:/local" \
    openapitools/openapi-generator-cli:v7.27.0@sha256:949efd0d5cf3c093ef169887b2ebe0879ee2e8074b6476264232ce4fd8ef46d0 generate \
    -c /local/openapi-generator-config.yaml \
    -t /local/generator/templates --skip-validate-spec
