#!/bin/bash
set -euo pipefail

cd "$(dirname "$0")"
if [ ! -x vendor/bin/php-cs-fixer ]; then
    echo "Install Composer dependencies before regenerating the client." >&2
    exit 1
fi
generated_spec=$(mktemp)
trap 'rm -f -- "$generated_spec"' EXIT
php generator/prepare-openapi.php > "$generated_spec"
docker run --rm --user "$(id -u):$(id -g)" -v "$PWD:/local" -v "$generated_spec:/spec/openapi.json:ro" \
    openapitools/openapi-generator-cli:v7.27.0@sha256:949efd0d5cf3c093ef169887b2ebe0879ee2e8074b6476264232ce4fd8ef46d0 generate \
    -c /local/openapi-generator-config.yaml \
    -t /local/generator/templates --skip-validate-spec
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php --show-progress=none --quiet src/lib
