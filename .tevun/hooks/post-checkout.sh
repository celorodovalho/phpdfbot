#!/usr/bin/env bash

cd ${1}

echo " ~> [hooks\post-checkout.sh] on [${1}, ${2}]"

if [[ -f "docker-compose.yml" ]]; then
  docker-compose rm
  docker-compose up -d
fi