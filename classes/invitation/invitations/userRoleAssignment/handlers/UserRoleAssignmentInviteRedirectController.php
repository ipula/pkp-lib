<?php

/**
 * @file classes/invitation/invitations/handlers/ChangeProfileEmailInviteRedirectController.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ChangeProfileEmailInviteRedirectController
 *
 * @brief Change Profile Email invitation
 */

namespace PKP\invitation\invitations\userRoleAssignment\handlers;

use APP\core\Request;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;
use PKP\core\PKPApplication;
use PKP\invitation\core\enums\InvitationAction;
use PKP\invitation\core\enums\InvitationStatus;
use PKP\invitation\core\InvitationActionRedirectController;
use PKP\invitation\invitations\userRoleAssignment\UserRoleAssignmentInvite;

class UserRoleAssignmentInviteRedirectController extends InvitationActionRedirectController
{
    public function getInvitation(): UserRoleAssignmentInvite
    {
        return $this->invitation;
    }

    public function acceptHandle(Request $request): void
    {
        $templateMgr = TemplateManager::getManager($request);

        $templateMgr->assign('invitation', $this->invitation);
        $templateMgr->display('frontend/pages/invitations.tpl');
    }

    public function declineHandle(Request $request): void
    {
        return;
    }

    public function preRedirectActions(InvitationAction $action)
    {
        return;
    }
}
