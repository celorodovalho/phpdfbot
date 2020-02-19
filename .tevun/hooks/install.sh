#!/usr/bin/env bash

cd "${1}" || exit

echo " ~> [hooks\install.sh] on [${1}, ${2}]"

echo "@ install composer stuffs"
docker exec phpdfbot bash -c "composer install --no-interaction"
echo "@ fix permissions"
docker exec phpdfbot bash -c "chown -R application:application ."
# docker exec phpdfbot bash -c "su -c \"php artisan migrate --force\" application"
