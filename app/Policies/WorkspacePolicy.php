<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    /**
     * Any authenticated user may create a workspace.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * A member of the workspace may view it.
     */
    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->hasMember($user);
    }

    /**
     * Owners and admins may update the workspace.
     */
    public function update(User $user, Workspace $workspace): bool
    {
        return $workspace->memberRole($user)?->isAdministrator() ?? false;
    }

    /**
     * Owners and admins may manage members and invitations.
     */
    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return $workspace->memberRole($user)?->isAdministrator() ?? false;
    }

    /**
     * Only the owner may delete the workspace.
     */
    public function delete(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id === $user->getKey();
    }
}
