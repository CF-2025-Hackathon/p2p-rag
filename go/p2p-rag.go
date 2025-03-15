package main

import (
	"bufio"
	"context"
	"flag"
	"fmt"
	"os"
	"time"
	"strings"

	"github.com/libp2p/go-libp2p"
	"github.com/libp2p/go-libp2p/core/network"
	"github.com/libp2p/go-libp2p/core/peer"
	"github.com/libp2p/go-libp2p/core/protocol"
	drouting "github.com/libp2p/go-libp2p/p2p/discovery/routing"
	dutil "github.com/libp2p/go-libp2p/p2p/discovery/util"
	p2pcrypto "github.com/libp2p/go-libp2p/core/crypto"
	pubsub "github.com/libp2p/go-libp2p-pubsub"
	dht "github.com/libp2p/go-libp2p-kad-dht"
	"github.com/multiformats/go-multiaddr"
	"github.com/ipfs/go-log/v2"
)

const systemName = "rendezvous"

var knownTopics = make(map[string]struct{})
var logger = log.Logger(systemName)

func main() {
	log.SetAllLoggers(log.LevelError)
	log.SetLogLevel(systemName, "info")
	help := flag.Bool("h", false, "Display Help")
	printKey := flag.Bool("pk", false, "Prints a new private key")
	config, err := ParseFlags()
	if err != nil {
		panic(err)
	}

	if *help {
		fmt.Println("This program demonstrates a simple p2p chat application using libp2p")
		fmt.Println()
		fmt.Println("Usage: Run './p2p-rag in two different terminals. Let them connect to the bootstrap nodes, announce themselves and connect to the peers")
		fmt.Println("Example for listening on all local IP addresses on a random TCP port:")
		fmt.Println("./p2p-rag -listen /ip4/0.0.0.0/tcp/0 ")
		fmt.Println("You can also pass a base64 private key using the -key flag, otherwise a new Ed25519 key will be created and printed.")
		fmt.Println("./p2p-rag -listen /ip4/0.0.0.0/tcp/0 -key CAESQJ...")
		flag.PrintDefaults()
		return
	}

	if *printKey {
		newPrivateKey()
		return
	}

	// libp2p.New constructs a new libp2p Host. Other options can be added
	// here.
	privateKey, err := getPrivateKey(config.PrivateKey)
	if err != nil {
		panic(err)
	}

	opts := []libp2p.Option{
		libp2p.NATPortMap(),
		libp2p.EnableHolePunching(),
		libp2p.ListenAddrs([]multiaddr.Multiaddr(config.ListenAddresses)...),
		libp2p.Identity(privateKey),
	}

	host, err := libp2p.New(opts...)
	if err != nil {
		panic(err)
	}
	logger.Info("Host created. We are: ", host.ID())
	logger.Info(host.Addrs())

	// Set a function as stream handler. This function is called when a peer
	// initiates a connection and starts a stream with this peer.
	host.SetStreamHandler(protocol.ID(config.ProtocolID), handleStream)

	// Start a DHT, for use in peer discovery. We can't just make a new DHT
	// client because we want each peer to maintain its own local copy of the
	// DHT, so that the bootstrapping node of the DHT can go down without
	// inhibiting future peer discovery.
	ctx := context.Background()
	bootstrapPeers := make([]peer.AddrInfo, len(config.BootstrapPeers))
	for i, addr := range config.BootstrapPeers {
		peerinfo, _ := peer.AddrInfoFromP2pAddr(addr)
		bootstrapPeers[i] = *peerinfo
	}
	kademliaDHT, err := dht.New(ctx, host, dht.BootstrapPeers(bootstrapPeers...))
	if err != nil {
		panic(err)
	}

	// Bootstrap the DHT. In the default configuration, this spawns a Background
	// thread that will refresh the peer table every five minutes.
	logger.Debug("Bootstrapping the DHT")
	if err = kademliaDHT.Bootstrap(ctx); err != nil {
		panic(err)
	}

	// Wait a bit to let bootstrapping finish (really bootstrap should block until it's ready, but that isn't the case yet.)
	time.Sleep(1 * time.Second)

	// We use a rendezvous point `config.RendezvousString` to announce our location.
	// This is like telling your friends to meet you at the Eiffel Tower.
	logger.Info("Announcing ourselves with rendezvous ", config.RendezvousString)
	routingDiscovery := drouting.NewRoutingDiscovery(kademliaDHT)
	dutil.Advertise(ctx, routingDiscovery, config.RendezvousString)

	ps, err := pubsub.NewGossipSub(ctx, host)
	if err != nil {
		panic(err)
	}

	topic, err := ps.Join("/rag-topics")
	if err != nil {
		panic(err)
	}
	subscription, err := topic.Subscribe()
	if err != nil {
		panic(err)
	}

	// Start listening for incoming topic gossip
	go listenForGossip(subscription)

	// Periodically gossip local topics
	go func() {
		for {
			gossipTopics(topic)
			time.Sleep(10 * time.Second) // Adjust based on network size
		}
	}()

	for {
		// Now, look for others who have announced
		// This is like your friend telling you the location to meet you.
		logger.Info("Searching for other peers...")
		// Wait again...
		time.Sleep(2 * time.Second)
		peerChan, err := routingDiscovery.FindPeers(ctx, config.RendezvousString)
		if err != nil {
			panic(err)
		}
		for peer := range peerChan {
			if peer.ID.String() == host.ID().String() || len(peer.Addrs) == 0 || hasIntersection(peer.Addrs, host.Addrs()) {
				continue
			}

			// We do not want to connect again to the same peer
			if host.Network().Connectedness(peer.ID) != network.Connected {
				logger.Info("Connecting to: ", peer.ID, peer.Addrs)
				stream, err := host.NewStream(ctx, peer.ID, protocol.ID(config.ProtocolID))

				if err != nil {
					logger.Warn("Connection failed: ", err)
					continue
				} else {
					rw := bufio.NewReadWriter(bufio.NewReader(stream), bufio.NewWriter(stream))

					go writeData(rw)
					go readData(rw)
				}

				logger.Info("*** 🥳 Connected to: ", peer)
			}
		}

		logger.Warn("No more peers 😢- Trying again")
		// Wait again...
		time.Sleep(5 * time.Second)
	}
}

func handleStream(stream network.Stream) {
	logger.Info("*** 🥳 Got a new stream!")

	// Create a buffer stream for non-blocking read and write.
	rw := bufio.NewReadWriter(bufio.NewReader(stream), bufio.NewWriter(stream))

	go readData(rw)
	go writeData(rw)

	// 'stream' will stay open until you close it (or the other side closes it).
}

// 🟢 Function to send our known topics to the gossip network
func gossipTopics(topic *pubsub.Topic) {
	myTopics := strings.Split(os.Getenv("RAG_TOPICS"), ",")
	message := strings.Join(myTopics, ",")
	err := topic.Publish(context.Background(), []byte(message))
	if err != nil {
		logger.Warn("❌ Error publishing topics:", err)
	}
	logger.Info("📡 Gossiped topics:", message)
}

// 🟢 Function to listen for gossip messages from peers
func listenForGossip(sub *pubsub.Subscription) {
	for {
		msg, err := sub.Next(context.Background())
		if err != nil {
			logger.Warn("❌ Error receiving gossip:", err)
			continue
		}

		// Extract and store topics
		receivedTopics := strings.Split(string(msg.Data), ",")
		for _, topic := range receivedTopics {
			if _, exists := knownTopics[topic]; !exists {
				knownTopics[topic] = struct{}{}
				logger.Info("🔍 Learned about new topic:", topic)
			}
		}
	}
}

func readData(rw *bufio.ReadWriter) {
	for {
		str, err := rw.ReadString('\n')
		if err != nil {
			fmt.Println("Error reading from buffer")
			break
		}

		if str == "" {
			return
		}
		if str != "\n" {
			// Green console colour: 	\x1b[32m
			// Reset console colour: 	\x1b[0m
			fmt.Printf("\x1b[32m%s\x1b[0m> ", str)
		}

	}
}

func writeData(rw *bufio.ReadWriter) {
	stdReader := bufio.NewReader(os.Stdin)

	for {
		fmt.Print("> ")
		sendData, err := stdReader.ReadString('\n')
		if err != nil {
			fmt.Println("Error reading from stdin")
			panic(err)
		}

		_, err = rw.WriteString(fmt.Sprintf("%s\n", sendData))
		if err != nil {
			fmt.Println("Error writing to buffer")
			panic(err)
		}
		err = rw.Flush()
		if err != nil {
			fmt.Println("Error flushing buffer")
			panic(err)
		}
	}
}

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
	if err == nil {
		privateKeyAsBytes, err1 := p2pcrypto.MarshalPrivateKey(privateKey)
		if err1 != nil {
			panic(err1)
		}
		fmt.Println(p2pcrypto.ConfigEncodeKey(privateKeyAsBytes))
	}
	return privateKey, err
}
