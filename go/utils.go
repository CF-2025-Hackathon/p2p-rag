package main

import (
	"fmt"
	"math/rand"
)

func generateRandomString(length int) string {
	const (
		lowercase = "abcdefghijklmnopqrstuvwxyz"
		uppercase = "ABCDEFGHIJKLMNOPQRSTUVWXYZ"
		digits    = "0123456789"
	)

	allChars := lowercase + uppercase + digits

	b := make([]byte, length)

	for i := range b {
		b[i] = allChars[rand.Intn(len(allChars))]
	}

	return string(b)
}

func hasIntersection[T fmt.Stringer](arr1 []T, arr2 []T) bool {
	if len(arr1) == 0 || len(arr2) == 0 {
		return false
	}

	m := make(map[string]bool)
	for _, v := range arr1 {
		m[v.String()] = true
	}

	for _, v := range arr2 {
		if m[v.String()] {
			return true
		}
	}
	return false
}
