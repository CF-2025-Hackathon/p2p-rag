#!/bin/bash

DIR="$(dirname "${BASH_SOURCE[0]}")"
DIR="$(readlink -f "${DIR}")"

export DTO=$(cat "${DIR}"/expertise.json)

curl -X POST http://localhost:8888/expertise -H "Content-Type: application/json" -d "$DTO"

