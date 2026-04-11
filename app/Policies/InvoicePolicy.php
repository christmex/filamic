<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserTypeEnum;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->user_type === UserTypeEnum::EMPLOYEE;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->user_type === UserTypeEnum::EMPLOYEE;
    }

    public function create(User $user): bool
    {
        return $user->user_type === UserTypeEnum::EMPLOYEE;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->user_type === UserTypeEnum::EMPLOYEE;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->user_type === UserTypeEnum::EMPLOYEE;
    }
}
