<?php

declare(strict_types=1);

use App\Models\SchoolTerm;
use App\Models\SchoolYear;

beforeEach(function () {
    SchoolYear::factory()->active()->create();
    SchoolTerm::factory()->create(['is_active' => true]);
    $this->branch = $this->loginFinance();
});

test('authenticated users can access finance panel', function () {
    $this->get("/finance/{$this->branch->getKey()}/students")->assertOk();
});

test('authenticated users are not throttled on finance panel pages', function () {
    foreach (range(1, 6) as $_) {
        $this->get("/finance/{$this->branch->getKey()}/students")->assertOk();
    }
});
