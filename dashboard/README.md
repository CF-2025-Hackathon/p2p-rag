# P2P Network Dashboard

A simple server that listens for P2P-RAG network announcements and provides a web dashboard.

## Features

- Listens on port 9999 for incoming P2P-Rag expertise announcements
- Stores node information in an SQLite database (prevents duplicates)
- Provides a dashboard UI on port 3000 to view all nodes in the network
- Automatically refreshes dashboard data every 10 seconds
- Tracks node announcements with timestamps
- Automatically removes nodes that haven't announced in 2 minutes

## Setup

1. Make sure you have Python 3.6+ installed
2. Install dependencies:

```bash
pip install -r requirements.txt
```

## Running the server

```bash
python server.py
```

This will:
- Start the expertise server on port 9999
- Start the dashboard server on port 3000
- Create an SQLite database file (`p2p_network.db`) to store node data
- Generate HTML dashboard in the `public` directory

## API Endpoints

### Expertise Server (Port 9999)

Accepts POST requests to `/expertise` with JSON payload:

```json
{
    "nodeId":"12D3KooWE9AZaabAnMyBwEbZTMN73EWat2YhV9ViyXZpzZ9iUaMJ",
    "embeddings": [
    {
        "key": "machine_learning",
        "expertise": "machine learning",
        "model": "nomic-embed-text",
        "vector": [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0]
    },
    {
        "key": "go_programming",
        "expertise": "go programming",
        "model": "nomic-embed-text",
        "vector": [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0]
    }]
}
```

### Dashboard Server (Port 3000)

- Web dashboard: `http://localhost:3000/`
- API endpoint: `http://localhost:3000/api/nodes` (returns JSON list of all nodes)