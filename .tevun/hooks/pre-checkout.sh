#!/usr/bin/env bash

cd ${1}

echo " ~> [hooks\pre-checkout.sh] on [${1}, ${2}]"

if [[ "$(docker ps -q -f name=${2}-app)" ]]; then
  docker-compose down
fi

# rm -rf ./*
