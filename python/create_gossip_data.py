import asyncio
import json
from datetime import datetime
import logging
from ollama import AsyncClient

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

async def create_gossip_data():
    try:
        # Initialize Ollama client
        client = AsyncClient(host='http://localhost:11434')
        model_name = "nomic-embed-text"
        
        # Example texts for each node
        node_texts = {
            "node1": "Ich bin ein Experte in der Datenanalyse und habe eine große Erfahrung mit Python und SQL.",
            "node2": "Ich bin ein Experte in Biersorten und habe eine große Erfahrung mit der Bierherstellung.",
            "node3": "Ich bin ein Experte in italienischen Rezepten und habe eine große Erfahrung mit der italienischen Küche."
        }
        
        # Create gossip data
        gossip_data = {}
        
        for node_id, text in node_texts.items():
            logger.info(f"Creating embedding for {node_id} with text: {text}")
            
            try:
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
                    logger.error(f"Unexpected response format: {response}")
                    logger.error(f"Response type: {type(response)}")
                    continue
                
                # Store in gossip data
                gossip_data[node_id] = {
                    "embedding_model": model_name,
                    "vector": vector,
                    "last_updated": datetime.now().isoformat()
                }
                logger.info(f"Successfully created embedding for {node_id}")
                
            except Exception as e:
                logger.error(f"Error processing node {node_id}: {str(e)}")
                continue
        
        if gossip_data:
            # Save to file
            with open("gossip_data.json", "w", encoding="utf-8") as f:
                json.dump(gossip_data, f, indent=2, ensure_ascii=False)
                
            logger.info("Gossip data created successfully!")
            logger.info(f"Nodes created: {list(gossip_data.keys())}")
        else:
            logger.error("No gossip data was created!")
        
    except Exception as e:
        logger.error(f"Error creating gossip data: {str(e)}")
        raise

if __name__ == "__main__":
    asyncio.run(create_gossip_data()) 