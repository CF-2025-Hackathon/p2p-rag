import asyncio
import httpx
import json
import logging

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

async def test_query():
    url = "http://localhost:8000/query"
    data = {
        "question": "I am hungry and would like to try something new. Can you recommend something?",
        "embedding_model": "nomic-embed-text"
    }
    
    try:
        async with httpx.AsyncClient() as client:
            logger.info(f"Sending request to {url}")
            logger.info(f"Request data: {json.dumps(data, indent=2)}")
            
            response = await client.post(url, json=data)
            result = response.json()
            
            logger.info(f"Status Code: {response.status_code}")
            logger.info(f"Response Headers: {dict(response.headers)}")
            logger.info("\nResponse Body:")
            logger.info(json.dumps(result, indent=2))
            
            if response.status_code == 200:
                # logger.info(f"\nVector dimension: {len(result['vector'])}")
                logger.info(f"First few values: {result['vector'][:5]}")
            else:
                logger.error("No vector in response or request failed")
                
    except Exception as e:
        logger.error(f"Error during test: {str(e)}")
        raise

if __name__ == "__main__":
    asyncio.run(test_query()) 