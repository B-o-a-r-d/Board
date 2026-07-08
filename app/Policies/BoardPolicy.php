<?php

namespace App\Policies;

use App\Enums\BoardVisibility;
use App\Enums\Permission;
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
     * Contribute to the board's content: create/edit/move cards, lists,
     * checklists, dates, labels, members. Denied to read-only roles (Observer).
     */
    public function contribute(User $user, Board $board): bool
    {
        return $board->userCan($user, Permission::CardManage);
    }

    /**
     * Post comments and reactions.
     */
    public function comment(User $user, Board $board): bool
    {
        return $board->userCan($user, Permission::CommentPost);
    }

    /**
     * Update board settings (rename, background, lists config, custom fields).
     */
    public function update(User $user, Board $board): bool
    {
        return $board->userCan($user, Permission::BoardSettings);
    }

    /**
     * Manage board members and their roles.
     */
    public function manageMembers(User $user, Board $board): bool
    {
        return $board->userCan($user, Permission::MemberManage);
    }

    /**
     * Delete the board.
     */
    public function delete(User $user, Board $board): bool
    {
        return $board->userCan($user, Permission::BoardDelete);
    }

    /**
     * Install and configure plugins (Power-Ups), which can hold OAuth credentials.
     */
    public function managePlugins(User $user, Board $board): bool
    {
        return $board->userCan($user, Permission::PluginManage);
    }
}
