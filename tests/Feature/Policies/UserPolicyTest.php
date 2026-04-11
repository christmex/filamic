<?php

declare(strict_types=1);

use App\Enums\UserTypeEnum;
use App\Models\User;

test('employee can view any users', function () {
    $user = User::factory()->employee()->create();

    expect($user->can('viewAny', User::class))->toBeTrue();
});

test('employee can view a user', function () {
    $user = User::factory()->employee()->create();
    $target = User::factory()->create();

    expect($user->can('view', $target))->toBeTrue();
});

test('employee can create a user', function () {
    $user = User::factory()->employee()->create();

    expect($user->can('create', User::class))->toBeTrue();
});

test('employee can update a user', function () {
    $user = User::factory()->employee()->create();
    $target = User::factory()->create();

    expect($user->can('update', $target))->toBeTrue();
});

test('employee can delete a user', function () {
    $user = User::factory()->employee()->create();
    $target = User::factory()->create();

    expect($user->can('delete', $target))->toBeTrue();
});

test('non-employee cannot view any users', function (UserTypeEnum $type) {
    $user = User::factory()->create(['user_type' => $type]);

    expect($user->can('viewAny', User::class))->toBeFalse();
})->with([
    UserTypeEnum::NOTYPE,
    UserTypeEnum::STUDENT,
    UserTypeEnum::PARENT,
    UserTypeEnum::GUARDIAN,
]);

test('non-employee cannot create a user', function (UserTypeEnum $type) {
    $user = User::factory()->create(['user_type' => $type]);

    expect($user->can('create', User::class))->toBeFalse();
})->with([
    UserTypeEnum::NOTYPE,
    UserTypeEnum::STUDENT,
    UserTypeEnum::PARENT,
    UserTypeEnum::GUARDIAN,
]);
