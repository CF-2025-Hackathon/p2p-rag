docker network create p2prag
docker compose up -d
docker compose exec go1 go mod tidy
docker compose exec go1 go mod vendor
