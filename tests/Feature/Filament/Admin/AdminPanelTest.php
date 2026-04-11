<?php

declare(strict_types=1);

test('authenticated users can access admin panel', function () {
    $this->loginAdmin();

    $this->get(route('filament.admin.pages.dashboard'))
        ->assertOk();
});

test('authenticated users are not throttled on admin panel pages', function () {
    $this->loginAdmin();

    foreach (range(1, 6) as $_) {
        $this->get(route('filament.admin.pages.dashboard'))->assertOk();
    }
});
