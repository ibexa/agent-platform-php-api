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
# OpenAPI Generator does not overwrite tests, even with custom templates.
if [ -d src/test ]; then
    find src/test -type f -name '*Test.php' -delete
fi
docker run --rm --user "$(id -u):$(id -g)" -v "$PWD:/local" -v "$generated_spec:/spec/openapi.json:ro" \
    openapitools/openapi-generator-cli:v7.27.0@sha256:949efd0d5cf3c093ef169887b2ebe0879ee2e8074b6476264232ce4fd8ef46d0 generate \
    -c /local/openapi-generator-config.yaml \
    -t /local/generator/templates --skip-validate-spec
# The PHP generator hardcodes test/ relative to its output directory.
mkdir -p tests/generated/Api tests/generated/Model
for directory in Api Model; do
    find "tests/generated/$directory" -maxdepth 1 -type f -name '*Test.php' -delete
    find "src/test/$directory" -maxdepth 1 -type f -name '*Test.php' \
        -exec mv -t "tests/generated/$directory" -- {} +
    rmdir "src/test/$directory"
done
rmdir src/test
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php --show-progress=none src tests/generated
