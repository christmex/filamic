<?php

declare(strict_types=1);

use App\Enums\UserTypeEnum;
use App\Models\Invoice;
use App\Models\User;

test('employee can view any invoices', function () {
    $user = User::factory()->employee()->create();

    expect($user->can('viewAny', Invoice::class))->toBeTrue();
});

test('employee can view an invoice', function () {
    $user = User::factory()->employee()->create();
    $invoice = Invoice::factory()->create();

    expect($user->can('view', $invoice))->toBeTrue();
});

test('employee can create an invoice', function () {
    $user = User::factory()->employee()->create();

    expect($user->can('create', Invoice::class))->toBeTrue();
});

test('employee can update an invoice', function () {
    $user = User::factory()->employee()->create();
    $invoice = Invoice::factory()->create();

    expect($user->can('update', $invoice))->toBeTrue();
});

test('employee can delete an invoice', function () {
    $user = User::factory()->employee()->create();
    $invoice = Invoice::factory()->create();

    expect($user->can('delete', $invoice))->toBeTrue();
});

test('non-employee cannot view any invoices', function (UserTypeEnum $type) {
    $user = User::factory()->create(['user_type' => $type]);

    expect($user->can('viewAny', Invoice::class))->toBeFalse();
})->with([
    UserTypeEnum::NOTYPE,
    UserTypeEnum::STUDENT,
    UserTypeEnum::PARENT,
    UserTypeEnum::GUARDIAN,
]);

test('non-employee cannot create an invoice', function (UserTypeEnum $type) {
    $user = User::factory()->create(['user_type' => $type]);

    expect($user->can('create', Invoice::class))->toBeFalse();
})->with([
    UserTypeEnum::NOTYPE,
    UserTypeEnum::STUDENT,
    UserTypeEnum::PARENT,
    UserTypeEnum::GUARDIAN,
]);
