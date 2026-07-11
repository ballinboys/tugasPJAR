# Network Programming Lab

Client-server application in PHP (Zend Framework 1): **two separate
processes communicating over HTTP**.

```
Browser ──HTTP──> Frontend (this app, /)  ──HTTP + Basic auth + HMAC signature──> Backend API (backend/)
                  session, UI, no database          MySQL, file storage, Gmail SMTP
```

- **Frontend** (repo root) — serves the single page (`/`) with three
  columns (auth, files, media). It has no database: every operation is an
  HTTP request to the backend. Binary responses (download, media stream)
  are relayed chunk by chunk, including `206 Partial Content` +
  `Content-Range`, so video seeking works across both processes.
- **Backend** (`backend/`) — JSON/binary API owning the `networking_lab`
  MySQL database, `backend/data/` file storage, and password-reset email
  via Gmail SMTP.

## API security (basic, per assignment)

Every frontend request carries:

| Header          | Value                                                   |
| --------------- | ------------------------------------------------------- |
| `Authorization` | `Basic base64(API_KEY:API_SECRET)` (`X-Auth` fallback)  |
| `X-Timestamp`   | unix time, max 5 minutes skew                           |
| `X-Signature`   | `HMAC-SHA256("METHOD|REQUEST_URI|TIMESTAMP", API_SECRET)` |

The backend's `ApiAuth` plugin rejects anything else with `401`.

## Endpoints (backend)

| Method | Path                        | Purpose                                  |
| ------ | --------------------------- | ---------------------------------------- |
| POST   | `/auth/register`            | create user (bcrypt via `password_hash`) |
| POST   | `/auth/login`               | verify credentials, return identity      |
| POST   | `/auth/forgot`              | create reset token, email link           |
| POST   | `/auth/checkreset`          | validate a reset token                   |
| POST   | `/auth/reset`               | set new password (single-use token)      |
| POST   | `/auth/mailtest`            | SMTP smoke test                          |
| POST   | `/file/upload` `/media/upload` | multipart upload (max 50MB)           |
| GET    | `/file/list?user_id&category`  | list a user's rows                    |
| GET    | `/file/download/id/N?user_id`  | download by id                        |
| GET    | `/media/stream/id/N?user_id`   | Range-aware stream (`206`/`416`)      |

## Setup

1. Database: `mysql -u root -p < backend/database/schema.sql`
2. Backend env: edit `backend/.env` (MySQL credentials + Gmail App
   Password; the Gmail account needs 2FA).
3. Frontend env: `.env` needs `API_BASE_URL` pointing at the backend and
   the same `API_KEY`/`API_SECRET` pair as `backend/.env`.
4. Serve both apps, e.g. with Herd:
   - repo root: `herd link networking-pjar` → http://networking-pjar.test
   - `backend/`: `herd link networking-pjar-api` → http://networking-pjar-api.test

## Verifying the streaming (the networking core)

```
curl -H "Range: bytes=0-99" -i http://networking-pjar.test/media/stream/id/1
```

Expect `206 Partial Content`, `Content-Range: bytes 0-99/<size>`,
`Content-Length: 100` — served by the backend and relayed by the frontend.
Without a `Range` header the same URL returns `200` with
`Accept-Ranges: bytes`. (Streams are session-gated: pass your browser's
session cookie to curl.) Calling the backend directly without the auth +
signature headers returns `401`.
