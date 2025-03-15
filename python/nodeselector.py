from typing import Dict, List, Tuple
import json
import logging
from pathlib import Path
import ollama
from ollama import AsyncClient
import numpy as np
from numpy.linalg import norm

# Logging configuration
logger = logging.getLogger(__name__)

class NodeSelector:
    def __init__(self, gossip_file: str = "gossip_data.json"):
        """
        Initialize the NodeSelector
        Args:
            gossip_file: Path to the gossip JSON file
        """
        self.gossip_file = gossip_file
        self.client = AsyncClient(host='http://localhost:11434')
        
    def load_gossip_data(self) -> Dict:
        """
        Load the gossip data from JSON file
        Returns:
            Dict: The gossip data
        """
        try:
            if Path(self.gossip_file).exists():
                with open(self.gossip_file, 'r') as f:
                    return json.load(f)
            return {}
        except Exception as e:
            logger.error(f"Error loading gossip data: {str(e)}")
            return {}

    async def find_best_match(self, query: str, model_name: str = "nomic-embed-text", top_k: int = 1) -> List[Tuple[str, float, str]]:
        """
        Find the best matching nodes for a query using Ollama's embeddings
        Args:
            query: The query text
            model_name: Name of the embedding model to use
            top_k: Number of best matches to return, or None for all matches
        Returns:
            List[Tuple[str, float, str]]: List of (node_id, similarity_score, embedding_model) tuples
        """
        try:
            # Get query embedding from Ollama
            query_response = await self.client.embeddings(
                model=model_name,
                prompt=query
            )
            
            # Convert response to dict if it's not already
            if not isinstance(query_response, dict):
                query_response = query_response.__dict__
                
            # Extract the embedding
            if 'embedding' in query_response:
                query_embedding = np.array(query_response['embedding'])
                # L2 Normalisierung des Query-Vektors
                query_embedding = query_embedding / norm(query_embedding)
            else:
                logger.error(f"Unexpected response format: {query_response}")
                return []

            # Load gossip data
            gossip_data = self.load_gossip_data()
            if not gossip_data:
                logger.warning("No gossip data available")
                return []

            # Calculate similarities with all nodes
            similarities = []
            for node_id, node_data in gossip_data.items():
                # Konvertiere den Node-Vektor zu numpy und normalisiere
                node_vector = np.array(node_data["vector"])
                node_vector = node_vector / norm(node_vector)
                
                # Berechne verschiedene Ähnlichkeitsmetriken
                cosine_sim = np.dot(query_embedding, node_vector)
                l2_distance = norm(query_embedding - node_vector)
                
                # Kombinierte Ähnlichkeitsmetrik
                similarity = (cosine_sim + 1) / 2  # Normalisiere auf [0,1]
                
                similarities.append((
                    node_id,
                    float(similarity),  # Konvertiere numpy float zu Python float
                    node_data["embedding_model"]
                ))

            # Sort by similarity score in descending order
            similarities.sort(key=lambda x: x[1], reverse=True)
            
            # Return top-k matches or all matches if top_k is None
            return similarities[:top_k] if top_k is not None else similarities

        except Exception as e:
            logger.error(f"Error finding best match: {str(e)}")
            return []

    async def find_nodes_above_threshold(self, query: str, model_name: str = "nomic-embed-text", threshold: float = 0.8) -> List[Tuple[str, float, str]]:
        """
        Find all nodes with similarity above a threshold using Ollama's embeddings
        Args:
            query: The query text
            model_name: Name of the embedding model to use
            threshold: Minimum similarity score (between 0 and 1)
        Returns:
            List[Tuple[str, float, str]]: List of (node_id, similarity_score, embedding_model) tuples
        """
        try:
            # Get all matches
            all_matches = await self.find_best_match(query, model_name, top_k=None)
            
            # Filter by threshold
            return [match for match in all_matches if match[1] >= threshold]

        except Exception as e:
            logger.error(f"Error finding nodes above threshold: {str(e)}")
            return []
