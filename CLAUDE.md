# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

PirateGram is a competitive multiplayer game application with a Laravel backend and React frontend, featuring real-time matchmaking, parties, and game management.

## Development Commands

### Backend (run from `backend/` or via Docker workspace)
```bash
php artisan test                              # Run all tests
php artisan test --testsuite=Unit             # Unit tests only
php artisan test tests/Feature/MatchMaking    # Specific directory
./vendor/bin/pint                             # Lint and auto-fix PHP code
php artisan migrate                           # Run database migrations
php artisan app:match-making:daemon           # Start matchmaker daemon
```

### Frontend (run from `frontend/`)
```bash
npm run dev          # Start dev server (Vite + SSR)
npm run build        # Production build
```

### Docker Infrastructure (run from `infra/dev/`)
```bash
docker-compose up -d                    # Start all services
docker-compose exec workspace bash      # Enter PHP container
docker-compose exec frontend sh         # Enter Node container
```

## Architecture

### Directory Structure
- `backend/` - Laravel 12.x PHP application (PHP 8.5)
- `frontend/` - React 19 + Vike SSR application (Node 25)
- `infra/dev/` - Docker Compose development environment

### Backend Organization (`backend/app/`)
Code is organized by feature domain:
- `Auth/` - Authentication (Sanctum-based login/register)
- `Game/` - Game logic, board states, cell behaviors
- `MatchMaking/` - Real-time matchmaking system (primary feature)
- `User/` - User models and resources

### Matchmaking System (Core Feature)
Location: `backend/app/MatchMaking/`

Key components:
- `MatchMakingManager` - Orchestrates the entire matchmaking flow
- `GroupAssembler` - Pairs compatible groups into teams based on MMR
- `PartyManager` - Handles team/party management
- `TicketManager` - Manages ready-check tickets

Queue structure uses Redis sorted sets by game mode (1v1, 2v2, ffa4). Status workflow: Searching → Matched → Ticket Pending → Confirmed/Declined/Timeout.

### Frontend Organization
- `pages/` - Vike file-based routing (similar to Next.js)
- `components/` - React components (Radix UI for primitives)
- `store/` - Zustand state stores (auth, match, party, friends)
- `api/` - API client modules with XSRF handling

### Real-time Broadcasting
- Redis broadcast driver with Ably PHP SDK
- Laravel Echo on frontend for subscriptions
- Private channels per user: `user.{hashedId}`
- Events: `UserSearchUpdated`, `SearchUpdated`, `TicketUpdated`, `GameStateUpdated`

## Key Patterns
- Always use enums on backend and constants on frontend instead of magic strings
 
### Backend
- All PHP files use `declare(strict_types=1)`
- User IDs are obfuscated using Hashids (`HashedIdTrait`)
- Value objects as enums: `GameMode`, `SearchStatus`, `TicketStatus`
- Redis handles session, cache, broadcasting, and queue

### Frontend
- Path aliases: `@/*` resolves to project root
- Vike for SSR (not Next.js - check Vike docs for patterns)
- Zustand stores for global state management

## Services (Docker)
- `nginx` - Reverse proxy (ports 5155/5156)
- `php-fpm` - PHP-FPM 8.4
- `frontend` - Node.js SSR server (port 3000)
- `workspace` - PHP CLI container for artisan commands
- `percona` - MySQL database (port 33110)
- `redis` - Cache, sessions, queues
- `horizon` - Job queue dashboard
- `matchmaker` - Dedicated matchmaking daemon

## API Routes

Authentication: `POST /api/auth/{register,login,logout}`, `GET /api/auth/me`

Matchmaking (authenticated):
- `POST /api/matchmaking/solo/start` - Start individual search
- `POST /api/matchmaking/solo/cancel` - Cancel search
- `POST /api/matchmaking/tickets/{id}/accept` - Accept ready check
- `POST /api/matchmaking/tickets/{id}/decline` - Decline ready check
