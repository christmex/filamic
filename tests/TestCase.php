<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Branch;
use App\Models\School;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Context;

abstract class TestCase extends BaseTestCase
{
    public function login(?User $user = null): void
    {
        $this->actingAs($user ?? User::factory()->create());
    }

    public function loginAdmin(?User $user = null, ?School $school = null): void
    {
        $school ??= School::factory()->create();
        Context::add('school', $school);

        $user ??= User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'super@admin.com',
            'password' => bcrypt('mantapjiwa00'),
        ]);

        $this->login($user);
    }

    public function loginFinance(?User $user = null, ?Branch $branch = null): Branch
    {
        $branch ??= Branch::factory()->create();
        $user ??= User::factory()->create();
        $branch->users()->attach($user->getKey());

        $this->actingAs($user);
        Filament::setCurrentPanel('finance');
        Filament::getPanel('finance')->boot();
        Filament::setTenant($branch);

        return $branch;
    }

    public function loginSupplyHub(?User $user = null, ?Branch $branch = null): Branch
    {
        $branch ??= Branch::factory()->create();
        $user ??= User::factory()->create();
        $branch->users()->attach($user->getKey());

        $this->actingAs($user);
        Filament::setCurrentPanel('supply-hub');
        Filament::getPanel('supply-hub')->boot();
        Filament::setTenant($branch);

        return $branch;
    }
}
