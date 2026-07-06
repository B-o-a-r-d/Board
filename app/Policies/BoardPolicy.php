<?php

namespace App\Policies;

use App\Enums\BoardVisibility;
use App\Models\Board;
use App\Models\User;

class BoardPolicy
{
    /**
     * A user may create a board within a workspace they belong to.
     * The workspace is passed through the request; membership is checked in the component.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * A user may view a board when they are a board member, a workspace
     * administrator, or the board is visible to the whole workspace.
     */
    public function view(User $user, Board $board): bool
    {
        if ($board->hasMember($user)) {
            return true;
        }

        $workspaceRole = $board->workspace->memberRole($user);

        if ($workspaceRole?->isAdministrator()) {
            return true;
        }

        return $board->visibility === BoardVisibility::Workspace
            && $workspaceRole !== null;
    }

    /**
     * Board admins/owners and workspace admins may update the board.
     */
    public function update(User $user, Board $board): bool
    {
        return $this->canAdminister($user, $board);
    }

    /**
     * Board admins/owners and workspace admins may manage board members.
     */
    public function manageMembers(User $user, Board $board): bool
    {
        return $this->canAdminister($user, $board);
    }

    /**
     * Board admins/owners and workspace admins may delete the board.
     */
    public function delete(User $user, Board $board): bool
    {
        return $this->canAdminister($user, $board);
    }

    private function canAdminister(User $user, Board $board): bool
    {
        if ($board->memberRole($user)?->isAdministrator()) {
            return true;
        }

        return $board->workspace->memberRole($user)?->isAdministrator() ?? false;
    }
}
