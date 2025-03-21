package api

import (
	"github.com/libp2p/go-libp2p/core/crypto/pb"
	"github.com/stretchr/testify/assert"
	"testing"
)

func Test_getPrivateKey_canDecodeAKeyInStringFormat(t *testing.T) {
	key, err := getPrivateKey("CAESQBEx8bKxlGKCzcfxsR94EEqCE+8bBm/fBaAXOBEkXiU9uGjKXsoDUJJuGugLJFVvbwvbqeZzP0IR23M45C0mkRY=")

	assert.Nil(t, err)
	assert.NotNil(t, key)
	assert.Equal(t, key.Type(), pb.KeyType_Ed25519)
}

func Test_getPrivateKey_canGenerateANewKeyInStringFormat(t *testing.T) {
	key, err := getPrivateKey("")

	assert.Nil(t, err)
	assert.NotNil(t, key)
}

func Test_weCanDecodeAKeyInStringFormatAndEncodeItAgain(t *testing.T) {
	originalPKasString := "CAESQBEx8bKxlGKCzcfxsR94EEqCE+8bBm/fBaAXOBEkXiU9uGjKXsoDUJJuGugLJFVvbwvbqeZzP0IR23M45C0mkRY="

	key, _ := getPrivateKey(originalPKasString)
	encodedKey, _ := privateKeyAsString(key)

	assert.Equal(t, encodedKey, originalPKasString)
}
