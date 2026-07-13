# Guised Up – Real Connections Feed Assessment

Guised Up is a full-stack take-home assessment that implements a personalized social feed designed to favor meaningful connections over conventional engagement metrics. Rather than ranking only on likes or views, the feed combines a viewer's relationship depth with an author, semantic relevance to their recent activity, a content-authenticity heuristic, and recency.

The repository contains a Laravel 12 API, a local Flask/ChromaDB embedding service, and an Expo React Native feed client.

## Architecture

```text
┌─────────────────────────────┐
│  React Native / Expo client │
│  Feed, search, refresh      │
└──────────────┬──────────────┘
               │ HTTPS + Sanctum Bearer token
               ▼
┌─────────────────────────────┐         ┌────────────────────────────┐
│ Laravel 12 API              │────────▶│ MySQL                      │
│ Auth, posts, interactions,  │         │ users, posts, interactions │
│ feed ranking, API resources │         │ Sanctum access tokens      │
└──────────────┬──────────────┘         └────────────────────────────┘
               │ HTTP: /embed, /search
               ▼
┌─────────────────────────────┐         ┌────────────────────────────┐
│ Flask embedding service     │────────▶│ ChromaDB                   │
│ all-MiniLM-L6-v2            │         │ persistent cosine vectors  │
└─────────────────────────────┘         └────────────────────────────┘
```

- **React Native / Expo** provides the single feed screen, semantic search, pull-to-refresh, infinite scroll, and local reaction UI.
- **Laravel** is the system of record. It authenticates users, validates requests, persists relational data, calls the embedding service, and ranks feed candidates.
- **MySQL** stores users, posts, interactions, and Sanctum personal access tokens.
- **Flask** produces normalized `all-MiniLM-L6-v2` embeddings and provides vector lookup endpoints.
- **ChromaDB** persists post vectors and returns the nearest embedding IDs. Laravel resolves those IDs back to relational posts before returning them to clients.

## Tech Stack

| Area | Technologies |
| --- | --- |
| Backend | Laravel 12, PHP, Laravel Sanctum |
| Database | MySQL (XAMPP) |
| Vector search | Flask, Sentence Transformers, `all-MiniLM-L6-v2`, ChromaDB |
| Frontend | React Native, Expo, Axios, TanStack React Query |
| Testing | Laravel Feature Tests, PHPUnit |

## Features

### Authentication

- `POST /api/login` validates credentials and returns a Sanctum personal access token.
- `POST /api/logout` revokes the current access token.
- All feed, search, post, and interaction routes require `Authorization: Bearer <token>`.

### Posts

- Creates text posts with an optional image URL.
- Calculates a bounded local authenticity score from content length and word count.
- Persists the post first, then calls the local embedding service and stores the returned `embedding_id`.
- Retains the post if the vector service is temporarily unavailable; the embedding ID remains `null` and the failure is logged.

### Feed

- Returns personalized results ranked in descending score order.
- Uses 20 records per page and returns `data`, `current_page`, and `last_page`.
- Ranks up to 200 recent posts authored by other users.

### Search

- Accepts natural-language search through `GET /api/search?q=...`.
- Uses ChromaDB semantic similarity rather than keyword matching.
- Resolves the top 10 embedding IDs into posts while preserving vector relevance order.

### Interactions

- Records `view`, `reaction`, and `reply` events.
- Interaction history contributes to relationship depth and the viewer interest profile used by ranking.

## Feed Ranking Algorithm

For each candidate post, the backend calculates:

```text
score =
  0.40 × relationship_depth
+ 0.30 × semantic_similarity
+ 0.20 × authenticity_score
+ 0.10 × recency_score
```

| Signal | Implementation |
| --- | --- |
| Relationship depth | The viewer's interactions with posts by the candidate author, normalized with a soft cap of 20 interactions. |
| Semantic similarity | The viewer's 10 most recently interacted post texts are queried against ChromaDB. Candidate posts in the returned interest cluster get a rank-decayed score. If embeddings are unavailable, the service uses a neutral fallback. |
| Authenticity | A local score derived from post length (60%) and word count (40%), bounded between 0 and 1. |
| Recency | Exponential decay using a 72-hour half-life; newer posts receive a higher score. |

The ranking implementation is in [backend/app/Services/FeedRankingService.php](backend/app/Services/FeedRankingService.php).

## Database Schema

### `users`

Stores identity and authentication data: `id`, `name`, unique `email`, hashed `password`, and timestamps.

### `posts`

Stores authored content: `user_id`, `content`, nullable `image_url`, nullable `embedding_id`, `authenticity_score`, and timestamps. `user_id` is a foreign key to `users`; `user_id` and `created_at` are indexed.

### `interactions`

Stores a user's event against a post: `user_id`, `post_id`, `type` (`view`, `reaction`, or `reply`), and timestamps. Both foreign keys cascade on delete, and `user_id`, `post_id`, and `type` are indexed.

```text
User 1 ───── * Post
User 1 ───── * Interaction
Post 1 ───── * Interaction
```

## API Endpoints

Protected endpoints require `Authorization: Bearer <sanctum-token>`.

| Method | Endpoint | Description |
| --- | --- | --- |
| `POST` | `/api/login` | Authenticate a user and return a Sanctum token. |
| `POST` | `/api/logout` | Revoke the token used by the request. |
| `POST` | `/api/posts` | Create a post and attach a vector embedding ID when available. |
| `GET` | `/api/feed?page=1` | Retrieve a ranked, 20-item paginated feed. |
| `GET` | `/api/search?q=travel%20stories` | Retrieve up to 10 semantically related posts. |
| `POST` | `/api/interactions` | Record a view, reaction, or reply event. |

### Login

```http
POST /api/login
Content-Type: application/json

{
  "email": "alice@example.com",
  "password": "password"
}
```

```json
{
  "token": "1|sanctum-token-value",
  "user": {
    "id": 1,
    "name": "Alice Johnson",
    "email": "alice@example.com"
  }
}
```

### Create a post

```http
POST /api/posts
Authorization: Bearer <sanctum-token>
Content-Type: application/json

{
  "content": "Trip to Goa",
  "image_url": "https://example.com/image.jpg"
}
```

```json
{
  "data": {
    "id": 1,
    "user_id": 1,
    "content": "Trip to Goa",
    "image_url": "https://example.com/image.jpg",
    "embedding_id": "a-vector-uuid-or-null",
    "authenticity_score": 0.17,
    "feed_score": null,
    "created_at": "2026-07-14T12:00:00+00:00",
    "updated_at": "2026-07-14T12:00:00+00:00",
    "user": { "id": 1, "name": "Alice Johnson" }
  }
}
```

### Get the feed

```http
GET /api/feed?page=1
Authorization: Bearer <sanctum-token>
```

```json
{
  "data": [
    {
      "id": 14,
      "user_id": 2,
      "content": "Funny travel story: missed my train in Prague...",
      "image_url": null,
      "embedding_id": "a-vector-uuid",
      "authenticity_score": 0.78,
      "feed_score": 0.691234,
      "created_at": "2026-07-14T11:30:00+00:00",
      "updated_at": "2026-07-14T11:30:00+00:00",
      "user": { "id": 2, "name": "Bob Smith" }
    }
  ],
  "current_page": 1,
  "last_page": 3
}
```

### Search and record an interaction

```http
GET /api/search?q=funny%20travel%20stories
Authorization: Bearer <sanctum-token>
```

```http
POST /api/interactions
Authorization: Bearer <sanctum-token>
Content-Type: application/json

{
  "post_id": 14,
  "type": "reaction"
}
```

```json
{
  "id": 201,
  "user_id": 1,
  "post_id": 14,
  "type": "reaction",
  "created_at": "2026-07-14T12:05:00+00:00"
}
```

## Setup Guide

### Prerequisites

- XAMPP with MySQL running
- PHP 8.2+ (the project currently declares PHP `^8.2`; PHP 8.3+ is recommended for the assessment target)
- Composer
- Python 3.10+
- Node.js and npm

### Database setup

1. Start **MySQL** from the XAMPP control panel.
2. Open phpMyAdmin at `http://localhost/phpmyadmin`.
3. Create a database named `guisedup`.

### Laravel API setup

```powershell
cd backend
composer install
copy .env.example .env
C:\xampp\php\php.exe artisan key:generate
```

Review `.env` and ensure these XAMPP defaults are correct:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=guisedup
DB_USERNAME=root
DB_PASSWORD=

EMBEDDING_SERVICE_URL=http://127.0.0.1:5000
```

Create schema and seed the assessment data:

```powershell
C:\xampp\php\php.exe artisan migrate:fresh --seed
```

The seeder creates two known accounts, eight additional users, exactly 50 posts, and 200 interactions:

| Email | Password |
| --- | --- |
| `alice@example.com` | `password` |
| `bob@example.com` | `password` |

Start the API:

```powershell
C:\xampp\php\php.exe artisan serve
```

The API listens at `http://127.0.0.1:8000` by default.

### Python embedding service setup

Open a second terminal from the repository root:

```powershell
cd python-service
py -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
python app.py
```

The service listens at `http://127.0.0.1:5000`. On its first start, Sentence Transformers downloads `all-MiniLM-L6-v2`; ChromaDB persists vectors under `python-service/chroma-data`.

Useful local health check:

```text
GET http://127.0.0.1:5000/health
```

### React Native / Expo setup

Open a third terminal from the repository root:

```powershell
cd mobile
copy .env.example .env
npm install
npx expo start
```

Set `EXPO_PUBLIC_API_URL` in `mobile/.env` to an API address reachable from the chosen device:

- Android emulator: `http://10.0.2.2:8000/api`
- iOS simulator: `http://127.0.0.1:8000/api`
- Physical device: `http://<your-LAN-IP>:8000/api`

The included mobile environment template also contains the seeded demo credentials used by the single assessment screen.

## Running Tests

Run the Laravel feature suite from `backend/`:

```powershell
C:\xampp\php\php.exe artisan test
```

Expected result:

```text
Tests: 8 passed (27 assertions)
```

| Test area | What it validates |
| --- | --- |
| Post API | Authenticated post creation, persisted embedding ID, and unauthenticated rejection. |
| Feed API | Authenticated ranked feed response and flat post collection contract. |
| Interaction API | Valid interaction persistence and invalid type rejection. |
| Search API | Semantic embedding-ID order is preserved and `q` is required. |
| Seeder | Exactly 10 users, 50 posts, and 200 interactions are created. |

## SQL Challenge

The optimized SQL solutions are in [sql/queries.sql](sql/queries.sql):

1. Top 10 active users in the last seven days.
2. Posts from authors a given user has interacted with most in the last 30 days.
3. Posts viewed more than 100 times with zero reactions.
4. Users who created more than 20 posts in the last 24 hours.

## AI Tool Usage

**OpenAI Codex** was used for:

- Project scaffolding
- Architecture review
- Documentation drafting
- Test support and validation review

All generated code was reviewed, integrated, and validated manually.

## Project Structure

```text
guisedUp/
├── backend/
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   ├── Requests/
│   │   │   └── Resources/
│   │   ├── Models/
│   │   ├── Repositories/
│   │   └── Services/
│   ├── config/
│   ├── database/
│   │   ├── factories/
│   │   ├── migrations/
│   │   └── seeders/
│   ├── routes/api.php
│   └── tests/Feature/
├── docs/
│   └── TSD.md
├── mobile/
│   ├── src/api/
│   ├── src/screens/FeedScreen.js
│   ├── App.js
│   └── package.json
├── python-service/
│   ├── app.py
│   └── requirements.txt
├── sql/
│   └── queries.sql
└── README.md
```

## Submission Notes

- The technical design document is available at [docs/TSD.md](docs/TSD.md).
- SQL challenge answers are available at [sql/queries.sql](sql/queries.sql).
- Laravel feature tests pass with 8 tests and 27 assertions.
- Local vector embedding and semantic search are implemented through Flask, Sentence Transformers, and ChromaDB.
- The Expo client implements the required feed, search, refresh, infinite-scroll, loading, error, empty, and reaction states.

## Future Improvements

1. Move embedding generation to queued jobs with retries and a durable outbox.
2. Persist precomputed feed candidates and scores for faster high-volume reads.
3. Introduce an explicit social graph (follow, friend, muted) instead of using interactions as the relationship proxy.
4. Use cursor pagination and a candidate-generation pipeline for larger feeds.
5. Add a production login and token-storage flow using SecureStore rather than assessment demo credentials.
6. Add request throttling, strict CORS allowlists, audit logs, and role-aware authorization policies.
7. Add vector metadata filtering and asynchronous vector reconciliation for failed embedding jobs.
8. Add image upload/storage, media validation, and content moderation.
9. Instrument API latency, vector-service health, ranking outcomes, and failed embedding metrics.
10. Evaluate a learned ranking model with offline relevance metrics and controlled experimentation.
