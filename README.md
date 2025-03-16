# Peer-to-Peer Federated RAG Framework

## About the Hackathon
See [project page](https://hackathon.cloudfest.com/project/peer-to-peer-federated-rag-framework/) for more details.

### General
Part of this code comes from the go-libp2p library, mainly from
its [examples](https://github.com/libp2p/go-libp2p/tree/master/examples/chat-with-rendezvous).

See also [edgevpn](https://github.com/mudler/edgevpn) for more hints.

## Documentation
### P2P Communcation Flow
#### First Flow
Starting in the project we first wanted to design the communication flow.

![First Communication Flow](https://github.com/CF-2025-Hackathon/p2p-rag/blob/main/documentation/first_draft.svg)

#### Exposure of new nodes / knowledge in the system
As soon as a new nodes joins the system, the nodes exposes its particular knowledge.

![Register in the network](https://github.com/CF-2025-Hackathon/p2p-rag/blob/main/documentation/expertise_exposure_A.svg)

Defining the payload:
![First Communication Flow](https://github.com/CF-2025-Hackathon/p2p-rag/blob/main/documentation/expertise_exposure.svg)

#### Querying the network
To query the network, the following flow should be implemented:

![First Communication Flow](https://github.com/CF-2025-Hackathon/p2p-rag/blob/docu/documentation/querying.svg)


### Decisions
- Embeddings and vectors are used instead of plain text to ensure scalability.
