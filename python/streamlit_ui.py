from __future__ import annotations
from typing import Literal, TypedDict
import asyncio
import os

import streamlit as st
import json
import logfire
from supabase import Client
from openai import AsyncOpenAI
from nodeselector import NodeSelector
import uuid
import httpx
# Import all the message part classes
from pydantic_ai.messages import (
    ModelMessage,
    ModelRequest,
    ModelResponse,
    SystemPromptPart,
    UserPromptPart,
    TextPart,
    ToolCallPart,
    ToolReturnPart,
    RetryPromptPart,
    ModelMessagesTypeAdapter
)
from pydantic_ai_expert import pydantic_ai_expert, PydanticAIDeps, get_embedding, database_source

# Load environment variables
from dotenv import load_dotenv

load_dotenv()

openai_client = AsyncOpenAI(
    base_url=os.getenv('LLM_BASE_URL'),
    api_key="dummy_key", # required, but unused
)

supabase: Client = Client(
    os.getenv("SUPABASE_URL"),
    os.getenv("SUPABASE_SERVICE_KEY")
)

# Configure logfire to suppress warnings (optional)
logfire.configure(send_to_logfire='never')


class ChatMessage(TypedDict):
    """Format of messages sent to the browser/API."""

    role: Literal['user', 'model']
    timestamp: str
    content: str


def display_message_part(part):
    """
    Display a single part of a message in the Streamlit UI.
    Customize how you display system prompts, user prompts,
    tool calls, tool returns, etc.
    """
    # system-prompt
    if part.part_kind == 'system-prompt':
        with st.chat_message("system"):
            st.markdown(f"**System**: {part.content}")
    # user-prompt
    elif part.part_kind == 'user-prompt':
        with st.chat_message("user"):
            st.markdown(part.content)
    # text
    elif part.part_kind == 'text':
        with st.chat_message("assistant"):
            st.markdown(part.content)


async def get_relevant_context(user_input: str, deps: PydanticAIDeps, embedding_model: str) -> str:
    """Hole relevante Dokumentation für die Benutzeranfrage."""
    try:
        node_selector = NodeSelector()
        best_matches = await node_selector.find_nodes_above_threshold(
            query=user_input,
            model_name=embedding_model,
            top_k=1,    
            threshold=0.6
        )

        async with httpx.AsyncClient() as client:
            response = await client.post(
                "http://localhost:8888/query",
                json={
                    "nodeId": best_matches[0]['node_id'],
                    "queryId": str(uuid.uuid4()),
                    "embedding": {
                        "expertise_key": "machine_learning",
                        "model": best_matches[0]['embedding_model'],
                        "vector": best_matches[1],
                        "match_count": 15
                    }
                }
            )
            
            client_answer = response.json()

        # Log des RAG-Ergebnisses
        print("RAG Rohergebnis:")
        print(json.dumps(client_answer, indent=2, ensure_ascii=False))

        if not client_answer or "answer" not in client_answer or not client_answer["answer"]["documents"]:
            return "Keine relevanten Informationen gefunden."

        formatted_chunks = []
        for doc in client_answer["answer"]["documents"]:
            chunk_text = f"""
            # {doc['title']}
            Quelle: {doc['source']}
            
            {doc['content']}
            """
            formatted_chunks.append(chunk_text)

        return "\n\n---\n\n".join(formatted_chunks)

    except Exception as e:
        print(f"Fehler beim Abrufen der Informationen: {e}")
        return f"Fehler beim Abrufen der Informationen: {str(e)}"

async def run_agent_with_streaming(user_input: str):
    with st.status("Suche relevante Informationen...") as status:
        deps = PydanticAIDeps(
            supabase=supabase,
            client=openai_client
        )
        relevant_context = await get_relevant_context(user_input, deps, embedding_model="nomic-embed-text")
        status.update(label="Generiere Antwort...", state="running")
        
        # Erweitere die Benutzeranfrage um den Kontext
        enhanced_prompt = f"""
        Benutzeranfrage: {user_input}
        
        Relevante Informationen:
        {relevant_context}
        
        Bitte beantworte die Frage basierend auf diesen Informationen.
        """

        # Run the agent in a stream
        async with pydantic_ai_expert.run_stream(
                system_prompt=enhanced_prompt,
                deps=deps,
                message_history=st.session_state.messages[:-1],
                tools= []
        ) as result:
            print(result)
            # We'll gather partial text to show incrementally
            partial_text = ""
            message_placeholder = st.empty()

            # Render partial text as it arrives
            async for chunk in result.stream_text(delta=True):
                partial_text += chunk
                message_placeholder.markdown(partial_text)

            # Now that the stream is finished, we have a final result.
            # Add new messages from this run, excluding user-prompt messages
            filtered_messages = [msg for msg in result.new_messages()
                                 if not (hasattr(msg, 'parts') and
                                         any(part.part_kind == 'user-prompt' for part in msg.parts))]
            st.session_state.messages.extend(filtered_messages)

            # Add the final response to the messages
            st.session_state.messages.append(
                ModelResponse(parts=[TextPart(content=partial_text)])
            )



async def main():
    st.title("Cloudfest 3PO")
    st.write("Ask any question related to Cloudfest event and will do my best to help you out.")

    # Initialize chat history in session state if not present
    if "messages" not in st.session_state:
        st.session_state.messages = []

    # Display all messages from the conversation so far
    # Each message is either a ModelRequest or ModelResponse.
    # We iterate over their parts to decide how to display them.
    for msg in st.session_state.messages:
        if isinstance(msg, ModelRequest) or isinstance(msg, ModelResponse):
            for part in msg.parts:
                display_message_part(part)

    # Chat input for the user
    user_input = st.chat_input("What questions do you have about Cloudfest?")

    if user_input:
        # We append a new request to the conversation explicitly
        st.session_state.messages.append(
            ModelRequest(parts=[UserPromptPart(content=user_input)])
        )

        # Display user prompt in the UI
        with st.chat_message("user"):
            st.markdown(user_input)

        # Display the assistant's partial response while streaming
        with st.chat_message("assistant"):
            # Actually run the agent now, streaming the text
            await run_agent_with_streaming(user_input)


if __name__ == "__main__":
    asyncio.run(main())