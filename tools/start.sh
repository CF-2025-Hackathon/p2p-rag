#!/bin/bash

RENDEZVOUS=Xc13dr5
KEY=$1

DIR="$(dirname "${BASH_SOURCE[0]}")"
DIR="$(readlink -f "${DIR}")"

"${DIR}"/../go/p2p-rag -rendezvous $RENDEZVOUS -key $KEY


