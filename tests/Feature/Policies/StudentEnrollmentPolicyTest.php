<?php

declare(strict_types=1);

use App\Enums\UserTypeEnum;
use App\Models\StudentEnrollment;
use App\Models\User;

test('employee can view any student enrollments', function () {
    $user = User::factory()->employee()->create();

    expect($user->can('viewAny', StudentEnrollment::class))->toBeTrue();
});

test('employee can view a student enrollment', function () {
    $user = User::factory()->employee()->create();
    $enrollment = StudentEnrollment::factory()->create();

    expect($user->can('view', $enrollment))->toBeTrue();
});

test('employee can create a student enrollment', function () {
    $user = User::factory()->employee()->create();

    expect($user->can('create', StudentEnrollment::class))->toBeTrue();
});

test('employee can update a student enrollment', function () {
    $user = User::factory()->employee()->create();
    $enrollment = StudentEnrollment::factory()->create();

    expect($user->can('update', $enrollment))->toBeTrue();
});

test('employee can delete a student enrollment', function () {
    $user = User::factory()->employee()->create();
    $enrollment = StudentEnrollment::factory()->create();

    expect($user->can('delete', $enrollment))->toBeTrue();
});

test('non-employee cannot view any student enrollments', function (UserTypeEnum $type) {
    $user = User::factory()->create(['user_type' => $type]);

    expect($user->can('viewAny', StudentEnrollment::class))->toBeFalse();
})->with([
    UserTypeEnum::NOTYPE,
    UserTypeEnum::STUDENT,
    UserTypeEnum::PARENT,
    UserTypeEnum::GUARDIAN,
]);

test('non-employee cannot create a student enrollment', function (UserTypeEnum $type) {
    $user = User::factory()->create(['user_type' => $type]);

    expect($user->can('create', StudentEnrollment::class))->toBeFalse();
})->with([
    UserTypeEnum::NOTYPE,
    UserTypeEnum::STUDENT,
    UserTypeEnum::PARENT,
    UserTypeEnum::GUARDIAN,
]);
