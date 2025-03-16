# API requests

## Announce a topic:

``` json
curl -X POST http://localhost:8888/expertise -H "Content-Type: application/json" -d '
{
    "embeddings": [
    {
    "model": "nomic-embed-text",
    "vector": [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0]
    }
    ,
    {
    "model": "nomic-embed-text",
    "vector": [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0]
    }]
}'   
```
## Perform a query:

``` json
curl -X POST http://localhost:8888/query -H "Content-Type: application/json" -d '
{
    "nodeId":"12D3KooWE9AZaabAnMyBwEbZTMN73EWat2YhV9ViyXZpzZ9iUaMJ",
    "embedding": 
    {
        "queryId": "1234567890",
        "model": "nomic-embed-text",
        "vector": [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
        "match_count": 15
    }
}'
```

## Answer to query:
``` json
{
    "nodeId":"12D3KooWE9AZaabAnMyBwEbZTMN73EWat2YhV9ViyXZpzZ9iUaMJ",
    "query": 
    {
        "model": "nomic-embed-text",
        "vector": [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0]
    },
    "answer": 
    {
        "documents": [
              {
                    "title": "",
                    "content": "",
                    "source": ""
              }
        ]
    }
}
```
