#!/usr/bin/env bash

cd "${1}" || exit

echo " ~> [hooks\pre-checkout.sh] on [${1}, ${2}]"

# echo "@ put the containers down before checkout the new codes"
# docker-compose down
