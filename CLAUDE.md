# Aliz API — Instructions Claude

## Workflow commit obligatoire

Quand l'utilisateur demande de commiter (mots : "commit", "commite", "commiter"), toujours suivre ces étapes dans l'ordre :

1. **Commit** — créer le commit git (le hook pre-commit lance Pest automatiquement)
2. **Code review** — lancer `/code-review medium` sur le diff committé — **sauf** si le message commence par `fix:` en réponse à une code review (inutile de re-reviewer les corrections)
3. **Push** — `git push origin dev` si les tests passent et la review ne bloque pas

Ne jamais pusher sans avoir fait la code review, sauf exception ci-dessus.
Ne jamais pusher sur `main` directement — toujours sur `dev`.

---

## Stack

- Laravel 13 / PHP 8.3+
- MySQL 8.4 (prod), SQLite possible en dev
- Laravel Sanctum (tokens mobiles stateless)
- Laravel Socialite + provider Strava (auth future)
- Pest pour tous les tests — jamais PHPUnit directement
- API JSON pure, pas de Blade

## Conventions

- **UUID** comme primary key sur toutes les tables (`$table->uuid('id')->primary()`)
- **snake_case** dans la DB et les réponses JSON (comportement Laravel par défaut)
- **Pas de commentaires** dans le code sauf si le "pourquoi" est non-évident
- **Form Requests** pour toute validation (jamais `$request->validate()` dans le controller)

## Auth

- **Dev** : middleware `StaticTokenAuth` — vérifie `Authorization: Bearer {STATIC_API_TOKEN}`
- **Futur** : Strava OAuth → `AuthController` (squelette présent, TODO dedans)

## Format de réponse API

```json
// Liste standard
{ "data": [...] }

// Objet standard
{ "data": {...} }

// Erreur
{ "message": "...", "errors": { "field": ["..."] } }
```

**Exceptions (format imposé par l'app React Native) :**
- `GET /api/recipes` → `{ "recipes": [...] }`
- `GET /api/planning/week` → `{ "meals": [...] }`
- `POST /api/planning/.../regenerate` → `{ "recipe": {...} }`

## Structure des tests Pest

```php
// Feature test type
it('description du comportement', function () {
    $response = $this->withToken(config('app.static_api_token'))
        ->getJson('/api/recipes');

    $response->assertOk()
        ->assertJsonStructure(['recipes' => [...]]);
});
```
