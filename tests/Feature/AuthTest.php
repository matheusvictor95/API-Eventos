<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('usuário pode se registrar com sucesso', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Matheus Teste',
        'email' => 'matheus@example.com',
        'password' => 'senha12345',
    ]);

    $response->assertStatus(210)
        ->assertJsonStructure([
            'message',
            'user' => ['id', 'name', 'email'],
            'access_token',
            'token_type',
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'matheus@example.com',
    ]);
});

test('usuário pode fazer login com credenciais válidas', function () {
    $user = User::factory()->create([
        'email' => 'login@example.com',
        'password' => bcrypt('senha123'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'login@example.com',
        'password' => 'senha123',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'user',
            'access_token',
            'token_type',
        ]);
});

test('login falha com credenciais inválidas', function () {
    $user = User::factory()->create([
        'email' => 'wrong@example.com',
        'password' => bcrypt('senha123'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'wrong@example.com',
        'password' => 'senha_errada',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('usuário autenticado pode obter seus dados (/me)', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/me');

    $response->assertStatus(200)
        ->assertJsonPath('user.email', $user->email);
});

test('usuário autenticado pode fazer logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/logout');

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Logout realizado com sucesso. Token revogado.',
        ]);
});
