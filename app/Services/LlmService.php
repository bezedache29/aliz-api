<?php

namespace App\Services;

use App\Models\Recipe;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class LlmService
{
    public function suggestRecipe(
        string $date,
        string $mealType,
        Collection $recipes,
        ?string $prompt,
        ?array $mealBudget = null,
        array $expiringStock = [],
        array $otherStock = [],
        array $likedFoods = [],
        array $dislikedFoods = [],
        array $plannedTodayMeals = [],
    ): array {
        $provider = config('llm.provider', 'anthropic');
        $system = $this->buildSystemPrompt();
        $user = $this->buildUserMessage(
            $date,
            $mealType,
            $recipes,
            $prompt,
            $mealBudget,
            $expiringStock,
            $otherStock,
            $likedFoods,
            $dislikedFoods,
            $plannedTodayMeals,
        );

        $raw = match ($provider) {
            'openai' => $this->callOpenAI($system, $user, 2048),
            default  => $this->callAnthropic($system, $user, 2048),
        };

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || array_is_list($decoded) || !in_array($decoded['type'] ?? null, ['existing', 'new', 'menu'], true)) {
            throw new \RuntimeException('Réponse LLM invalide : ' . $raw);
        }

        if ($decoded['type'] === 'menu') {
            if (empty($decoded['courses']) || !is_array($decoded['courses']) || !array_is_list($decoded['courses'])) {
                throw new \RuntimeException('Réponse LLM invalide : courses manquant ou invalide');
            }

            foreach ($decoded['courses'] as $course) {
                if (!is_array($course) || empty($course['course'])) {
                    throw new \RuntimeException('Réponse LLM invalide : nom de plat (course) manquant');
                }
                $this->validateSuggestion($course);
            }

            return $decoded;
        }

        $this->validateSuggestion($decoded);

        return $decoded;
    }

    private function validateSuggestion(array $suggestion): void
    {
        if (!in_array($suggestion['type'] ?? null, ['existing', 'new'], true)) {
            throw new \RuntimeException('Réponse LLM invalide : type de suggestion manquant ou invalide');
        }

        if ($suggestion['type'] === 'new' && (empty($suggestion['steps']) || empty($suggestion['ingredients']))) {
            throw new \RuntimeException('Réponse LLM invalide : steps ou ingredients manquants');
        }
    }

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
Tu es un assistant nutritionnel pour l'app Aliz. Tu suggères des recettes adaptées au type de repas.

Réponds UNIQUEMENT en JSON valide, sans markdown, sans explication.

Si une recette existante convient (et ne duplique pas un repas déjà prévu aujourd'hui, voir règle 1), retourne :
{"type":"existing","recipe_id":"<uuid>"}

RÈGLE ABSOLUE, PRIORITAIRE SUR TOUTES LES AUTRES : la recette doit être un plat réel et cohérent, comme on en trouverait dans un livre de cuisine ou servi au restaurant — jamais un assemblage forcé d'ingrédients qui ne vont pas ensemble. Le respect du stock, de la DLC ou de la variété ne doit JAMAIS se faire au détriment du bon sens culinaire. Si un aliment du stock ou de la liste "expiring_soon" ne s'intègre pas naturellement dans un plat cohérent, ignore-le simplement pour cette recette — il pourra être utilisé un autre jour. N'associe jamais des éléments qui ne vont pas ensemble (ex. ne mets pas une salade froide type macédoine à l'intérieur d'une quiche cuite, n'improvise pas une association de saveurs qui n'existe dans aucune tradition culinaire).

Règles de priorité, seulement une fois la cohérence culinaire ci-dessus respectée (valables aussi bien pour choisir une recette "existing" que pour créer une suggestion "new") :
1. VARIÉTÉ : si des repas sont déjà prévus le même jour (liste "today_meals"), ne choisis pas une recette existante identique et ne réutilise PAS le même aliment principal (viande, poisson, féculent, légume principal) que l'un de ces repas — quitte à ignorer une opportunité d'écouler le stock ou la DLC. L'utilisateur ne veut pas manger la même chose au déjeuner et au dîner.
2. Une fois la variété respectée, privilégie les aliments dont la DLC arrive bientôt (liste "expiring_soon") si c'est cohérent avec le repas — ce n'est qu'une préférence, jamais une obligation si cela nuit à la cohérence ou à la variété.
3. Utilise en priorité les aliments disponibles en stock (liste "other_stock"), toujours sous réserve de cohérence.
4. Tu peux suggérer des ingrédients hors stock si nécessaire pour compléter la recette ou pour varier.
5. N'utilise JAMAIS les aliments de la liste "disliked_foods", et ne choisis pas de recette existante qui en contient.
6. Favorise les aliments de la liste "liked_foods".
7. Chaque aliment du stock est donné avec sa quantité DANS SON UNITÉ D'ORIGINE (g, pièce(s), boîte(s), tranche(s), portion(s), sachet(s)...), précisée entre parenthèses. Une quantité en "pièce(s)" ou "boîte(s)" n'est PAS un poids en grammes : déduis un poids réaliste pour ce type de produit (ex. une pièce de type plat cuisiné pané ~120-180g, une boîte de conserve ~200-400g). N'utilise jamais aveuglément le nombre affiché comme un grammage.
8. N'utilise pas forcément la totalité du stock disponible : choisis une quantité cohérente avec une portion de repas pour ce type de plat.

Sinon, crée une suggestion complète et détaillée, et retourne :
{
  "type": "new",
  "name": "<nom>",
  "description": "<description courte>",
  "kcal": <nombre>,
  "proteines": <nombre>,
  "glucides": <nombre>,
  "lipides": <nombre>,
  "prep_time": <minutes>,
  "cook_time": <minutes>,
  "steps": ["<étape 1>", "<étape 2>", ...],
  "ingredients": [
    {"food_name": "<nom>", "quantity_g": <nombre>, "per100g_kcal": <nombre>, "per100g_proteines": <nombre>, "per100g_glucides": <nombre>, "per100g_lipides": <nombre>}
  ]
}

Les étapes et ingrédients sont obligatoires pour une suggestion "new" : l'utilisateur doit pouvoir cuisiner le plat rien qu'avec ces informations.
Les macros (kcal, proteines, glucides, lipides) doivent être cohérentes avec les ingrédients et leurs quantités.

Si un budget nutritionnel pour le repas est fourni, la suggestion (existante ou nouvelle, ou l'ensemble des plats d'un menu) doit s'en rapprocher au total, avec une tolérance de ±15%.

MENU À PLUSIEURS PLATS (optionnel) : pour "Déjeuner" ou "Dîner" uniquement, si plusieurs aliments à utiliser ne peuvent pas cohabiter dans un seul plat cohérent (ex. de la charcuterie et un fruit à utiliser ensemble n'ont de sens que dans deux plats séparés : une entrée et un dessert), tu PEUX proposer un menu de 2 à 3 plats au lieu d'un plat unique. Reste exceptionnel : la plupart des repas restent un plat unique. Retourne alors :
{
  "type": "menu",
  "courses": [
    {"course": "Entrée", ... mêmes champs qu'une suggestion "existing" ou "new" ci-dessus ...},
    {"course": "Plat", ...},
    {"course": "Dessert", ...}
  ]
}
Chaque plat du menu doit lui-même respecter TOUTES les règles ci-dessus (cohérence culinaire, variété, stock...), y compris la variété entre eux (ne mets pas deux fois le même aliment principal dans le menu). "Petit-déjeuner" et "Collation" doivent toujours rester un plat unique, jamais un menu.
PROMPT;
    }

    private function buildUserMessage(
        string $date,
        string $mealType,
        Collection $recipes,
        ?string $prompt,
        ?array $mealBudget = null,
        array $expiringStock = [],
        array $otherStock = [],
        array $likedFoods = [],
        array $dislikedFoods = [],
        array $plannedTodayMeals = [],
    ): string {
        $recipesJson = $recipes->map(fn(Recipe $r) => [
            'id'       => $r->id,
            'name'     => $r->name,
            'meal'     => $r->meal,
            'category' => $r->category,
        ])->values()->toJson(JSON_UNESCAPED_UNICODE);

        $parts = ["Date : {$date}", "Type de repas : {$mealType}"];

        if ($prompt) {
            $parts[] = "Contexte : {$prompt}";
        }

        if ($mealBudget) {
            $parts[] = "Budget nutritionnel pour ce repas : {$mealBudget['kcal']} kcal, {$mealBudget['proteines']}g protéines, {$mealBudget['glucides']}g glucides, {$mealBudget['lipides']}g lipides";
        }

        if (!empty($plannedTodayMeals)) {
            $list    = collect($plannedTodayMeals)
                ->map(fn ($m) => "{$m['meal_type']} : {$m['name']} (" . implode(', ', $m['ingredients']) . ')')
                ->join(' | ');
            $parts[] = "🚫 Repas déjà prévus aujourd'hui (à NE PAS dupliquer, varie les ingrédients principaux) — today_meals : {$list}";
        }

        if (!empty($expiringStock)) {
            $parts[] = "⚠️ À utiliser EN PRIORITÉ (DLC proche) — expiring_soon : {$this->formatStockList($expiringStock)}";
        }

        if (!empty($otherStock)) {
            $parts[] = "Stock disponible — other_stock : {$this->formatStockList($otherStock)}";
        }

        if (!empty($likedFoods)) {
            $parts[] = "Aliments aimés — liked_foods : " . implode(', ', $likedFoods);
        }

        if (!empty($dislikedFoods)) {
            $parts[] = "Aliments NON aimés (à exclure) — disliked_foods : " . implode(', ', $dislikedFoods);
        }

        $parts[] = "Recettes disponibles :\n{$recipesJson}";
        $parts[] = 'Suggère la recette la plus adaptée.';

        return implode("\n", $parts);
    }

    private function formatStockList(array $items): string
    {
        return collect($items)->map(function (array $i) {
            $line = "{$i['food_name']} ({$i['quantity']} {$i['unit']}";

            if (isset($i['per100g_kcal'])) {
                $line .= sprintf(
                    ' ; %sg kcal/100g, P:%sg/100g, G:%sg/100g, L:%sg/100g',
                    $i['per100g_kcal'],
                    $i['per100g_proteines'],
                    $i['per100g_glucides'],
                    $i['per100g_lipides'],
                );
            }

            if (isset($i['expiry_date'])) {
                $line .= ", DLC : {$i['expiry_date']}";
            }

            return $line . ')';
        })->join(', ');
    }

    public function generateFullRecipe(
        ?string $userPrompt,
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

RÈGLE ABSOLUE, PRIORITAIRE SUR TOUTES LES AUTRES : la recette doit être un plat réel et cohérent, comme on en trouverait dans un livre de cuisine ou servi au restaurant — jamais un assemblage forcé d'ingrédients qui ne vont pas ensemble. Le respect du stock ou de la DLC ne doit JAMAIS se faire au détriment du bon sens culinaire. Si un aliment du stock ou de la liste "expiring_soon" ne s'intègre pas naturellement dans un plat cohérent, ignore-le simplement — il pourra être utilisé une autre fois. N'associe jamais des éléments qui ne vont pas ensemble (ex. ne mets pas une salade froide type macédoine à l'intérieur d'une quiche cuite, n'improvise pas une association de saveurs qui n'existe dans aucune tradition culinaire).

Règles de priorité pour les ingrédients, seulement une fois la cohérence culinaire ci-dessus respectée :
1. Privilégie les aliments dont la DLC arrive bientôt (liste "expiring_soon") si c'est cohérent avec la recette — ce n'est qu'une préférence, jamais une obligation si cela nuit à la cohérence.
2. Utilise en priorité les aliments disponibles en stock (liste "other_stock"), toujours sous réserve de cohérence.
3. Tu peux suggérer des ingrédients hors stock si nécessaire pour compléter la recette.
4. N'utilise JAMAIS les aliments de la liste "disliked_foods".
5. Favorise les aliments de la liste "liked_foods".
6. Chaque aliment du stock est donné avec sa quantité DANS SON UNITÉ D'ORIGINE (g, pièce(s), boîte(s), tranche(s), portion(s), sachet(s)...), précisée entre parenthèses. Une quantité en "pièce(s)" ou "boîte(s)" n'est PAS un poids en grammes : déduis un poids réaliste pour ce type de produit (ex. une pièce de type plat cuisiné pané ~120-180g, une boîte de conserve ~200-400g) avant de renseigner "quantity_g" pour cet ingrédient. N'utilise jamais aveuglément le nombre affiché comme un grammage.
7. N'utilise pas forcément la totalité du stock disponible : choisis une quantité cohérente avec une portion de repas pour ce type de plat.

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
        ?string $userPrompt,
        array $expiringStock,
        array $otherStock,
        array $likedFoods,
        array $dislikedFoods,
        ?array $profileContext,
    ): string {
        $parts = [];

        if ($userPrompt) {
            $parts[] = "Demande : {$userPrompt}";
        } else {
            $parts[] = "Demande : aucune préférence précise, propose librement un plat cohérent adapté à mon profil et mon stock.";
        }

        if ($profileContext) {
            $parts[] = "Objectif nutritionnel : {$profileContext['kcal']} kcal/jour, {$profileContext['proteines']}g protéines/jour";
        }

        if (!empty($expiringStock)) {
            $parts[] = "⚠️ À utiliser EN PRIORITÉ (DLC proche) — expiring_soon : {$this->formatStockList($expiringStock)}";
        }

        if (!empty($otherStock)) {
            $parts[] = "Stock disponible — other_stock : {$this->formatStockList($otherStock)}";
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
