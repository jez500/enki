<?php

namespace App\Policies;

use App\Models\Skill;
use App\Models\User;

class SkillPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function update(User $user, Skill $skill): bool
    {
        return $skill->created_by_user_id === $user->id;
    }

    public function delete(User $user, Skill $skill): bool
    {
        return $skill->created_by_user_id === $user->id;
    }
}
