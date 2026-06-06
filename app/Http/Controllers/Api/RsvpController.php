<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendRsvpConfirmationEmail;
use App\Models\Event;
use App\Models\Rsvp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RsvpController extends Controller
{
    /**
     * Listar participantes de um evento.
     */
    public function index(Event $event): JsonResponse
    {
        $attendees = $event->attendees()->paginate(20);

        return response()->json($attendees);
    }

    /**
     * Inscrever-se (RSVP) em um evento.
     */
    public function store(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();

        // 1. Verificar se o usuário já se inscreveu no evento
        $exists = Rsvp::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Você já está inscrito neste evento.',
            ], 422);
        }

        // 2. Verificar se o evento já atingiu a capacidade máxima
        $attendeesCount = $event->attendees()->count();
        if ($attendeesCount >= $event->capacity) {
            return response()->json([
                'message' => 'Desculpe, este evento já atingiu a capacidade máxima de participantes.',
            ], 422);
        }

        // 3. Criar a inscrição RSVP
        $rsvp = Rsvp::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
        ]);

        // 4. Despachar o Job de envio de e-mail de confirmação para a fila do Redis
        SendRsvpConfirmationEmail::dispatch($user, $event);

        return response()->json([
            'message' => 'Inscrição confirmada com sucesso! Um e-mail de confirmação foi enviado.',
            'rsvp' => $rsvp,
        ], 201);
    }

    /**
     * Cancelar inscrição (RSVP) em um evento.
     */
    public function destroy(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();

        $rsvp = Rsvp::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $rsvp) {
            return response()->json([
                'message' => 'Você não possui inscrição confirmada para este evento.',
            ], 422);
        }

        $rsvp->delete();

        return response()->json([
            'message' => 'Inscrição cancelada com sucesso.',
        ]);
    }
}
