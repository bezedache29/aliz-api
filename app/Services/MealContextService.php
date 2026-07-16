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
            ->map(fn ($i) => [...$this->formatStockItem($i), 'expiry_date' => $i->expiry_date->toDateString()])
            ->values()->all();

        $other = $allStock
            ->filter(fn ($i) => !$i->expiry_date || $i->expiry_date->toDateString() > $threshold)
            ->map(fn ($i) => $this->formatStockItem($i))
            ->values()->all();

        return ['expiring' => $expiring, 'other' => $other];
    }

    private function formatStockItem(StockItem $item): array
    {
        $data = [
            'food_name' => $item->food_name,
            'quantity'  => (float) $item->quantity_g,
            'unit'      => $item->unit ?? 'g',
        ];

        if ($item->per100g_kcal !== null) {
            $data['per100g_kcal']      = (float) $item->per100g_kcal;
            $data['per100g_proteines'] = (float) $item->per100g_proteines;
            $data['per100g_glucides']  = (float) $item->per100g_glucides;
            $data['per100g_lipides']   = (float) $item->per100g_lipides;
        }

        return $data;
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
