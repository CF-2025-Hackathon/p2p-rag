import asyncio
import json
from datetime import datetime
import logging
from ollama import AsyncClient

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

async def create_gossip_data(node_id: str, text: str, model_name: str = "nomic-embed-text") -> dict:
    """
    Creates gossip data for a single node with the given text.
    
    Args:
        node_id: The ID of the node
        text: The text to create an embedding for
        model_name: Name of the embedding model (default: "nomic-embed-text")
        
    Returns:
        dict: The created gossip data for the node
        
    Raises:
        ValueError: If the model_name is invalid or not available
        ConnectionError: If the Ollama server is not reachable
    """
    try:
        # Initialize Ollama client
        client = AsyncClient(host='http://localhost:11434')
        
        logger.info(f"Creating embedding for {node_id} with text: {text} using model: {model_name}")
        
        try:
            # Validate model availability
            try:
                # Try to get model info to check if it exists
                await client.show(model=model_name)
            except Exception as model_error:
                error_msg = f"Model '{model_name}' is not available or invalid: {str(model_error)}"
                logger.error(error_msg)
                raise ValueError(error_msg)
            
            # Get embedding from Ollama
            response = await client.embeddings(
                model=model_name,
                prompt=text
            )
            
            # Convert response to dict if it's not already
            if not isinstance(response, dict):
                response = response.__dict__
            
            # Extract the embedding
            if 'embedding' in response:
                vector = response['embedding']
            else:
                error_msg = f"Unexpected response format: {response}"
                logger.error(error_msg)
                logger.error(f"Response type: {type(response)}")
                raise ValueError(error_msg)
            
            # Create gossip data for the node
            gossip_data = {
                node_id: {
                    "embedding_model": model_name,
                    "vector": vector,
                    "last_updated": datetime.now().isoformat()
                }
            }
            
            logger.info(f"Successfully created embedding for {node_id}")
            return gossip_data
            
        except ValueError as ve:
            logger.error(f"Validation error for node {node_id}: {str(ve)}")
            raise
        except Exception as e:
            logger.error(f"Error processing node {node_id}: {str(e)}")
            return {}
    
    except Exception as e:
        if "Connection refused" in str(e):
            error_msg = "Could not connect to Ollama server. Make sure it's running on http://localhost:11434"
            logger.error(error_msg)
            raise ConnectionError(error_msg) from e
        logger.error(f"Error creating gossip data: {str(e)}")
        raise

async def create_multiple_gossip_data(node_texts: dict, model_name: str = "nomic-embed-text") -> dict:
    """
    Creates gossip data for multiple nodes.
    
    Args:
        node_texts: Dictionary with node IDs as keys and texts as values
        model_name: Name of the embedding model (default: "nomic-embed-text")
        
    Returns:
        dict: The combined gossip data for all nodes
        
    Raises:
        ValueError: If the model_name is invalid or not available
        ConnectionError: If the Ollama server is not reachable
    """
    all_gossip_data = {}
    
    for node_id, text in node_texts.items():
        try:
            node_data = await create_gossip_data(node_id, text, model_name)
            all_gossip_data.update(node_data)
        except (ValueError, ConnectionError) as e:
            logger.error(f"Failed to process node {node_id}: {str(e)}")
            raise
    
    if all_gossip_data:
        # Save to file
        with open("gossip_data.json", "w", encoding="utf-8") as f:
            json.dump(all_gossip_data, f, indent=2, ensure_ascii=False)
            
        logger.info("Gossip data created successfully!")
        logger.info(f"Nodes created: {list(all_gossip_data.keys())}")
    else:
        logger.error("No gossip data was created!")
    
    return all_gossip_data

if __name__ == "__main__":
    # Example usage
    example_texts = {
        "node1": "I am an expert in data analysis with extensive experience in Python and SQL.",
        "node2": "I am a beer expert with extensive experience in brewing.",
        "node3": "I am an expert in Italian recipes with extensive experience in Italian cuisine."
    }
    # Optionally specify a different model
    try:
        asyncio.run(create_multiple_gossip_data(example_texts, model_name="nomic-embed-text"))
    except (ValueError, ConnectionError) as e:
        logger.error(f"Failed to create gossip data: {str(e)}")
        exit(1) 