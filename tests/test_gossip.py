import sys
from pathlib import Path
sys.path.append(str(Path(__file__).parent.parent))

import pytest
from fastapi.testclient import TestClient
import json
from python.nodelistener import app

client = TestClient(app)

def test_gossip_endpoint():
    # Test-Daten
    test_data = {
        "nodeId": "12D3KooWE9AZaabAnMyBwEbZTMN73EWat2YhV9ViyXZpzZ9iUaMJ",
        "embeddings": [
            {
                "key": "machine_learning",
                "expertise": "machine learning",
                "model": "nomic-embed-text",
                "vector": [0] * 42  # Array mit 42 Nullen
            },
            {
                "key": "go_programming",
                "expertise": "go programming",
                "model": "nomic-embed-text",
                "vector": [0] * 42
            }
        ]
    }

    # Sende POST-Request an /topic Endpoint
    response = client.post("/topic", json=test_data)
    
    # Überprüfe Response
    assert response.status_code == 200
    assert response.json()["status"] == "success"
    assert response.json()["embeddings_count"] == 2

    # Überprüfe, ob die Daten korrekt gespeichert wurden
    nodes_response = client.get("/nodes")
    assert nodes_response.status_code == 200
    
    stored_data = nodes_response.json()
    assert test_data["nodeId"] in stored_data
    assert len(stored_data[test_data["nodeId"]]["embeddings"]) == 2

def test_gossip_endpoint_invalid_data():
    # Test mit ungültigen Daten
    invalid_data = {
        "nodeId": "12D3KooWE9AZaabAnMyBwEbZTMN73EWat2YhV9ViyXZpzZ9iUaMJ",
        "embeddings": [
            {
                "key": "machine_learning",
                "expertise": "machine learning",
                "model": "nomic-embed-text",
                "vector": [0] * 41  # Ungültige Vektorlänge
            }
        ]
    }

    response = client.post("/topic", json=invalid_data)
    assert response.status_code == 500

def test_gossip_endpoint_multiple_updates():
    # Erster Datensatz
    first_data = {
        "nodeId": "12D3KooWE9AZaabAnMyBwEbZTMN73EWat2YhV9ViyXZpzZ9iUaMJ",
        "embeddings": [
            {
                "key": "machine_learning",
                "expertise": "machine learning",
                "model": "nomic-embed-text",
                "vector": [0] * 42
            }
        ]
    }

    # Zweiter Datensatz (Update)
    second_data = {
        "nodeId": "12D3KooWE9AZaabAnMyBwEbZTMN73EWat2YhV9ViyXZpzZ9iUaMJ",
        "embeddings": [
            {
                "key": "machine_learning",
                "expertise": "machine learning updated",
                "model": "nomic-embed-text",
                "vector": [1] * 42
            }
        ]
    }

    # Sende erste Anfrage
    response1 = client.post("/topic", json=first_data)
    assert response1.status_code == 200

    # Sende Update-Anfrage
    response2 = client.post("/topic", json=second_data)
    assert response2.status_code == 200

    # Überprüfe aktualisierte Daten
    nodes_response = client.get("/nodes")
    stored_data = nodes_response.json()
    assert stored_data[first_data["nodeId"]]["embeddings"][0]["expertise"] == "machine learning updated"

if __name__ == "__main__":
    pytest.main([__file__]) 