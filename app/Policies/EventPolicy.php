<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function before(User $user): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function view(User $user, Event $event): bool
    {
        if ($user->hasRole('manager')) {
            return $user->company_id !== null && $user->company_id === $event->company_id;
        }

        return $user->hasRole('usher')
            && $user->events()->whereKey($event->id)->exists();
    }

    public function update(User $user, Event $event): bool
    {
        return $user->hasRole('manager')
            && $user->company_id !== null
            && $user->company_id === $event->company_id;
    }

    public function manageWhenOpen(User $user, Event $event): bool
    {
        return $this->update($user, $event) && ! $event->isClosed();
    }

    public function scanAttendance(User $user, Event $event): bool
    {
        if ($user->hasRole('manager')) {
            return $user->company_id !== null && $user->company_id === $event->company_id;
        }

        return $user->hasRole('usher')
            && $user->events()->whereKey($event->id)->exists();
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }
}
