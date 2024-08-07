<?php

/**
 * @file pages/invitation/InvitationHandler.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InvitationHandler
 *
 * @ingroup pages_invitation
 *
 * @brief Handles page requests for invitations op
 */

namespace PKP\pages\invitation;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\template\TemplateManager;
use PKP\core\PKPApplication;
use PKP\invitation\core\enums\InvitationAction;
use PKP\invitation\core\Invitation;
use PKP\invitation\stepTypes\SendInvitationStep;

class InvitationHandler extends Handler
{
    public $_isBackendPage = true;
    public const REPLY_PAGE = 'invitation';
    public const REPLY_OP_ACCEPT = 'accept';
    public const REPLY_OP_DECLINE = 'decline';

    /**
     * Accept invitation handler
     */
    public function accept(array $args, Request $request): void
    {
        $this->setupTemplate($request);
        $invitation = $this->getInvitationByKey($request);
        $invitationHandler = $invitation->getInvitationActionRedirectController();
        $invitationHandler->preRedirectActions(InvitationAction::ACCEPT);
        $invitationHandler->acceptHandle($request);
    }

    /**
     * Decline invitation handler
     */
    public function decline(array $args, Request $request): void
    {
        $invitation = $this->getInvitationByKey($request);
        $invitationHandler = $invitation->getInvitationActionRedirectController();
        $invitationHandler->preRedirectActions(InvitationAction::DECLINE);
        $invitationHandler->declineHandle($request);
    }

    private function getInvitationByKey(Request $request): Invitation
    {
        $key = $request->getUserVar('key') ?: null;
        $id = $request->getUserVar('id') ?: null;

        $invitation = Repo::invitation()
            ->getByIdAndKey($id, $key);

        if (is_null($invitation)) {
            $request->getDispatcher()->handle404();
        }
        return $invitation;
    }

    private function getInvitationById(Request $request, $id): Invitation
    {
        $invitation = Repo::invitation()
            ->getById($id);

        if (is_null($invitation)) {
            $request->getDispatcher()->handle404();
        }
        return $invitation;
    }

    public static function getActionUrl(InvitationAction $action, Invitation $invitation): ?string
    {
        $invitationId = $invitation->getId();
        $invitationKey = $invitation->getKey();

        if (!isset($invitationId) || !isset($invitationKey)) {
            return null;
        }

        $request = Application::get()->getRequest();
        $contextPath = $request->getContext() ? $request->getContext()->getPath() : null;

        return $request->getDispatcher()
            ->url(
                $request,
                Application::ROUTE_PAGE,
                $contextPath,
                static::REPLY_PAGE,
                $action->value,
                null,
                [
                    'id' => $invitationId,
                    'key' => $invitationKey,
                ]
            );
    }

    public function invite($args, $request): void
    {
        $invitationId = null;
        $invitationPayload = [
            'userId' => null,
            'email' => '',
            'orcid' => '',
            'givenName' => '',
            'familyName' => '',
            'affiliation' => '',
            'country' => '',
            'orcidValidation' => false,
            'userGroupsToAdd' => [
                [
                    'userGroupId' => null,
                    'dateStart' => null,
                    'dateEnd' => null,
                    'masthead' => null,
                ]
            ],
            'currentUserGroups' => [],
            'userGroupsToRemove' => [],
            'emailComposer' => [
                'body' => '',
                'subject' => '',
            ]
        ];
        $invitation = null;
        if(!empty($args)) {
            $invitation = $this->getInvitationById($request, $args[0]);
            $invitationId = $invitation->invitationModel->invitation_id;
            $invitationPayload['userId'] = $invitation->invitationModel->userId;
            $invitationPayload['email'] = $invitation->invitationModel->email;
            $invitationPayload['orcid'] = $invitation->orcid;
            $invitationPayload['givenName'] = $invitation->givenName;
            $invitationPayload['familyName'] = $invitation->familyName;
            $invitationPayload['affiliation'] = $invitation->affiliation;
            $invitationPayload['country'] = $invitation->country;
            $invitationPayload['userGroupsToAdd'] = $invitation->userGroupsToAdd;
            $invitationPayload['currentUserGroups'] = !$invitation->currentUserGroups ? [] : $invitation->currentUserGroups;
            $invitationPayload['userGroupsToRemove'] = $invitation->userGroupsToRemove;
            $invitationPayload['emailComposer'] = $invitation->emailComposer;
        }
        $templateMgr = TemplateManager::getManager($request);
        $breadcrumbs = $templateMgr->getTemplateVars('breadcrumbs');
        $this->setupTemplate($request);
        $context = $request->getContext();
        $breadcrumbs[] = [
            'id' => 'contexts',
            'name' => __('navigation.access'),
            'url' => $request
                ->getDispatcher()
                ->url(
                    $request,
                    PKPApplication::ROUTE_PAGE,
                    $request->getContext()->getPath(),
                    'management',
                    'settings',
                )
        ];
        $breadcrumbs[] = [
            'id' => 'invitationWizard',
            'name' => __('invitation.wizard.pageTitle'),
        ];
        $steps = new SendInvitationStep();
        $templateMgr->setState([
            'steps' => $steps->getSteps($invitation, $context),
            'emailTemplatesApiUrl' => $request
                ->getDispatcher()
                ->url(
                    $request,
                    Application::ROUTE_API,
                    $context->getData('urlPath'),
                    'emailTemplates'
                ),
            'primaryLocale' => $context->getData('primaryLocale'),
            'invitationType' => 'userRoleAssignment',
            'invitationId' => $invitationId,
            'invitationPayload' => $invitationPayload,
            'pageTitle' => __('invitation.wizard.pageTitle'),
            'pageTitleDescription' => __('invitation.wizard.pageTitleDescription'),
        ]);
        $templateMgr->assign([
            'pageComponent' => 'PageOJS',
            'breadcrumbs' => $breadcrumbs,
            'pageWidth' => TemplateManager::PAGE_WIDTH_FULL,
        ]);
        $templateMgr->display('/invitation/userInvitation.tpl');
    }
}
