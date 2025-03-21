package api

import (
	"github.com/libp2p/go-libp2p/core/crypto"
	p2pcrypto "github.com/libp2p/go-libp2p/core/crypto"
)

func getPrivateKey(base64PrivateKey string) (p2pcrypto.PrivKey, error) {
	if base64PrivateKey == "" {
		return newPrivateKey()
	}
	return privateKeyFrom(base64PrivateKey)
}

func privateKeyFrom(base64PrivateKey string) (p2pcrypto.PrivKey, error) {
	privateKeyAsBytes, err := p2pcrypto.ConfigDecodeKey(base64PrivateKey)
	if err != nil {
		return nil, err
	}
	return p2pcrypto.UnmarshalPrivateKey(privateKeyAsBytes)
}

func newPrivateKey() (p2pcrypto.PrivKey, error) {
	privateKey, _, err := p2pcrypto.GenerateKeyPair(p2pcrypto.Ed25519, 0)
	if err != nil {
		return nil, err
	}
	return privateKey, err
}

func privateKeyAsString(privateKey crypto.PrivKey) (string, error) {
	privateKeyAsBytes, err := crypto.MarshalPrivateKey(privateKey)
	if err != nil {
		return "", err
	}
	return crypto.ConfigEncodeKey(privateKeyAsBytes), nil
}
