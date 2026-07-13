"""Local semantic-vector service for the GuisedUp API."""

from __future__ import annotations

import os
import uuid
from pathlib import Path

import chromadb
from flask import Flask, jsonify, request
from sentence_transformers import SentenceTransformer

MODEL_NAME = os.getenv("EMBEDDING_MODEL", "all-MiniLM-L6-v2")
DATA_PATH = Path(os.getenv("CHROMA_PATH", Path(__file__).parent / "chroma-data"))

app = Flask(__name__)
model = SentenceTransformer(MODEL_NAME)
client = chromadb.PersistentClient(path=str(DATA_PATH))
collection = client.get_or_create_collection(
    name="posts", metadata={"hnsw:space": "cosine"}
)


def json_text(field: str) -> str:
    payload = request.get_json(silent=True) or {}
    value = payload.get(field)
    if not isinstance(value, str) or not value.strip():
        raise ValueError(f"'{field}' must be a non-empty string")
    return value.strip()


@app.post("/embed")
def embed():
    """Embed text and persist it under a stable UUID returned to Laravel."""
    try:
        text = json_text("text")
    except ValueError as error:
        return jsonify(error=str(error)), 422

    embedding_id = str(uuid.uuid4())
    vector = model.encode(text, normalize_embeddings=True).tolist()
    collection.add(ids=[embedding_id], documents=[text], embeddings=[vector])
    return jsonify(embedding_id=embedding_id), 201


@app.post("/search")
def search():
    """Return nearest stored post-vector ids in relevance order."""
    try:
        query = json_text("query")
    except ValueError as error:
        return jsonify(error=str(error)), 422

    payload = request.get_json(silent=True) or {}
    limit = payload.get("limit", 10)
    if not isinstance(limit, int) or not 1 <= limit <= 100:
        return jsonify(error="'limit' must be an integer between 1 and 100"), 422
    if collection.count() == 0:
        return jsonify(embedding_ids=[])

    vector = model.encode(query, normalize_embeddings=True).tolist()
    result = collection.query(query_embeddings=[vector], n_results=min(limit, collection.count()))
    return jsonify(embedding_ids=result["ids"][0])


@app.get("/health")
def health():
    return jsonify(status="ok", model=MODEL_NAME, vector_count=collection.count())
