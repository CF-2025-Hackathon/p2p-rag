package api

import (
	"context"
	"encoding/json"
	"fmt"
	"github.com/gin-gonic/gin"
)

func SetupRouter() *gin.Engine {
	gin.SetMode(gin.ReleaseMode)
	router := gin.Default()

	router.GET("/expertise", func(c *gin.Context) {
		myExpertiseMutex.RLock()
		// Return a map of topic keys to their vector data
		topics := make([]Expertise, len(myExpertise))
		for _, topicData := range myExpertise {
			topics = append(topics, topicData)
		}
		myExpertiseMutex.RUnlock()

		c.JSON(200, gin.H{
			"topics": topics,
		})
	})

	router.POST("/expertise", func(c *gin.Context) {
		type EmbeddingJson struct {
			Key       string    `json:"key" binding:"required"`
			Model     string    `json:"model" binding:"required"`
			Expertise string    `json:"expertise" binding:"required"`
			Vector    []float64 `json:"vector" binding:"required"`
		}
		type ExpertiseRequest struct {
			Embeddings []EmbeddingJson `json:"embeddings" binding:"required"`
		}

		var request ExpertiseRequest
		if err := c.ShouldBindJSON(&request); err != nil {
			c.JSON(400, gin.H{"error": "Invalid request format" + err.Error()})
			return
		}

		// Validate vectors
		for _, emb := range request.Embeddings {
			if len(emb.Vector) != vectorDimension {
				c.JSON(400, gin.H{"error": fmt.Sprintf("Each vector must have exactly %d values", vectorDimension)})
				return
			}
		}

		// Create embeddings and copy data from request
		embeddings := make([]Embedding, len(request.Embeddings))
		for i, emb := range request.Embeddings {
			embeddings[i] = Embedding{
				Key:       emb.Key,
				Expertise: emb.Expertise,
				Model:     emb.Model,
				Vector:    emb.Vector,
			}
		}

		expertiseData := Expertise{
			Embeddings: embeddings,
		}

		// Add to known topics
		myExpertiseMutex.Lock()
		myExpertise = append(myExpertise, expertiseData)
		myExpertiseMutex.Unlock()

		// Gossip the new topic immediately
		if topic != nil {
			// Serialize the topic data to JSON
			expertisePayload := struct {
				Data Expertise `json:"data"`
			}{
				Data: expertiseData,
			}

			jsonData, err := json.Marshal(expertisePayload)
			if err != nil {
				l.Warn("❌ Error marshaling topic data:", err)
				c.JSON(500, gin.H{"error": "Failed to serialize topic data", "details": err.Error()})
				return
			}

			err = topic.Publish(context.Background(), jsonData)
			if err != nil {
				l.Warn("❌ Error publishing topic from API:", err)
				c.JSON(500, gin.H{"error": "Failed to gossip topic", "details": err.Error()})
				return
			}
			l.Info("📡 Gossiped topic from API")
		} else {
			l.Warn("❌ Couldn't gossip topic from API: p2p not initialized yet")
		}

		c.JSON(200, gin.H{
			"message":        "Expertise received and gossiped",
			"embeddingCount": len(expertiseData.Embeddings),
		})
	})

	// New endpoint for querying a remote peer
	router.POST("/query", func(c *gin.Context) {

		type EmbeddingQuery struct {
			ExpertiseKey string    `json:"expertise_key"`
			Model        string    `json:"model"`
			Vector       []float64 `json:"vector"`
			MatchCount   int       `json:"match_count"`
		}
		type QueryRequestAPI struct {
			PeerId    string         `json:"nodeId" binding:"required"`
			QueryId   string         `json:"queryId" binding:"required"`
			Embedding EmbeddingQuery `json:"embedding" binding:"required"`
		}
		var request QueryRequestAPI
		if err := c.ShouldBindJSON(&request); err != nil {
			c.JSON(400, gin.H{"error": "Invalid request format : " + err.Error()})
			return
		}

		// Validate vector length
		if len(request.Embedding.Vector) != vectorDimension {
			c.JSON(400, gin.H{"error": fmt.Sprintf("Vector must have exactly %d values", vectorDimension)})
			return
		}

		// Get the host from the global variable
		if globalHost == nil {
			c.JSON(500, gin.H{"error": "P2P host not initialized yet"})
			return
		}
		vector := Vector(request.Embedding.Vector)

		req := QueryRequest{
			QueryId:      request.QueryId,
			ExpertiseKey: request.Embedding.ExpertiseKey,
			Model:        request.Embedding.Model,
			MatchCount:   request.Embedding.MatchCount,
			Vector:       vector,
		}

		if request.PeerId == globalHost.ID().String() {
			l.Info("🔍 Querying self")
			var result interface{}
			var err error

			result, err = forwardQueryToLocalAPI(req)
			if err != nil {
				l.Warn("❌ Error querying self:", err)
				c.JSON(500, gin.H{"error": "Failed to query self", "details": err.Error()})
				return
			} else {
				l.Info("✅ Successfully queried self")
				c.JSON(200, result)
			}
		} else {
			l.Info("🔍 Querying peer:", request.PeerId)

			// Send the query to the remote peer via libp2p
			result, err := queryRemotePeer(c.Request.Context(), globalHost, request.PeerId, req)
			if err != nil {
				l.Warn("❌ Error querying peer:", err)
				c.JSON(500, gin.H{"error": "Failed to query peer", "details": err.Error()})
				return
			}

			// Return the query result
			c.JSON(200, result)
		}
	})
	return router
}
