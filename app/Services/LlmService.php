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
        if (!is_array($decoded) || array_is_list($decoded) || !in_array($decoded['type'] ?? null, ['existing', 'new'], true)) {
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

    public function generateFullRecipe(
        string $userPrompt,
        array $expiringStock,
        array $otherStock,
        array $likedFoods,
        array $dislikedFoods,
        ?array $profileContext,
    ): array {
        $provider = config('llm.provider', 'anthropic');
        $system   = $this->buildGenerateSystemPrompt();
        $user     = $this->buildGenerateUserMessage($userPrompt, $expiringStock, $otherStock, $likedFoods, $dislikedFoods, $profileContext);

        $raw = match ($provider) {
            'openai' => $this->callOpenAI($system, $user, 2048),
            default  => $this->callAnthropic($system, $user, 2048),
        };

        $decoded = json_decode($raw, true);
        if (
            !is_array($decoded)
            || empty($decoded['name'])
            || empty($decoded['steps'])
            || empty($decoded['ingredients'])
        ) {
            throw new \RuntimeException('Réponse LLM invalide : ' . $raw);
        }

        return $decoded;
    }

    private function buildGenerateSystemPrompt(): string
    {
        return <<<'PROMPT'
Tu es un chef cuisinier et nutritionniste expert. Tu génères des recettes complètes et réalistes.

Réponds UNIQUEMENT en JSON valide, sans markdown, sans explication, sans balise de code.

Règles de priorité pour les ingrédients :
1. PRIORITÉ ABSOLUE aux aliments dont la DLC arrive bientôt (liste "expiring_soon") — intègre-les impérativement si la recette le permet.
2. Utilise en priorité les aliments disponibles en stock (liste "other_stock").
3. Tu peux suggérer des ingrédients hors stock si nécessaire pour compléter la recette.
4. N'utilise JAMAIS les aliments de la liste "disliked_foods".
5. Favorise les aliments de la liste "liked_foods".

Le JSON doit suivre exactement ce schéma :
{
  "name": "string",
  "description": "string (1-2 phrases)",
  "category": "Petit-déjeuner | Brunch | Entrée | Plat principal | Soupe | Dessert | Encas | Apéritif | Boulangerie | Sauce & condiments",
  "meal": "Petit-déjeuner | Déjeuner | Collation | Dîner | null",
  "cooking_method": "Four | Poêle | Cookeo | Barbecue | Froid | null",
  "seasons": [],
  "prep_time": integer,
  "cook_time": integer,
  "kcal_estimated": float,
  "proteines_estimated": float,
  "glucides_estimated": float,
  "lipides_estimated": float,
  "steps": ["string", ...],
  "ingredients": [
    {
      "food_name": "string",
      "quantity_g": float,
      "per100g_kcal": float,
      "per100g_proteines": float,
      "per100g_glucides": float,
      "per100g_lipides": float,
      "from_stock": true or false
    }
  ]
}

Les macros estimées doivent être cohérentes avec les ingrédients et la quantité totale de la recette.
PROMPT;
    }

    private function buildGenerateUserMessage(
        string $userPrompt,
        array $expiringStock,
        array $otherStock,
        array $likedFoods,
        array $dislikedFoods,
        ?array $profileContext,
    ): string {
        $parts = ["Demande : {$userPrompt}"];

        if ($profileContext) {
            $parts[] = "Objectif nutritionnel : {$profileContext['kcal']} kcal/jour, {$profileContext['proteines']}g protéines/jour";
        }

        if (!empty($expiringStock)) {
            $list    = collect($expiringStock)->map(fn ($i) => "{$i['food_name']} ({$i['quantity_g']}g, DLC : {$i['expiry_date']})")->join(', ');
            $parts[] = "⚠️ À utiliser EN PRIORITÉ (DLC proche) — expiring_soon : {$list}";
        }

        if (!empty($otherStock)) {
            $list    = collect($otherStock)->map(fn ($i) => "{$i['food_name']} ({$i['quantity_g']}g)")->join(', ');
            $parts[] = "Stock disponible — other_stock : {$list}";
        }

        if (!empty($likedFoods)) {
            $parts[] = "Aliments aimés — liked_foods : " . implode(', ', $likedFoods);
        }

        if (!empty($dislikedFoods)) {
            $parts[] = "Aliments NON aimés (à exclure) — disliked_foods : " . implode(', ', $dislikedFoods);
        }

        return implode("\n", $parts);
    }

    private function callAnthropic(string $system, string $user, int $maxTokens = 512): string
    {
        $response = Http::withHeaders([
            'x-api-key'         => config('llm.anthropic.api_key'),
            'anthropic-version' => '2023-06-01',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model'      => config('llm.anthropic.model'),
            'max_tokens' => $maxTokens,
            'system'     => $system,
            'messages'   => [['role' => 'user', 'content' => $user]],
        ]);

        $response->throw();

        return $response->json('content.0.text') ?? throw new \RuntimeException('Anthropic : contenu vide');
    }

    private function callOpenAI(string $system, string $user, int $maxTokens = 512): string
    {
        $response = Http::withToken(config('llm.openai.api_key'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'      => config('llm.openai.model'),
                'max_tokens' => $maxTokens,
                'messages'   => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
            ]);

        $response->throw();

        return $response->json('choices.0.message.content') ?? throw new \RuntimeException('OpenAI : contenu vide');
    }
}
