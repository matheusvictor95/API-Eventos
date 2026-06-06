# Olá, {{ $user->name }}!

Sua presença foi confirmada com sucesso no evento **{{ $event->title }}**.

**Detalhes do Evento:**
- **Local:** {{ $event->location }}
- **Início:** {{ $event->starts_at->format('d/m/Y H:i') }}
- **Término:** {{ $event->ends_at->format('d/m/Y H:i') }}

@if($event->description)
## Descrição:
{{ $event->description }}
@endif

Seus detalhes de inscrição estão salvos. Se você precisar cancelar, poderá fazê-lo a qualquer momento pela nossa API.

Estamos ansiosos para a sua participação!

Obrigado,<br>
Equipe **{{ config('app.name') }}**
