package main

import (
	"fmt"
	"sync"

	//"github.com/libp2p/go-libp2p/core/host"
	"github.com/libp2p/go-libp2p/core/network"
	"github.com/libp2p/go-libp2p/core/peer"
)

// PeerManager keeps track of connected peers and their streams
type PeerManager struct {
	peers map[peer.ID]network.Stream
	mutex sync.Mutex
}

// NewPeerManager initializes the peer list
func NewPeerManager() *PeerManager {
	return &PeerManager{
		peers: make(map[peer.ID]network.Stream),
	}
}

// AddPeer registers a new peer connection
func (pm *PeerManager) AddPeer(p peer.ID, stream network.Stream) {
	pm.mutex.Lock()
	defer pm.mutex.Unlock()
	pm.peers[p] = stream
	fmt.Printf("Added peer: %s\n", p)
}

// RemovePeer removes a peer from the list
func (pm *PeerManager) RemovePeer(p peer.ID) {
	pm.mutex.Lock()
	defer pm.mutex.Unlock()
	delete(pm.peers, p)
	fmt.Printf("Removed peer: %s\n", p)
}

// ListPeers returns all connected peers
func (pm *PeerManager) ListPeers() map[peer.ID]network.Stream {
	pm.mutex.Lock()
	defer pm.mutex.Unlock()
	return pm.peers
}

/* // Integrate with the host
func handleNewStream(pm *PeerManager) network.StreamHandler {
	return func(s network.Stream) {
		pm.AddPeer(s.Conn().RemotePeer(), s)
	}
}

func main() {
	// Initialize Peer Manager
	peerManager := NewPeerManager()

	// Example: Initialize host and set stream handler (Replace 'h' with your host instance)
	var h host.Host // Assume this is properly initialized
	h.SetStreamHandler("/p2p-rag/1.0.0", handleNewStream(peerManager))
} */
