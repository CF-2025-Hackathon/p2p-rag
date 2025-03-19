Feature: API interface
  In order to control the P2P client
  As an API user
  I need to send REST request to it

  Scenario: reading expertise
    When I send "GET" request to "/expertise"
    Then the response code should be 200
    And the response payload should match json:
    """json
    {
      "topics": [
      ]
    }
    """