Feature: API interface
  In order to control the P2P client
  As an API user
  I need to send REST request to it

  Scenario: reading empty expertise set
    When I send "GET" request to "/expertise"
    Then the response code should be 200
    And the response payload should match json:
    """json
    {
      "topics": [
      ]
    }
    """

  Scenario: reading non empty expertise set
    Given System is an expert in:
    """json
    {
        "embeddings": [
          {
            "key": "machine_learning",
            "expertise": "machine learning",
            "model": "nomic-embed-text",
            "vector": [0.0, 0.1, 0.2]
          }
        ]
    }
    """
    When I send "GET" request to "/expertise"
    Then the response code should be 200
    And the response payload should match json:
    """json
    {
      "topics": [
        {
          "embeddings": null
        },
        {
          "embeddings": [
            {
              "key": "machine_learning",
              "expertise": "machine learning",
              "model": "nomic-embed-text",
              "vector": [0.0, 0.1, 0.2]
            }
          ]
        }
      ]
    }
    """