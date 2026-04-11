<?php

declare(strict_types=1);

use App\Enums\UserTypeEnum;
use App\Models\Branch;
use App\Models\User;

test('employee can view any branches', function () {
    $user = User::factory()->employee()->create();

    expect($user->can('viewAny', Branch::class))->toBeTrue();
});

test('employee can view a branch', function () {
    $user = User::factory()->employee()->create();
    $branch = Branch::factory()->create();

    expect($user->can('view', $branch))->toBeTrue();
});

test('employee can create a branch', function () {
    $user = User::factory()->employee()->create();

    expect($user->can('create', Branch::class))->toBeTrue();
});

test('employee can update a branch', function () {
    $user = User::factory()->employee()->create();
    $branch = Branch::factory()->create();

    expect($user->can('update', $branch))->toBeTrue();
});

test('employee can delete a branch', function () {
    $user = User::factory()->employee()->create();
    $branch = Branch::factory()->create();

    expect($user->can('delete', $branch))->toBeTrue();
});

test('non-employee cannot view any branches', function (UserTypeEnum $type) {
    $user = User::factory()->create(['user_type' => $type]);

    expect($user->can('viewAny', Branch::class))->toBeFalse();
})->with([
    UserTypeEnum::NOTYPE,
    UserTypeEnum::STUDENT,
    UserTypeEnum::PARENT,
    UserTypeEnum::GUARDIAN,
]);

test('non-employee cannot create a branch', function (UserTypeEnum $type) {
    $user = User::factory()->create(['user_type' => $type]);

    expect($user->can('create', Branch::class))->toBeFalse();
})->with([
    UserTypeEnum::NOTYPE,
    UserTypeEnum::STUDENT,
    UserTypeEnum::PARENT,
    UserTypeEnum::GUARDIAN,
]);
