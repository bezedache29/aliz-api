<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProfileRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function show(): JsonResponse
    {
        $profile = Profile::first();

        if (! $profile) {
            return response()->json(['message' => 'Profil non trouvé.'], 404);
        }

        return response()->json(['data' => $profile]);
    }

    public function store(StoreProfileRequest $request): JsonResponse
    {
        if (Profile::exists()) {
            return response()->json(['message' => 'Un profil existe déjà.'], 409);
        }

        $profile = Profile::create($request->validated());

        return response()->json(['data' => $profile], 201);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $profile = Profile::first();

        if (! $profile) {
            return response()->json(['message' => 'Profil non trouvé.'], 404);
        }

        $profile->update($request->validated());

        return response()->json(['data' => $profile->refresh()]);
    }
}
