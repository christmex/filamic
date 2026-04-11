<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserTypeEnum;
use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->user_type === UserTypeEnum::EMPLOYEE;
    }

    public function view(User $user, Student $student): bool
    {
        return $user->user_type === UserTypeEnum::EMPLOYEE;
    }

    public function create(User $user): bool
    {
        return $user->user_type === UserTypeEnum::EMPLOYEE;
    }

    public function update(User $user, Student $student): bool
    {
        return $user->user_type === UserTypeEnum::EMPLOYEE;
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->user_type === UserTypeEnum::EMPLOYEE;
    }
}
