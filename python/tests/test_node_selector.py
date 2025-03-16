import asyncio
import logging
import sys
import os
from dotenv import load_dotenv

# Fügen Sie das Hauptverzeichnis zum Python-Path hinzu
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(__file__))))

from python.nodeselector import NodeSelector

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Load environment variables
load_dotenv()

# Get Supabase credentials
supabase_url = os.getenv("SUPABASE_URL")
supabase_key = os.getenv("SUPABASE_SERVICE_KEY")

if not supabase_url or not supabase_key:
    raise ValueError("SUPABASE_URL and SUPABASE_SERVICE_KEY must be set in .env file")

async def test_node_selection():
    try:
        # Test query
        query = "I love Beer, what would you recommend? I'm looking for an beer expert"
        model_name = "nomic-embed-text"
        
        # Initialize node selector with Supabase credentials
        selector = NodeSelector(supabase_url=supabase_url, supabase_key=supabase_key)
        
        # Find best match
        logger.info(f"Finding best matches for query: '{query}'")
        best_matches = await selector.find_best_match(query, model_name, top_k=1)
        
        if best_matches:
            logger.info("\nTop 1 Best Matches:")
            for match in best_matches:
                logger.info(f"Node: {match['node_id']}")
                logger.info(f"Similarity Score: {match['similarity_score']:.4f}")
                logger.info(f"Model: {match['embedding_model']}")
                logger.info(f"Expertise: {match['expertise']}")
                logger.info(f"Embedding Key: {match['embedding_key']}")
                logger.info("---")
        else:
            logger.warning("No matches found!")
            
        # Find nodes above threshold
        logger.info("\nFinding nodes above threshold (0.6)...")
        threshold_matches = await selector.find_nodes_above_threshold(query, model_name, threshold=0.6)
        
        logger.info(f"Threshold matches: {threshold_matches}")
        if threshold_matches:
            logger.info("\nNodes above threshold:")
            for match in threshold_matches:
                logger.info(f"Node: {match['node_id']}")
                logger.info(f"Similarity Score: {match['similarity_score']:.4f}")
                logger.info(f"Model: {match['embedding_model']}")
                logger.info(f"Expertise: {match['expertise']}")
                logger.info(f"Embedding Key: {match['embedding_key']}")
                logger.info("---")
        else:
            logger.warning("No nodes found above threshold!")

            
    except Exception as e:
        logger.error(f"Error during test: {str(e)}")
        raise

if __name__ == "__main__":
    asyncio.run(test_node_selection()) 