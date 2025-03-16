# API requests

 
## Announce a topic (client -> network):
The client announces a RAG database to the network:

``` shell
curl -X POST http://localhost:8888/expertise -H "Content-Type: application/json" -d '...'
```

``` json

{
    "embeddings": [
    {
        "key": "machine_learning",
        "expertise": "machine learning",
        "model": "nomic-embed-text",
        "vector": [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0]
    }
    ,
    {
        "key": "go_programming",
        "expertise": "go programming",
        "model": "nomic-embed-text",
        "vector": [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0]
    }]
}
```

## Announce a topic (network -> client, through gossip):

``` shell
curl -X POST http://localhost:9999/expertise -H "Content-Type: application/json" -d '...'
```

``` json
{
    "nodeId":"12D3KooWE9AZaabAnMyBwEbZTMN73EWat2YhV9ViyXZpzZ9iUaMJ",
    "embeddings": [
    {
        "key": "machine_learning",
        "expertise": "machine learning",
        "model": "nomic-embed-text",
        "vector": [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0]
    }
    ,
    {
        "key": "go_programming",
        "expertise": "go programming",
        "model": "nomic-embed-text",
        "vector": [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0]
    }]
}
```

## Perform a query (client -> network -> knowledge base:

``` shell
curl -X POST http://localhost:8888/query -H "Content-Type: application/json" -d '...'
```

``` json

{
    "nodeId":"12D3KooWE9AZaabAnMyBwEbZTMN73EWat2YhV9ViyXZpzZ9iUaMJ",
    "queryId": "1234567890",
    "embedding": 
    {
        "expertise_key": "machine_learning",
        "model": "nomic-embed-text",
        "vector": [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        "match_count": 15
    }
}
```

## Answer to query:
``` json
{
    "nodeId":"12D3KooWE9AZaabAnMyBwEbZTMN73EWat2YhV9ViyXZpzZ9iUaMJ",
    "query": 
    {
        "queryId": "1234567890",
        "model": "nomic-embed-text",
        "vector": [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0]
    },
    "answer": 
    {
        "documents": [
              {
                    "title": "",
                    "content": "",
                    "source": "",
                    "metadata": {}
              }
        ]
    }
}
```
