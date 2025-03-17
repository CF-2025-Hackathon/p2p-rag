from typing import Dict, List, Tuple
import logging
from pathlib import Path
import ollama
from ollama import AsyncClient
import numpy as np
from numpy.linalg import norm
from supabase import create_client, Client
from dotenv import load_dotenv
import os

# Logging configuration
logger = logging.getLogger(__name__)


load_dotenv()

# Supabase Konfiguration
supabase_url = os.getenv("SUPABASE_URL")
supabase_key = os.getenv("SUPABASE_SERVICE_KEY")
supabase: Client = create_client(supabase_url, supabase_key)


class NodeSelector:
    def __init__(self, supabase_url: str, supabase_key: str):
        """
        Initialize the NodeSelector with Supabase credentials
        Args:
            supabase_url: The URL of your Supabase project
            supabase_key: The anon/service key of your Supabase project
        """
        self.client = AsyncClient(host='http://localhost:11434')
        self.supabase: Client = create_client(supabase_url, supabase_key)
        self.supabase_url = supabase_url
        self.supabase_key = supabase_key
        
    async def load_gossip_data(self) -> Dict:
        """
        Load the node embeddings data from Supabase
        Returns:
            Dict: The node embeddings data with structure:
            {
                node_id: {
                    "vector": vector,
                    "embedding_model": str,
                    "embedding_key": str,
                    "expertise": str,
                    "last_updated": datetime
                }
            }
        """
        try:
            response = self.supabase.table('node_embeddings').select(
                'node_id',
                'embedding_key',
                'expertise',
                'embedding_model',
                'vector',
                'last_updated'
            ).execute()
            
            # Convert the response to the expected format
            gossip_data = {}
            for record in response.data:
                gossip_data[record['node_id']] = {
                    "vector": record['vector'],
                    "embedding_model": record['embedding_model'],
                    "embedding_key": record['embedding_key'],
                    "expertise": record['expertise'],
                    "last_updated": record['last_updated']
                }
            return gossip_data
        except Exception as e:
            logger.error(f"Error loading node embeddings data from Supabase: {str(e)}")
            return {}

    async def find_best_match(self, query: str, model_name: str = "nomic-embed-text", top_k: int = 1) -> List[Dict]:
        """
        Find the best matching nodes for a query using Ollama's embeddings and Supabase vector similarity
        Args:
            query: The query text
            model_name: Name of the embedding model to use
            top_k: Number of best matches to return
        Returns:
            List[Dict]: List of dictionaries containing:
                - node_id: str
                - similarity_score: float
                - embedding_model: str
                - embedding_key: str
                - expertise: str
                - last_updated: datetime
        """
        try:
            query_response = await self.client.embeddings(
                model=model_name,
                prompt=query
            )
            
            query_embedding = query_response['embedding']
            logger.info(f"Embedding dimension: {len(query_embedding)}")  # Sollte 768 sein
            
            # Convert response to dict if it's not already
            if not isinstance(query_response, dict):
                query_response = query_response.__dict__
                
            # Extract the embedding
            if 'embedding' not in query_response:
                logger.error(f"Unexpected response format: {query_response}")
                return []
                
            # Use Supabase's vector similarity search
            result = self.supabase.rpc(
                'match_nodes_embeddings',  # Korrekte Funktion in Supabase
                {
                    'query_embedding': query_embedding,
                    'match_count': 1  # Filter für die Node-Embeddings
                }
            ).execute()

            # Logging des Supabase-Ergebnisses
            logger.info(f"Supabase result: {result.data}")
            
            if not result.data:
                logger.warning("No matching nodes found. Raw result: %s", result)
                return []

            # Format the results
            similarities = []
            for match in result.data:
                similarities.append({
                    "node_id": match['node_id'],
                    "similarity_score": float(match['similarity']),
                    "embedding_model": match['embedding_model'],
                    "embedding_key": match['embedding_key'],
                    "expertise": match['expertise'],
                    # "last_updated": match.get('last_updated')
                })

            return similarities, query_embedding

        except Exception as e:
            logger.error(f"Error finding best match: {str(e)}")
            return []

    async def find_nodes_above_threshold(self, query: str, model_name: str = "nomic-embed-text", threshold: float = 0.6) -> List[Dict]:
        """
        Find all nodes with similarity above a threshold using Ollama's embeddings
        Args:
            query: The query text
            model_name: Name of the embedding model to use
            threshold: Minimum similarity score (between 0 and 1)
        Returns:
            List[Dict]: List of matches above threshold
        """
        try:
            # Get all matches
            all_matches = await self.find_best_match(query, model_name, top_k=None)
            
            # Filter by threshold
            return [match for match in all_matches if match["similarity_score"] >= threshold]

        except Exception as e:
            logger.error(f"Error finding nodes above threshold: {str(e)}")
            return []
