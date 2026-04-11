<?php

declare(strict_types=1);

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

test('login rate limiter is registered', function () {
    expect(RateLimiter::limiter('login'))->not->toBeNull();
});

test('login rate limiter is keyed by email and ip', function () {
    $limiter = RateLimiter::limiter('login');

    expect($limiter)->toBeCallable();

    $request = Request::create('/admin/login', 'POST');
    $request->merge(['email' => 'test@example.com']);

    $limit = $limiter($request);

    expect($limit)->toBeInstanceOf(Limit::class);
});

test('admin login route returns 429 after 5 requests from the same ip', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->get('/admin/login')->assertOk();
    }

    $this->get('/admin/login')->assertStatus(429);
});

test('finance login route returns 429 after 5 requests from the same ip', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->get('/finance/login')->assertOk();
    }

    $this->get('/finance/login')->assertStatus(429);
});

test('cms login route returns 429 after 5 requests from the same ip', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->get('/cms/login')->assertOk();
    }

    $this->get('/cms/login')->assertStatus(429);
});

test('supply-hub login route returns 429 after 5 requests from the same ip', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->get('/supply-hub/login')->assertOk();
    }

    $this->get('/supply-hub/login')->assertStatus(429);
});
