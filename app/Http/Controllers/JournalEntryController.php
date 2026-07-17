<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJournalEntryRequest;
use App\Http\Requests\UpdateJournalEntryRequest;
use App\Models\JournalEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class JournalEntryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $entries = JournalEntry::where('date', $validated['date'])
            ->orderBy('meal_type')
            ->orderBy('created_at')
            ->get();

        return response()->json(['data' => $entries]);
    }

    public function store(StoreJournalEntryRequest $request): JsonResponse
    {
        $entry = JournalEntry::create($request->validated());

        return response()->json(['data' => $entry], 201);
    }

    public function update(UpdateJournalEntryRequest $request, JournalEntry $journalEntry): JsonResponse
    {
        $journalEntry->update($request->validated());

        return response()->json(['data' => $journalEntry->refresh()]);
    }

    public function destroy(JournalEntry $journalEntry): Response
    {
        $journalEntry->delete();

        return response()->noContent();
    }
}
