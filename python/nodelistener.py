from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
import json
from typing import Dict, List
import os
from datetime import datetime
import logging
import uuid
from embedding import get_embedding_from_ollama
from nodeselector import NodeSelector
from supabase import create_client, Client
from os import environ
from dotenv import load_dotenv
from pydantic_ai_expert import PydanticAIDeps, retrieve_relevant_documentation, pydantic_ai_expert
from openai import AsyncOpenAI

# Logging configuration
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI()

load_dotenv()

# Supabase Konfiguration
supabase_url = os.getenv("SUPABASE_URL")
supabase_key = os.getenv("SUPABASE_SERVICE_KEY")
supabase: Client = create_client(supabase_url, supabase_key)

# OpenAI Client Konfiguration
openai_client = AsyncOpenAI(
    base_url=os.getenv('LLM_BASE_URL'),
    api_key="dummy_key"
)

# Initialize deps
deps = PydanticAIDeps(
    supabase=supabase,
    client=openai_client
)

# Aktualisierte Modelle für das neue Format
class EmbeddingData(BaseModel):
    key: str
    expertise: str
    model: str
    vector: List[float]

class GossipData(BaseModel):
    nodeId: str
    embeddings: List[EmbeddingData]

# Model for query requests
class QueryRequest(BaseModel):
    question: str
    embedding_model: str = "nomic-embed-text"  # Default to nomic-embed-text

# Storage location for the JSON file
GOSSIP_FILE = "gossip_data.json"

# In-Memory cache for gossip data
gossip_cache: Dict[str, dict] = {}

async def load_gossip_data() -> Dict[str, dict]:
    """Lädt existierende Gossip-Daten aus der Supabase-Tabelle"""
    try:
        response = supabase.table('node_embeddings').select('*').execute()
        gossip_data = {}
        
        for row in response.data:
            node_id = row['node_id']
            if node_id not in gossip_data:
                gossip_data[node_id] = {
                    "embeddings": []
                }
            
            gossip_data[node_id]["embeddings"].append({
                "key": row['embedding_key'],
                "expertise": row['expertise'],
                "model": row['embedding_model'],
                "vector": row['vector']
            })
            
        return gossip_data
    except Exception as e:
        logger.error(f"Fehler beim Laden der Daten aus Supabase: {str(e)}")
        return {}

async def save_gossip_data(node_id: str, embeddings: List[EmbeddingData]):
    """Speichert alle Embeddings für einen Node in der Supabase-Tabelle"""
    try:
        # Erstelle Liste von Einträgen für Bulk Upsert
        entries = [
            {
                'node_id': node_id,
                'embedding_key': emb.key,
                'expertise': emb.expertise,
                'embedding_model': emb.model,
                'vector': emb.vector,
                'last_updated': datetime.now().isoformat()
            }
            for emb in embeddings
        ]
        
        # Lösche zuerst alle existierenden Einträge für diesen node_id
        await supabase.table('node_embeddings').delete().eq('node_id', node_id).execute()
        
        # Füge die neuen Einträge hinzu
        response = await supabase.table('node_embeddings').insert(entries).execute()
        return response
    except Exception as e:
        logger.error(f"Fehler beim Speichern in Supabase: {str(e)}")
        raise

@app.on_event("startup")
async def startup_event():
    """Executed when the application starts"""
    global gossip_cache
    gossip_cache = await load_gossip_data()
    logger.info(f"Server started. Loaded {len(gossip_cache)} existing entries.")

@app.post("/expertise")
async def receive_gossip(gossip: GossipData):
    """
    Empfängt Gossip-Daten von anderen Nodes.
    Ersetzt alle existierenden Embeddings für die gegebene node_id.
    """
    try:
        # Speichere die neuen Daten
        await save_gossip_data(gossip.nodeId, gossip.embeddings)
        
        # Aktualisiere den Cache
        global gossip_cache
        gossip_cache = await load_gossip_data()
        
        logger.info(f"Node {gossip.nodeId} erfolgreich aktualisiert mit {len(gossip.embeddings)} Embeddings")
        
        return {
            "status": "success", 
            "message": f"Gossip von Node {gossip.nodeId} erfolgreich gespeichert",
            "embeddings_count": len(gossip.embeddings)
        }
    
    except Exception as e:
        logger.error(f"Fehler bei der Verarbeitung des Gossips: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Fehler bei der Verarbeitung des Gossips: {str(e)}")

@app.post("/query")
async def process_query(query: QueryRequest):
    """
    Processes a query by vectorizing it with Ollama's nomic-embed-text model
    and finds the best matching nodes
    """
    try:
        # Get vector from Ollama
        # vector = await get_embedding_from_ollama(query.question, query.embedding_model)
        
        # Run the agent in a stream
        result = await retrieve_relevant_documentation(deps, query)


        # Erstelle die formatierte Antwort
        formatted_response = {
            "nodeId": query['nodeId'],
            "query": {
                "queryId": query['queryId'],
                "model": query.embedding_model,
                "vector": query['embedding']['vector']  
                    },
            "answer": {
                "documents": [
                    {
                        "title": doc.get("title", ""),
                        "content": doc.get("content", ""),
                        "source": doc.get("source", ""),
                        "metadata": doc.get("metadata", {})
                    }
                    for doc in result.get("documents", [])
                ]
            }
        }

        return formatted_response
    
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