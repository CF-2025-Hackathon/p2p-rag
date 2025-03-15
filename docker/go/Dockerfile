FROM golang:1.24-alpine

RUN apk update && apk upgrade && \
    apk add --no-cache bash git openssh curl wget

RUN mkdir -p $$GOPATH/bin && \
    go install github.com/air-verse/air@latest

WORKDIR /go/src/app

EXPOSE 8080

ENTRYPOINT exec air -c .air.toml -- -listen /ip4/0.0.0.0/tcp/0 -rendezvous "$RENDEZVOUS" -key "$PRIV_KEY"
