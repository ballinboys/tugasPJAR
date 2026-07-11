# Network Programming Lab — Frontend

Client-server application in PHP (Zend Framework 1): **two separate
processes communicating over HTTP**. This repo is the **frontend**; the
backend API lives in a separate repo, [networking-pjar-be](https://github.com/Bismvrckz/networking-pjar-be).

```
Browser ──HTTP──> Frontend (this repo)  ──HTTP + Basic auth + HMAC signature──> Backend API (networking-pjar-be)
  :8081           session, UI, no database        :8082   MySQL, file storage, Gmail SMTP
```

- **Frontend** (this repo, runs at `http://localhost:8081`) — serves the
  single page (`/`) with three columns (auth, files, media). It has no
  database: every operation is an HTTP request to the backend. Binary
  responses (download, media stream) are relayed chunk by chunk, including
  `206 Partial Content` + `Content-Range`, so video seeking works across
  both processes.
- **Backend** (`networking-pjar-be`, runs at `http://localhost:8082`) —
  JSON/binary API owning the `networking_lab` MySQL database, `data/` file
  storage, and password-reset email via Gmail SMTP.

## API security (basic, per assignment)

Every frontend request carries:

| Header          | Value                                                  |
| --------------- | ------------------------------------------------------ | ----------- | ------------------------ |
| `Authorization` | `Basic base64(API_KEY:API_SECRET)` (`X-Auth` fallback) |
| `X-Timestamp`   | unix time, max 5 minutes skew                          |
| `X-Signature`   | `HMAC-SHA256("METHOD                                   | REQUEST_URI | TIMESTAMP", API_SECRET)` |

The backend's `ApiAuth` plugin rejects anything else with `401`.

## Endpoints (served by the backend repo)

| Method | Path                           | Purpose                                  |
| ------ | ------------------------------ | ---------------------------------------- |
| POST   | `/auth/register`               | create user (bcrypt via `password_hash`) |
| POST   | `/auth/login`                  | verify credentials, return identity      |
| POST   | `/auth/forgot`                 | create reset token, email link           |
| POST   | `/auth/checkreset`             | validate a reset token                   |
| POST   | `/auth/reset`                  | set new password (single-use token)      |
| POST   | `/auth/mailtest`               | SMTP smoke test                          |
| POST   | `/file/upload` `/media/upload` | multipart upload (max 50MB)              |
| GET    | `/file/list?user_id&category`  | list a user's rows                       |
| GET    | `/file/download/id/N?user_id`  | download by id                           |
| GET    | `/media/stream/id/N?user_id`   | Range-aware stream (`206`/`416`)         |

## Setup

Both repos run as separate processes on the same machine.

1. **Backend** (`networking-pjar-be`):
   - Database: `mysql -u root -p < database/schema.sql`
   - Edit `.env`: MySQL credentials + Gmail App Password (the Gmail account
     needs 2FA), plus `API_KEY`/`API_SECRET`.
   - Serve at `http://localhost:8082`.
2. **Frontend** (this repo):
   - Edit `.env`: `API_BASE_URL` pointing at the backend
     (`http://localhost:8082`) and the **same** `API_KEY`/`API_SECRET` pair
     as the backend.
   - Serve at `http://localhost:8081`.

## Verifying the streaming (the networking core)

```
curl -H "Range: bytes=0-99" -i http://localhost:8081/media/stream/id/1
```

Expect `206 Partial Content`, `Content-Range: bytes 0-99/<size>`,
`Content-Length: 100` — served by the backend and relayed by the frontend.
Without a `Range` header the same URL returns `200` with
`Accept-Ranges: bytes`. (Streams are session-gated: pass your browser's
session cookie to curl.) Calling the backend directly on `:8082` without
the auth + signature headers returns `401`.

## Public demo (Cloudflare Tunnel)

Only the **frontend** needs to be exposed — the browser talks solely to it,
and the frontend calls the backend server-side over `localhost`, so the
backend stays private.

```
cloudflared tunnel --url http://localhost:8081
```

This prints a public `https://…trycloudflare.com` URL. The reset-link
scheme follows `X-Forwarded-Proto`, so emailed links stay `https` through
the tunnel. For a stable URL, use a named tunnel with your own domain
(Cloudflare Zero Trust → Networks → Tunnels) and map a public hostname to
`http://localhost:8081`.
