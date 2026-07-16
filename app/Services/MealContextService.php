<?php

namespace App\Services;

use App\Models\FoodPreference;
use App\Models\StockItem;

class MealContextService
{
    public function stock(bool $useStock = true): array
    {
        $threshold = now()->addDays(7)->toDateString();
        $allStock  = $useStock ? StockItem::all() : collect();

        $expiring = $allStock
            ->filter(fn ($i) => $i->expiry_date && $i->expiry_date->toDateString() <= $threshold)
            ->map(fn ($i) => ['food_name' => $i->food_name, 'quantity_g' => $i->quantity_g, 'expiry_date' => $i->expiry_date->toDateString()])
            ->values()->all();

        $other = $allStock
            ->filter(fn ($i) => !$i->expiry_date || $i->expiry_date->toDateString() > $threshold)
            ->map(fn ($i) => ['food_name' => $i->food_name, 'quantity_g' => $i->quantity_g])
            ->values()->all();

        return ['expiring' => $expiring, 'other' => $other];
    }

    public function foodPreferences(): array
    {
        $preferences = FoodPreference::all()->groupBy('type');

        return [
            'liked'    => $preferences->get('liked', collect())->pluck('food_name')->all(),
            'disliked' => $preferences->get('disliked', collect())->pluck('food_name')->all(),
        ];
    }
}
