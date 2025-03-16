package main

import (
	"bufio"
	"bytes"
	"context"
	"encoding/json"
	"flag"
	"fmt"
	"net/http"
	"os"
	"sync"
	"time"
	"strings"

	"github.com/gin-gonic/gin"
	"github.com/ipfs/go-log/v2"
	"github.com/libp2p/go-libp2p"
	dht "github.com/libp2p/go-libp2p-kad-dht"
	pubsub "github.com/libp2p/go-libp2p-pubsub"
	p2pcrypto "github.com/libp2p/go-libp2p/core/crypto"
	"github.com/libp2p/go-libp2p/core/host"
	"github.com/libp2p/go-libp2p/core/network"
	"github.com/libp2p/go-libp2p/core/peer"
	"github.com/libp2p/go-libp2p/core/protocol"
	drouting "github.com/libp2p/go-libp2p/p2p/discovery/routing"
	dutil "github.com/libp2p/go-libp2p/p2p/discovery/util"
	"github.com/multiformats/go-multiaddr"
)

const systemName = "rendezvous"
const vectorDimension = 768

// Vector is a type representing a 768-dimensional vector of float64 values
type Vector [vectorDimension]float64

// Topic represents a semantic vector or embedding
type Topic struct {
	Vectors []Vector `json:"vectors"`
}

var knownTopics []Topic
var knownTopicsMutex sync.RWMutex
var logger = log.Logger(systemName)
var topic *pubsub.Topic
var globalHost host.Host
var clientApiUrl string

// notifyExternalApiAboutGossipedTopic sends an HTTP request to notify an external API about a gossiped topic
func notifyExternalApiAboutGossipedTopic(topicData Topic, peerId string) {
	apiUrl := strings.TrimRight(clientApiUrl, "/") + "/expertise"

	logger.Info("📡 Notifying external API about gossiped topic:", "from peer: ", peerId, " to ", apiUrl)
	// Use the Gin HTTP client to make the POST request
	// This is a non-blocking call to avoid slowing down the gossip process
	go func() {
		client := &http.Client{Timeout: 5 * time.Second}

		// Create a payload with topic data and peer ID
		payload := struct {
			Topic  Topic  `json:"topic"`
			PeerId string `json:"peerId"`
		}{
			Topic:  topicData,
			PeerId: peerId,
		}

		jsonData, err := json.Marshal(payload)
		if err != nil {
			logger.Warn("❌ Failed to marshal topic data:", err)
			return
		}

		resp, err := client.Post(apiUrl, "application/json", bytes.NewReader(jsonData))
		if err != nil {
			logger.Warn("❌ Failed to notify external API:", err)
			return
		}
		defer resp.Body.Close()

		if resp.StatusCode >= 400 {
			logger.Warn("❌ External API returned error status:", resp.StatusCode)
		} else {
			logger.Info("✅ Successfully notified external API about topic")
		}
	}()
}

// Protocol ID for query streams
const queryProtocolID = "/p2p-rag/query/0.0.1"

// QueryRequest represents a request to query a peer
type QueryRequest struct {
	Vector Vector `json:"vector"`
}

// QueryResponse represents a response from a peer query
type QueryResponse struct {
	Success bool        `json:"success"`
	Error   string      `json:"error,omitempty"`
	Result  interface{} `json:"result,omitempty"`
}

// setupQueryProtocol initializes the query protocol handler
func setupQueryProtocol(host host.Host) {
	// Set up a stream handler for the query protocol
	host.SetStreamHandler(protocol.ID(queryProtocolID), handleQueryStream)
}

// handleQueryStream handles incoming query streams from other peers
func handleQueryStream(stream network.Stream) {
	defer stream.Close()

	// Create a buffered reader and writer
	rw := bufio.NewReadWriter(bufio.NewReader(stream), bufio.NewWriter(stream))

	// Read the request from the stream
	var request QueryRequest
	decoder := json.NewDecoder(rw.Reader)
	if err := decoder.Decode(&request); err != nil {
		logger.Warn("❌ Error decoding query request:", err)
		sendErrorResponse(rw, "Failed to decode request")
		return
	}

	logger.Info("📥 Received query request from peer:", stream.Conn().RemotePeer())

	// Forward the query to the local search API
	result, err := forwardQueryToLocalAPI(request.Vector)
	if err != nil {
		logger.Warn("❌ Error forwarding query to local API:", err)
		sendErrorResponse(rw, fmt.Sprintf("Failed to process query: %s", err.Error()))
		return
	}

	// Send the response back
	response := QueryResponse{
		Success: true,
		Result:  result,
	}

	encoder := json.NewEncoder(rw.Writer)
	if err := encoder.Encode(response); err != nil {
		logger.Warn("❌ Error encoding query response:", err)
		return
	}

	if err := rw.Writer.Flush(); err != nil {
		logger.Warn("❌ Error flushing response:", err)
		return
	}

	logger.Info("📤 Sent query response to peer:", stream.Conn().RemotePeer())
}

// forwardQueryToLocalAPI forwards the query to the local search API
func forwardQueryToLocalAPI(queryVector Vector) (interface{}, error) {
	// Construct the query payload
	queryPayload := struct {
		Vector Vector `json:"vector"`
	}{
		Vector: queryVector,
	}

	// Convert the payload to JSON
	jsonData, err := json.Marshal(queryPayload)
	if err != nil {
		return nil, fmt.Errorf("failed to marshal query data: %w", err)
	}

	// Send the query to the local search API
	client := &http.Client{Timeout: 10 * time.Second}
	resp, err := client.Post("http://localhost:9999/query", "application/json", bytes.NewReader(jsonData))
	if err != nil {
		return nil, fmt.Errorf("failed to send query to search API: %w", err)
	}
	defer resp.Body.Close()

	// Check for HTTP errors
	if resp.StatusCode >= 400 {
		return nil, fmt.Errorf("search API returned error status: %d", resp.StatusCode)
	}

	// Parse and return the JSON response
	var result interface{}
	if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
		return nil, fmt.Errorf("failed to parse query response: %w", err)
	}

	return result, nil
}

// sendErrorResponse sends an error response back to the peer
func sendErrorResponse(rw *bufio.ReadWriter, errorMsg string) {
	response := QueryResponse{
		Success: false,
		Error:   errorMsg,
	}

	encoder := json.NewEncoder(rw.Writer)
	if err := encoder.Encode(response); err != nil {
		logger.Warn("❌ Error encoding error response:", err)
		return
	}

	if err := rw.Writer.Flush(); err != nil {
		logger.Warn("❌ Error flushing error response:", err)
	}
}

// queryRemotePeer sends a query to a remote peer and returns the response
func queryRemotePeer(ctx context.Context, host host.Host, peerIdStr string, queryVector Vector) (interface{}, error) {
	// Parse the peer ID string
	peerID, err := peer.Decode(peerIdStr)
	if err != nil {
		return nil, fmt.Errorf("invalid peer ID: %w", err)
	}

	// Check if we're connected to this peer
	if host.Network().Connectedness(peerID) != network.Connected {
		return nil, fmt.Errorf("not connected to peer %s", peerIdStr)
	}

	// Open a new stream to the peer
	stream, err := host.NewStream(ctx, peerID, protocol.ID(queryProtocolID))
	if err != nil {
		return nil, fmt.Errorf("failed to open stream to peer: %w", err)
	}
	defer stream.Close()

	// Create a buffered reader and writer
	rw := bufio.NewReadWriter(bufio.NewReader(stream), bufio.NewWriter(stream))

	// Prepare the query request
	request := QueryRequest{
		Vector: queryVector,
	}

	// Send the request
	encoder := json.NewEncoder(rw.Writer)
	if err := encoder.Encode(request); err != nil {
		return nil, fmt.Errorf("failed to encode query request: %w", err)
	}

	if err := rw.Writer.Flush(); err != nil {
		return nil, fmt.Errorf("failed to send query request: %w", err)
	}

	logger.Info("📤 Sent query request to peer:", peerID)

	// Wait for the response
	var response QueryResponse
	decoder := json.NewDecoder(rw.Reader)
	if err := decoder.Decode(&response); err != nil {
		return nil, fmt.Errorf("failed to decode query response: %w", err)
	}

	// Check if the query was successful
	if !response.Success {
		return nil, fmt.Errorf("query failed on peer: %s", response.Error)
	}

	logger.Info("📥 Received query response from peer:", peerID)

	return response.Result, nil
}

func startWebApi() {
	r := gin.Default()

	r.GET("/topic", func(c *gin.Context) {
		knownTopicsMutex.RLock()
		// Return a map of topic keys to their vector data
		topics := make([]Topic, len(knownTopics))
		for _, topicData := range knownTopics {
			topics = append(topics, topicData)
		}
		knownTopicsMutex.RUnlock()

		c.JSON(200, gin.H{
			"topics": topics,
		})
	})

	r.POST("/topic", func(c *gin.Context) {
		type TopicRequest struct {
			Vectors [][]float64 `json:"vectors" binding:"required"`
		}

		var request TopicRequest
		if err := c.ShouldBindJSON(&request); err != nil {
			c.JSON(400, gin.H{"error": "Invalid request format, expecting {\"vectors\": [[...768 values], [...]]}"})
			return
		}

		// Validate vectors
		for _, vec := range request.Vectors {
			if len(vec) != vectorDimension {
				c.JSON(400, gin.H{"error": fmt.Sprintf("Each vector must have exactly %d values", vectorDimension)})
				return
			}
		}

		// Convert [][]float64 to []Vector
		vectors := make([]Vector, len(request.Vectors))
		for i, vec := range request.Vectors {
			var vector Vector
			for j, val := range vec {
				vector[j] = val
			}
			vectors[i] = vector
		}

		topicData := Topic{
			Vectors: vectors,
		}

		// Add to known topics
		knownTopicsMutex.Lock()
		knownTopics = append(knownTopics, topicData)
		knownTopicsMutex.Unlock()

		// Gossip the new topic immediately
		if topic != nil {
			// Serialize the topic data to JSON
			topicPayload := struct {
				Data Topic `json:"data"`
			}{
				Data: topicData,
			}

			jsonData, err := json.Marshal(topicPayload)
			if err != nil {
				logger.Warn("❌ Error marshaling topic data:", err)
				c.JSON(500, gin.H{"error": "Failed to serialize topic data", "details": err.Error()})
				return
			}

			err = topic.Publish(context.Background(), jsonData)
			if err != nil {
				logger.Warn("❌ Error publishing topic from API:", err)
				c.JSON(500, gin.H{"error": "Failed to gossip topic", "details": err.Error()})
				return
			}
			logger.Info("📡 Gossiped topic from API")
		} else {
			logger.Warn("❌ Couldn't gossip topic from API: p2p not initialized yet")
		}

		c.JSON(200, gin.H{
			"message":     "Topic received and gossiped",
			"vectorCount": len(vectors),
		})
	})

	// New endpoint for querying a remote peer
	r.POST("/query", func(c *gin.Context) {
		type QueryRequestAPI struct {
			PeerId string    `json:"peerId" binding:"required"`
			Vector []float64 `json:"vector" binding:"required"`
		}

		var request QueryRequestAPI
		if err := c.ShouldBindJSON(&request); err != nil {
			c.JSON(400, gin.H{"error": "Invalid request format, expecting {\"peerId\": \"...\", \"vector\": [...768 values]}"})
			return
		}

		// Validate vector length
		if len(request.Vector) != vectorDimension {
			c.JSON(400, gin.H{"error": fmt.Sprintf("Vector must have exactly %d values", vectorDimension)})
			return
		}

		// Convert []float64 to Vector
		var queryVector Vector
		for i, val := range request.Vector {
			queryVector[i] = val
		}

		// Get the host from the global variable
		if globalHost == nil {
			c.JSON(500, gin.H{"error": "P2P host not initialized yet"})
			return
		}

		logger.Info("🔍 Querying peer:", request.PeerId)

		// Send the query to the remote peer via libp2p
		result, err := queryRemotePeer(c.Request.Context(), globalHost, request.PeerId, queryVector)
		if err != nil {
			logger.Warn("❌ Error querying peer:", err)
			c.JSON(500, gin.H{"error": "Failed to query peer", "details": err.Error()})
			return
		}

		// Return the query result
		c.JSON(200, result)
	})

	r.Run(":8888")
}

func main() {
	go startWebApi()
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

	clientApiUrl = config.ClientApiUrl

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

	// Store the host in the global variable
	globalHost = host

	// Set a function as stream handler. This function is called when a peer
	// initiates a connection and starts a stream with this peer.
	host.SetStreamHandler(protocol.ID(config.ProtocolID), handleStream)

	// Set up the query protocol handler
	setupQueryProtocol(host)

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

	// Initialize the global topic
	topic, err = ps.Join("/rag-topics")
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
func gossipTopics(pubsubTopic *pubsub.Topic) {
	knownTopicsMutex.RLock()
	defer knownTopicsMutex.RUnlock()

	if len(knownTopics) == 0 {
		return
	}

	// We'll gossip each topic individually since they may be large
	for _, topicData := range knownTopics {
		// Create payload
		topicPayload := struct {
			Data Topic `json:"data"`
		}{
			Data: topicData,
		}

		jsonData, err := json.Marshal(topicPayload)
		if err != nil {
			logger.Warn("❌ Error marshaling topic data:", err)
			continue
		}

		err = pubsubTopic.Publish(context.Background(), jsonData)
		if err != nil {
			logger.Warn("❌ Error publishing topic:", err)
			continue
		}

		logger.Info("📡 Gossiped topic, with ", len(topicData.Vectors), " vectors")
	}
}

// 🟢 Function to listen for gossip messages from peers
func listenForGossip(sub *pubsub.Subscription) {
	for {
		msg, err := sub.Next(context.Background())
		if err != nil {
			logger.Warn("❌ Error receiving gossip:", err)
			continue
		}

		// Get peer ID who sent this message
		senderId := msg.ReceivedFrom.String()

		// Parse the received JSON data
		var topicPayload struct {
			Key  string `json:"key"`
			Data Topic  `json:"data"`
		}

		if err := json.Unmarshal(msg.Data, &topicPayload); err != nil {
			logger.Warn("❌ Failed to unmarshal received topic data:", err)
			continue
		}

		topicData := topicPayload.Data

		notifyExternalApiAboutGossipedTopic(topicData, senderId)
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
