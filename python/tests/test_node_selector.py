import asyncio
import logging
from ..nodeselector import NodeSelector

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

async def test_node_selection():
    try:
        # Test query
        query = "Ich liebe italienisches Essen, was kannst du mir empfehlen?"
        model_name = "nomic-embed-text"
        
        # Initialize node selector
        selector = NodeSelector()
        
        # Find best match
        logger.info(f"Finding best matches for query: '{query}'")
        best_matches = await selector.find_best_match(query, model_name, top_k=3)
        
        if best_matches:
            logger.info("\nTop 1 Best Matches:")
            for node_id, similarity, model in best_matches:
                logger.info(f"Node: {node_id}")
                logger.info(f"Similarity Score: {similarity:.4f}")
                logger.info(f"Model: {model}")
                logger.info("---")
        else:
            logger.warning("No matches found!")
            
        # Find nodes above threshold
        logger.info("\nFinding nodes above threshold (0.8)...")
        threshold_matches = await selector.find_nodes_above_threshold(query, model_name, threshold=0.8)
        
        if threshold_matches:
            logger.info("\nNodes above threshold:")
            for node_id, similarity, model in threshold_matches:
                logger.info(f"Node: {node_id}")
                logger.info(f"Similarity Score: {similarity:.4f}")
                logger.info(f"Model: {model}")
                logger.info("---")
        else:
            logger.warning("No nodes found above threshold!")
            
    except Exception as e:
        logger.error(f"Error during test: {str(e)}")
        raise

if __name__ == "__main__":
    asyncio.run(test_node_selection()) 