from typing import List
from fastapi import HTTPException
import logging
import ollama
from ollama import AsyncClient

# Logging configuration
logger = logging.getLogger(__name__)

async def get_embedding_from_ollama(text: str, model_name: str = "nomic-embed-text") -> List[float]:
    """
    Gets embeddings from Ollama API using the official ollama-python library
    Args:
        text: The text to be embedded
        model_name: The name of the Ollama model to use (default: nomic-embed-text)
    Returns:
        List[float]: The embedding vector
    Raises:
        HTTPException: If the Ollama API request fails
    """
    try:
        # Initialize client with explicit host to ensure connection
        client = AsyncClient(host='http://localhost:11434')
        
        # Get embeddings
        response = await client.embeddings(
            model=model_name,
            prompt=text
        )
        
        # Response format changed in newer versions
        if hasattr(response, 'embedding'):
            return response.embedding
        elif isinstance(response, list):
            return response[0]
        elif isinstance(response, dict) and 'embedding' in response:
            return response['embedding']
        else:
            raise ValueError(f"Unexpected response format: {response}")
        
    except Exception as e:
        logger.error(f"Error getting embedding from Ollama: {str(e)}")
        logger.error(f"Response type: {type(response)}")
        logger.error(f"Response content: {response}")
        raise HTTPException(
            status_code=500,
            detail=f"Failed to get embedding from Ollama: {str(e)}"
        ) 