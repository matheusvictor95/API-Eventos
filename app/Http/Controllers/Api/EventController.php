<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EventController extends Controller
{
    /**
     * Listar todos os eventos (com paginação e filtros).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Event::with('user')->withCount('attendees');

        // Filtro por termo de busca (título ou descrição)
        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filtro para listar apenas eventos futuros (padrão ou sob demanda)
        if ($request->boolean('upcoming', false)) {
            $query->where('starts_at', '>=', now());
        }

        // Filtro por local
        if ($request->has('location')) {
            $query->where('location', 'like', "%{$request->query('location')}%");
        }

        $events = $query->latest()->paginate(10);

        return response()->json($events);
    }

    /**
     * Criar um novo evento.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'capacity' => ['required', 'integer', 'min:1'],
        ]);

        $event = $request->user()->events()->create($validated);

        return response()->json([
            'message' => 'Evento criado com sucesso.',
            'event' => $event->load('user'),
        ], 201);
    }

    /**
     * Exibir os detalhes de um evento.
     */
    public function show(Event $event): JsonResponse
    {
        return response()->json([
            'event' => $event->load(['user'])->loadCount('attendees'),
        ]);
    }

    /**
     * Atualizar um evento.
     */
    public function update(Request $request, Event $event): JsonResponse
    {
        Gate::authorize('update', $event);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['sometimes', 'required', 'string', 'max:255'],
            'starts_at' => ['sometimes', 'required', 'date', 'after:now'],
            'ends_at' => ['sometimes', 'required', 'date', 'after:starts_at'],
            'capacity' => ['sometimes', 'required', 'integer', 'min:1'],
        ]);

        $event->update($validated);

        return response()->json([
            'message' => 'Evento atualizado com sucesso.',
            'event' => $event->load('user'),
        ]);
    }

    /**
     * Excluir um evento.
     */
    public function destroy(Event $event): JsonResponse
    {
        Gate::authorize('delete', $event);

        $event->delete();

        return response()->json([
            'message' => 'Evento excluído com sucesso.',
        ]);
    }
}
