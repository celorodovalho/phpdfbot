#!/usr/bin/env bash

cd "${1}" || exit

echo " ~> [hooks\setup.sh] on [${1}, ${2}]"

# apply seeds on database
# docker exec -it phpdfbot bash -c "php artisan db:seed"
