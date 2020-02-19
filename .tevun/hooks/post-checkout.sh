#!/usr/bin/env bash

cd "${1}" || exit

echo " ~> [hooks\post-checkout.sh] on [${1}, ${2}]"

# echo "@ remove previous containers"
# docker-compose rm -f
if [ ".env" -ot ".env.stage" ]; then
    echo "@ copy a new .env"
    cp -f .env.stage .env
    echo "@ generate a new key"
    docker exec -it phpdfbot bash -c "php artisan key:generate"
fi
echo "@ copy a new docker-compose"
cp docker-compose.yml.stage docker-compose.yml
# echo "@ start the app"
# docker-compose up -d
