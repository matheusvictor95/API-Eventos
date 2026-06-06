<?php

use App\Jobs\SendRsvpConfirmationEmail;
use App\Models\Event;
use App\Models\Rsvp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('usuário autenticado pode se inscrever em um evento e despacha job de e-mail', function () {
    Queue::fake();

    $user = User::factory()->create();
    $event = Event::factory()->create(['capacity' => 10]);

    $response = $this->actingAs($user, 'sanctum')->postJson("/api/events/{$event->id}/rsvp");

    $response->assertStatus(201)
        ->assertJsonStructure(['message', 'rsvp']);

    $this->assertDatabaseHas('rsvps', [
        'event_id' => $event->id,
        'user_id' => $user->id,
    ]);

    // Garantir que o job foi para a fila do Redis (no fake do laravel)
    Queue::assertPushed(SendRsvpConfirmationEmail::class, function ($job) use ($user, $event) {
        return $job->user->id === $user->id && $job->event->id === $event->id;
    });
});

test('usuário não pode se inscrever duas vezes no mesmo evento', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();

    // Primeira inscrição
    Rsvp::create([
        'event_id' => $event->id,
        'user_id' => $user->id,
    ]);

    // Tentativa de duplicada
    $response = $this->actingAs($user, 'sanctum')->postJson("/api/events/{$event->id}/rsvp");

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Você já está inscrito neste evento.');
});

test('usuário não pode se inscrever se a capacidade estiver esgotada', function () {
    $event = Event::factory()->create(['capacity' => 2]);

    // Preencher o evento
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    Rsvp::create(['event_id' => $event->id, 'user_id' => $user1->id]);
    Rsvp::create(['event_id' => $event->id, 'user_id' => $user2->id]);

    // Terceiro usuário tenta se inscrever
    $user3 = User::factory()->create();
    $response = $this->actingAs($user3, 'sanctum')->postJson("/api/events/{$event->id}/rsvp");

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Desculpe, este evento já atingiu a capacidade máxima de participantes.');
});

test('usuário pode cancelar sua inscrição', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();

    Rsvp::create([
        'event_id' => $event->id,
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user, 'sanctum')->deleteJson("/api/events/{$event->id}/rsvp");

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Inscrição cancelada com sucesso.');

    $this->assertDatabaseMissing('rsvps', [
        'event_id' => $event->id,
        'user_id' => $user->id,
    ]);
});

test('qualquer pessoa pode ver a lista de participantes de um evento', function () {
    $event = Event::factory()->create();
    $users = User::factory()->count(3)->create();

    foreach ($users as $user) {
        Rsvp::create(['event_id' => $event->id, 'user_id' => $user->id]);
    }

    $response = $this->getJson("/api/events/{$event->id}/attendees");

    $response->assertStatus(200)
        ->assertJsonStructure(['data', 'current_page'])
        ->assertJsonCount(3, 'data');
});
