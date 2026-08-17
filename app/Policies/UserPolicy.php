<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function before(User $actor): ?bool
    {
        return $actor->hasRole('admin') ? true : null;
    }

    public function manage(User $actor, User $subject): bool
    {
        return $actor->hasRole('manager')
            && $actor->company_id !== null
            && $actor->company_id === $subject->company_id
            && $subject->hasRole('usher');
    }
}
