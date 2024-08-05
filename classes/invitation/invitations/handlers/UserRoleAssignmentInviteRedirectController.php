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

namespace PKP\invitation\invitations\handlers;

use APP\core\Request;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;
use PKP\core\PKPApplication;
use PKP\invitation\core\enums\InvitationAction;
use PKP\invitation\core\enums\InvitationStatus;
use PKP\invitation\core\InvitationActionRedirectController;
use PKP\invitation\invitations\UserRoleAssignmentInvite;
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
        // if ($this->invitation->getStatus() !== InvitationStatus::DECLINED) {
        //     $request->getDispatcher()->handle404();
        // }

        // $user = Repo::user()->get($this->invitation->invitationModel->userId);

        // $notificationManager = new NotificationManager();
        // $notificationManager->createTrivialNotification($user->getId());

        // $url = PKPApplication::get()->getDispatcher()->url(
        //     PKPApplication::get()->getRequest(),
        //     PKPApplication::ROUTE_PAGE,
        //     null,
        //     'user',
        //     'profile',
        //     [
        //         'contact'
        //     ]
        // );

        // $request->redirectUrl($url);
    }

    public function preRedirectActions(InvitationAction $action)
    {
        return;
    }

    // public function index($args, $request)
    // {
    //     if (!$this->isAnnouncementsEnabled($request)) {
    //         $request->getDispatcher()->handle404();
    //     }

    //     $this->setupTemplate($request);

    //     $templateMgr = TemplateManager::getManager($request);
    //     $templateMgr->assign('announcementsIntroduction', $this->getAnnouncementsIntro($request));

    //     // TODO the announcements list should support pagination
    //     $collector = Repo::announcement()
    //         ->getCollector()
    //         ->filterByActive();

    //     if ($request->getContext()) {
    //         $collector->filterByContextIds([$request->getContext()->getId()]);
    //     } else {
    //         $collector->withSiteAnnouncements(Collector::SITE_ONLY);
    //     }

    //     $announcements = $collector->getMany();

    //     $templateMgr->assign('announcements', $announcements->toArray());
    //     $templateMgr->display('frontend/pages/announcements.tpl');
    // }
}
