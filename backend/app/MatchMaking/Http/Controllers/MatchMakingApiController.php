<?php

declare(strict_types=1);

namespace App\MatchMaking\Http\Controllers;

use App\MatchMaking\Http\Requests\CancelSearchRequest;
use App\MatchMaking\Http\Requests\StartSearchRequest;
use App\MatchMaking\Http\Requests\TicketActionRequest;
use App\MatchMaking\MatchMakingManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MatchMakingApiController extends Controller
{
    public function startSearch(StartSearchRequest $request, MatchMakingManager $mm): JsonResponse
    {
        $user = $request->user();

        $result = $mm->startSearch(
            user: $user,
            mode: $request->mode(),
            sessionId: $request->sessionId(),
        );

        return response()->json($result);
    }

    public function cancelSearch(CancelSearchRequest $request, MatchMakingManager $mm): JsonResponse
    {
        $user = $request->user();

        $mm->cancelSearch(
            user: $user,
            sessionId: $request->sessionId(),
        );

        return response()->json(['ok' => true]);
    }

    public function getState(Request $request, MatchMakingManager $mm): JsonResponse
    {
        $user = $request->user();

        $state = $mm->getState($user);

        return response()->json($state);
    }

    public function acceptTicket(TicketActionRequest $request, MatchMakingManager $mm, string $ticketId): JsonResponse
    {
        $user = $request->user();

        $mm->acceptTicket(
            user: $user,
            ticketId: $ticketId,
            sessionId: $request->sessionId(),
        );

        return response()->json(['ok' => true]);
    }

    public function declineTicket(TicketActionRequest $request, MatchMakingManager $mm, string $ticketId): JsonResponse
    {
        $user = $request->user();

        $mm->declineTicket(
            user: $user,
            ticketId: $ticketId,
            sessionId: $request->sessionId(),
        );

        return response()->json(['ok' => true]);
    }
}
