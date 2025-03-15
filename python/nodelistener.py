from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
import json
from typing import Dict, List
import os
from datetime import datetime
import logging
from .embedding import get_embedding_from_ollama

# Logging configuration
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI()

# Model for incoming gossip data
class GossipData(BaseModel):
    node_id: str
    embedding_model: str
    vector: List[float]

# Model for query requests
class QueryRequest(BaseModel):
    question: str
    embedding_model: str = "nomic-embed-text"  # Default to nomic-embed-text

# Storage location for the JSON file
GOSSIP_FILE = "gossip_data.json"

# In-Memory cache for gossip data
gossip_cache: Dict[str, dict] = {}

def load_gossip_data():
    """Loads existing gossip data from the JSON file"""
    if os.path.exists(GOSSIP_FILE):
        try:
            with open(GOSSIP_FILE, 'r') as f:
                return json.load(f)
        except json.JSONDecodeError:
            logger.warning("Corrupted JSON file found. Creating new empty file.")
            return {}
    return {}

def save_gossip_data():
    """Saves gossip data to the JSON file"""
    with open(GOSSIP_FILE, 'w') as f:
        json.dump(gossip_cache, f, indent=4)

@app.on_event("startup")
async def startup_event():
    """Executed when the application starts"""
    global gossip_cache
    gossip_cache = load_gossip_data()
    logger.info(f"Server started. Loaded {len(gossip_cache)} existing entries.")

@app.post("/topic")
async def receive_gossip(gossip: GossipData):
    """
    Receives gossip data from other nodes.
    If the node_id already exists, the entry will be updated.
    If the node_id is new, a new entry will be created.
    """
    try:
        # Reload latest data from file before updating
        global gossip_cache
        gossip_cache = load_gossip_data()
        
        is_update = gossip.node_id in gossip_cache
        
        # Update or add new gossip data
        gossip_cache[gossip.node_id] = {
            "embedding_model": gossip.embedding_model,
            "vector": gossip.vector,
            "last_updated": datetime.now().isoformat()
        }
        
        # Save the updated data
        save_gossip_data()
        
        action_msg = "updated" if is_update else "added"
        logger.info(f"Node {gossip.node_id} successfully {action_msg}")
        
        return {
            "status": "success", 
            "message": f"Gossip from Node {gossip.node_id} successfully {action_msg}",
            "action": "updated" if is_update else "created"
        }
    
    except Exception as e:
        logger.error(f"Error processing gossip: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Error processing gossip: {str(e)}")

@app.post("/query")
async def process_query(query: QueryRequest):
    """
    Processes a query by vectorizing it with Ollama's nomic-embed-text model
    """
    try:
        # Get vector from Ollama
        vector = await get_embedding_from_ollama(query.question, query.embedding_model)
        
        return {
            "status": "success",
            "vector": vector,
            "model_used": query.embedding_model
        }
        
    except Exception as e:
        logger.error(f"Error processing query: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Error processing query: {str(e)}")

@app.get("/nodes")
async def get_nodes():
    """
    Returns all stored node information
    """
    return gossip_cache

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000) 