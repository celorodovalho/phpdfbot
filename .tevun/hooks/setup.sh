#!/usr/bin/env bash

cd ${1}

echo " ~> [hooks\setup.sh] on [${1}, ${2}]"

cp .env.stage .env
cp docker-compose.yml.stage docker-compose.yml
