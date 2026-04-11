<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserTypeEnum;
use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->user_type === UserTypeEnum::EMPLOYEE;
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->user_type === UserTypeEnum::EMPLOYEE;
    }

    public function create(User $user): bool
    {
        return $user->user_type === UserTypeEnum::EMPLOYEE;
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->user_type === UserTypeEnum::EMPLOYEE;
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->user_type === UserTypeEnum::EMPLOYEE;
    }
}
