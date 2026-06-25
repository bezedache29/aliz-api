<?php

namespace App\Services;

use App\Models\Recipe;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class LlmService
{
    public function suggestRecipe(string $date, string $mealType, Collection $recipes, ?string $prompt): array
    {
        $provider = config('llm.provider', 'anthropic');
        $system = $this->buildSystemPrompt();
        $user = $this->buildUserMessage($date, $mealType, $recipes, $prompt);

        $raw = match ($provider) {
            'openai' => $this->callOpenAI($system, $user),
            default  => $this->callAnthropic($system, $user),
        };

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Réponse LLM invalide : ' . $raw);
        }

        return $decoded;
    }

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
Tu es un assistant nutritionnel pour l'app Aliz. Tu suggères des recettes adaptées au type de repas.

Réponds UNIQUEMENT en JSON valide, sans markdown, sans explication.

Si une recette existante convient, retourne :
{"type":"existing","recipe_id":"<uuid>"}

Sinon, crée une suggestion et retourne :
{"type":"new","name":"<nom>","description":"<description courte>","kcal":<nombre>,"proteines":<nombre>,"glucides":<nombre>,"lipides":<nombre>,"prep_time":<minutes>,"cook_time":<minutes>}
PROMPT;
    }

    private function buildUserMessage(string $date, string $mealType, Collection $recipes, ?string $prompt): string
    {
        $recipesJson = $recipes->map(fn(Recipe $r) => [
            'id'       => $r->id,
            'name'     => $r->name,
            'meal'     => $r->meal,
            'category' => $r->category,
        ])->values()->toJson(JSON_UNESCAPED_UNICODE);

        $contextLine = $prompt ? "Contexte : {$prompt}\n" : '';

        return <<<MSG
Date : {$date}
Type de repas : {$mealType}
{$contextLine}
Recettes disponibles :
{$recipesJson}

Suggère la recette la plus adaptée.
MSG;
    }

    private function callAnthropic(string $system, string $user): string
    {
        $response = Http::withHeaders([
            'x-api-key'         => config('llm.anthropic.api_key'),
            'anthropic-version' => '2023-06-01',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model'      => config('llm.anthropic.model'),
            'max_tokens' => 512,
            'system'     => $system,
            'messages'   => [['role' => 'user', 'content' => $user]],
        ]);

        $response->throw();

        return $response->json('content.0.text') ?? throw new \RuntimeException('Anthropic : contenu vide');
    }

    private function callOpenAI(string $system, string $user): string
    {
        $response = Http::withToken(config('llm.openai.api_key'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'      => config('llm.openai.model'),
                'max_tokens' => 512,
                'messages'   => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
            ]);

        $response->throw();

        return $response->json('choices.0.message.content') ?? throw new \RuntimeException('OpenAI : contenu vide');
    }
}
