<?php

/**
 * @file classes/invitation/invitations/handlers/reviewerAccess/ReviewerAccessInviteRedirectController.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerAccessInviteRedirectController
 *
 */

namespace PKP\invitation\invitations\reviewerAccess\handlers;

use APP\core\Request;
use APP\facades\Repo;
use APP\template\TemplateManager;
use Exception;
use PKP\core\PKPApplication;
use PKP\invitation\core\enums\InvitationAction;
use PKP\invitation\core\enums\InvitationStatus;
use PKP\invitation\core\enums\InvitationTypes;
use PKP\invitation\core\InvitationActionRedirectController;
use PKP\invitation\core\InvitationContextFactory;
use PKP\invitation\invitations\reviewerAccess\ReviewerAccessInvite;
use PKP\invitation\stepTypes\AcceptInvitationStep;

class ReviewerAccessInviteRedirectController extends InvitationActionRedirectController
{
    public function getInvitation(): \PKP\invitation\core\Invitation
    {
        return $this->invitation;
    }

    public function acceptHandle(Request $request): void
    {
        $templateMgr = TemplateManager::getManager($request);

        $templateMgr->assign('invitation', $this->getInvitation());
        $context = $request->getContext();
        $invitationModel = $this->getInvitation()->invitationModel->toArray();
        $user = $invitationModel['userId'] ? Repo::user()->get($invitationModel['userId']) : null;

        $invitationContext = InvitationContextFactory::make(InvitationTypes::INVITATION_REVIEWER_ACCESS_INVITE->value);
        $steps = new AcceptInvitationStep($invitationContext, $this->invitation, $context, $user);

        $templateMgr->setState([
            'steps' => $steps->getSteps(),
            'primaryLocale' => $context->getData('primaryLocale'),
            'pageTitle' => __('invitation.wizard.pageTitle'),
            'invitationId' => (int)$request->getUserVar('id') ?: null,
            'invitationKey' => $request->getUserVar('key') ?: null,
            'pageTitleDescription' => __('invitation.wizard.pageTitleDescription'),
        ]);
        $templateMgr->assign([
            'pageComponent' => 'Page',
        ]);
        $templateMgr->display('invitation/acceptInvitation.tpl');
    }

    public function declineHandle(Request $request): void
    {
        if ($this->invitation->getStatus() !== InvitationStatus::PENDING) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        $context = $request->getContext();

        $url = PKPApplication::get()->getDispatcher()->url(
            PKPApplication::get()->getRequest(),
            PKPApplication::ROUTE_PAGE,
            $context->getData('urlPath'),
            'login',
            null,
            null,
            [
            ]
        );

        $this->getInvitation()->decline();

        $request->redirectUrl($url);
    }

    public function preRedirectActions(InvitationAction $action): void
    {
        return;
    }
}
