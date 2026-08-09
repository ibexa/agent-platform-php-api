#!/bin/bash

docker run --rm -v "${PWD}:/local" openapitools/openapi-generator-cli generate \
    -c /local/openapi-generator-config.yaml --skip-validate-spec

sudo chown -R awojs:awojs src/
