<?php

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('qualquer pessoa pode listar eventos paginados', function () {
    Event::factory()->count(15)->create();

    $response = $this->getJson('/api/events');

    $response->assertStatus(200)
        ->assertJsonStructure(['data', 'current_page', 'last_page'])
        ->assertJsonCount(10, 'data'); // paginação padrão de 10
});

test('qualquer pessoa pode visualizar detalhes de um evento', function () {
    $event = Event::factory()->create();

    $response = $this->getJson("/api/events/{$event->id}");

    $response->assertStatus(200)
        ->assertJsonPath('event.title', $event->title);
});

test('usuário autenticado pode criar um evento', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/events', [
        'title' => 'Laravel Meetup',
        'description' => 'Encontro da comunidade de Laravel',
        'location' => 'São Paulo, SP',
        'starts_at' => now()->addDays(5)->toDateTimeString(),
        'ends_at' => now()->addDays(5)->addHours(4)->toDateTimeString(),
        'capacity' => 100,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('event.title', 'Laravel Meetup');

    $this->assertDatabaseHas('events', [
        'title' => 'Laravel Meetup',
        'user_id' => $user->id,
    ]);
});

test('não é possível criar evento com dados inválidos', function () {
    $user = User::factory()->create();

    // Data de início no passado
    $response = $this->actingAs($user, 'sanctum')->postJson('/api/events', [
        'title' => 'Laravel Meetup',
        'location' => 'São Paulo, SP',
        'starts_at' => now()->subDay()->toDateTimeString(),
        'ends_at' => now()->addDay()->toDateTimeString(),
        'capacity' => 100,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['starts_at']);
});

test('apenas o criador pode atualizar o evento', function () {
    $creator = User::factory()->create();
    $otherUser = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $creator->id]);

    // Tentativa por outro usuário
    $response = $this->actingAs($otherUser, 'sanctum')->putJson("/api/events/{$event->id}", [
        'title' => 'Novo Título Malicioso',
    ]);
    $response->assertStatus(403);

    // Tentativa pelo criador
    $response = $this->actingAs($creator, 'sanctum')->putJson("/api/events/{$event->id}", [
        'title' => 'Título Atualizado',
    ]);
    $response->assertStatus(200);

    $this->assertDatabaseHas('events', [
        'id' => $event->id,
        'title' => 'Título Atualizado',
    ]);
});

test('apenas o criador pode deletar o evento', function () {
    $creator = User::factory()->create();
    $otherUser = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $creator->id]);

    // Tentativa por outro usuário
    $response = $this->actingAs($otherUser, 'sanctum')->deleteJson("/api/events/{$event->id}");
    $response->assertStatus(403);

    // Tentativa pelo criador
    $response = $this->actingAs($creator, 'sanctum')->deleteJson("/api/events/{$event->id}");
    $response->assertStatus(200);

    $this->assertDatabaseMissing('events', [
        'id' => $event->id,
    ]);
});
