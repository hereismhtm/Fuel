#!/bin/sh

docker compose \
    -f docker-compose.yaml \
    -f docker-compose.dev.yaml \
    --env-file .env.local up "$@"
