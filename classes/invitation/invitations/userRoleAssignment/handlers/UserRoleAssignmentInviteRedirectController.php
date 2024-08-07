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
use APP\template\TemplateManager;
use PKP\invitation\core\enums\InvitationAction;
use PKP\invitation\core\InvitationActionRedirectController;
use PKP\invitation\invitations\userRoleAssignment\UserRoleAssignmentInvite;
use PKP\invitation\stepTypes\AcceptInvitationStep;

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
        $context = $request->getContext();
        $steps = new AcceptInvitationStep();
        $templateMgr->setState([
            'steps' => $steps->getSteps($this->invitation, $context),
            'primaryLocale' => $context->getData('primaryLocale'),
            'pageTitle' => __('invitation.wizard.pageTitle'),
            'invitationId' => (int)$request->getUserVar('id') ?: null,
            'invitationKey' => $request->getUserVar('key') ?: null,
            'pageTitleDescription' => __('invitation.wizard.pageTitleDescription'),
        ]);
        $templateMgr->assign([
            'pageComponent' => 'PageOJS',
        ]);
        $templateMgr->display('invitation/acceptInvitation.tpl');
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
