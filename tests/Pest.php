<?php

use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->beforeEach(fn () => config(['app.static_api_token' => 'test-token']))
    ->in('Feature');
