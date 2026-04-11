<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserTypeEnum;
use App\Models\StudentEnrollment;
use App\Models\User;

class StudentEnrollmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->user_type === UserTypeEnum::EMPLOYEE;
    }

    public function view(User $user, StudentEnrollment $studentEnrollment): bool
    {
        return $user->user_type === UserTypeEnum::EMPLOYEE;
    }

    public function create(User $user): bool
    {
        return $user->user_type === UserTypeEnum::EMPLOYEE;
    }

    public function update(User $user, StudentEnrollment $studentEnrollment): bool
    {
        return $user->user_type === UserTypeEnum::EMPLOYEE;
    }

    public function delete(User $user, StudentEnrollment $studentEnrollment): bool
    {
        return $user->user_type === UserTypeEnum::EMPLOYEE;
    }
}
