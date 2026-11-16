#!/usr/bin/env bash
# Runs the lekalo-php suites under every current PHP version through
# the official docker CLI images (lekalo-php#1: the language adapter
# works with all current PHP versions; no host interpreter needed).
#
#   scripts/version-matrix.sh                      # protocol + analyzer
#   scripts/version-matrix.sh tests/protocol.php   # one suite
#
# LEKALO_DOCKER overrides the docker command (e.g. "sudo docker").
# PHP 8.6 rides its `-rc` image until GA lands in the official library.
set -euo pipefail
cd "$(dirname "$0")/.."

VERSIONS=(8.0 8.1 8.2 8.3 8.4 8.5)
if docker image inspect php:8.6-cli >/dev/null 2>&1; then
  VERSIONS+=(8.6)
else
  VERSIONS+=("8.6-rc")
fi

SUITES=("$@")
if [ ${#SUITES[@]} -eq 0 ]; then
  SUITES=(tests/protocol.php tests/analyzer.php)
fi

DOCKER=(docker)
if ! docker info >/dev/null 2>&1; then
  DOCKER=(sudo docker)
fi
fail=0
for version in "${VERSIONS[@]}"; do
  tag="php:${version}-cli"
  for suite in "${SUITES[@]}"; do
    if output="$("${DOCKER[@]}" run --rm -v "$PWD:/work" -w /work "$tag" php -n "$suite" 2>&1)"; then
      echo "php ${version} ${suite}: $(echo "$output" | tail -1)"
    else
      echo "php ${version} ${suite}: FAILED"
      echo "$output" | tail -5
      fail=1
    fi
  done
done
exit $fail
