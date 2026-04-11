<?php

declare(strict_types=1);

use App\Enums\UserTypeEnum;
use App\Models\Student;
use App\Models\User;

test('employee can view any students', function () {
    $user = User::factory()->employee()->create();

    expect($user->can('viewAny', Student::class))->toBeTrue();
});

test('employee can view a student', function () {
    $user = User::factory()->employee()->create();
    $student = Student::factory()->create();

    expect($user->can('view', $student))->toBeTrue();
});

test('employee can create a student', function () {
    $user = User::factory()->employee()->create();

    expect($user->can('create', Student::class))->toBeTrue();
});

test('employee can update a student', function () {
    $user = User::factory()->employee()->create();
    $student = Student::factory()->create();

    expect($user->can('update', $student))->toBeTrue();
});

test('employee can delete a student', function () {
    $user = User::factory()->employee()->create();
    $student = Student::factory()->create();

    expect($user->can('delete', $student))->toBeTrue();
});

test('non-employee cannot view any students', function (UserTypeEnum $type) {
    $user = User::factory()->create(['user_type' => $type]);

    expect($user->can('viewAny', Student::class))->toBeFalse();
})->with([
    UserTypeEnum::NOTYPE,
    UserTypeEnum::STUDENT,
    UserTypeEnum::PARENT,
    UserTypeEnum::GUARDIAN,
]);

test('non-employee cannot create a student', function (UserTypeEnum $type) {
    $user = User::factory()->create(['user_type' => $type]);

    expect($user->can('create', Student::class))->toBeFalse();
})->with([
    UserTypeEnum::NOTYPE,
    UserTypeEnum::STUDENT,
    UserTypeEnum::PARENT,
    UserTypeEnum::GUARDIAN,
]);
