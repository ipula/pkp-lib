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
use Exception;
use PKP\components\forms\invitation\AcceptUserDetailsForm;
use PKP\core\PKPApplication;
use PKP\invitation\core\enums\InvitationAction;
use PKP\invitation\core\Invitation;
use PKP\invitation\types\SendInvitationStep;

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
        $invitation = $this->getInvitationByKey($request);
        //        $invitationHandler = $invitation->getInvitationActionRedirectController();
        //        $invitationHandler->preRedirectActions(InvitationAction::ACCEPT);
        //        $invitationHandler->acceptHandle($request);
        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);
        $context = $request->getContext();
        $steps = $this->getAcceptSteps($request, $invitation, $context);
        $templateMgr->setState([
            'steps' => $steps,
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

    private function getInvitationByKey(Request $request)
    {
        $key = $request->getUserVar('key') ?: null;
        $id = $request->getUserVar('id') ?: null;

        $invitation = Repo::invitation()
            ->getByIdAndKey($id, $key);

        //        if (is_null($invitation)) {
        //            $request->getDispatcher()->handle404();
        //        }

        return null;
    }

    public static function getActionUrl(InvitationAction $action, Invitation $invitation): string
    {
        $invitationId = $invitation->getId();
        $invitationKey = $invitation->getKey();

        if (!isset($invitationId) || !isset($invitationKey)) {
            throw new Exception();
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

    public function send($args, $request): void
    {
        $templateMgr = TemplateManager::getManager($request);
        $breadcrumbs = $templateMgr->getTemplateVars('breadcrumbs');
        $this->setupTemplate($request);
        $context = $request->getContext();

        //        $breadcrumbs[] = [
        //            'id' => 'contexts',
        //            'name' => __('invitation.userAndRoles'),
        //            'url' => $request
        //                ->getDispatcher()
        //                ->url(
        //                    $request,
        //                    PKPApplication::ROUTE_PAGE,
        //                    $request->getContext()->getPath(),
        //                    'management',
        //                    'settings',
        //                    'access'
        //                )
        //        ];
        //        $breadcrumbs[] = [
        //            'id' => 'contexts',
        //            'name' => __('invitation.users'),
        //            'url' => $request
        //                ->getDispatcher()
        //                ->url(
        //                    $request,
        //                    PKPApplication::ROUTE_PAGE,
        //                    $request->getContext()->getPath(),
        //                    'management',
        //                    'settings',
        //                    'access'
        //                )
        //        ];
        $breadcrumbs[] = [
            'id' => 'wizard',
            'name' => __('manager.settings.wizard'),
        ];
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
                    'userGroup' => null,
                    'dateStart' => null,
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
        $steps = new SendInvitationStep();
        $templateMgr->setState([
            'steps' => $steps->getSteps(null, $context),
            'emailTemplatesApiUrl' => $request
                ->getDispatcher()
                ->url(
                    $request,
                    Application::ROUTE_API,
                    $context->getData('urlPath'),
                    'emailTemplates'
                ),
            'primaryLocale' => $context->getData('primaryLocale'),
            'invitationType' => 'RoleUpdateForNewUser',
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

    /**
     * get user account create steps
     */
    protected function getAcceptSteps(Request $request, $invitation, $context): array
    {
        $apiUrl = $this->getAcceptInvitationApiUrl($request, $invitation);

        $steps = [];
        //        if($invitation->userId) {
        //            $steps[] = $this->verifyOrcid();
        //            $steps[] = $this->userCreateReview($invitation, $context);
        //        } else {
        $steps[] = $this->verifyOrcid();
        $steps[] = $this->userCreate();
        //            $steps[] = $this->getAcceptUserDetailsForm($request, $apiUrl, $invitation);
        $steps[] = $this->userCreateReview($invitation, $context);
        //        }


        return $steps;
    }
    /**
     * Get the state for the user orcid verification
     */
    protected function verifyOrcid(): array
    {
        $sections = [
            [
                'id' => 'userVerifyOrcid',
                'sectionComponent' => 'AcceptInvitationVerifyOrcid'
            ]
        ];
        return [
            'id' => 'verifyOrcid',
            'name' => __('invitation.verifyOrcid'),
            'reviewName' => '',
            'stepName' => __('invitation.verifyOrcidStep'),
            'stepButtonName' => __('invitation.verifyOrcidStep.button'),
            'type' => 'popup',
            'description' => __('invitation.verifyOrcidDescription'),
            'sections' => $sections,
        ];
    }

    /**
     * create username and password for ojs account
     */
    protected function userCreate(): array
    {
        $sections = [
            [
                'id' => 'userCreateForm',
                'sectionComponent' => 'AcceptInvitationCreateUserAccount'
            ]
        ];
        return [
            'id' => 'userCreate',
            'name' => __('invitation.userCreate'),
            'reviewName' => __('invitation.userCreateReviewName'),
            'stepName' => __('invitation.userCreateStep'),
            'stepButtonName' => __('invitation.userCreateStep.button'),
            'type' => 'form',
            'description' => __('invitation.userCreateDescription'),
            'sections' => $sections,
            'reviewData' => []
        ];
    }

    protected function getAcceptUserDetailsForm(Request $request, string $apiUrl, $invitation): array
    {
        $localeNames = $request->getContext()->getSupportedFormLocaleNames();
        $locales = [];
        foreach ($localeNames as $key => $name) {
            $locales[] = [
                'key' => $key,
                'label' => $name,
            ];
        }
        $contactForm = new AcceptUserDetailsForm($apiUrl, $locales);
        $sections = [
            [
                'id' => 'userCreateDetailsForm',
                'type' => 'form',
                'description' => $request->getContext()->getLocalizedData('detailsHelp'),
                'form' => $contactForm->getConfig(),
                'sectionComponent' => 'AcceptInvitationCreateUserForms'
            ]
        ];

        return [
            'id' => 'userDetails',
            'name' => __('invitation.userCreateDetails'),
            'reviewName' => __('invitation.userCreateDetailsReviewName'),
            'stepName' => __('invitation.userCreateDetailStep'),
            'stepButtonName' => __('invitation.userCreateDetailStep.button'),
            'type' => 'form',
            'description' => __('invitation.userCreateDetailsDescription'),
            'sections' => $sections,
        ];
    }

    /**
     * create review all steps for create ojs account
     */
    protected function userCreateReview($invitation, $context): array
    {
        $rows = [];
        //        foreach (json_decode($invitation->roles) as $role) {
        //            $row = [
        //                'user_group_id' => $role->user_group_id,
        //                'user_group_name' => $this->getUserGroup($role->user_group_id)->getName($context->getData('primaryLocale')),
        //                'start_date' => $role->start_date,
        //                'end_date' => $role->end_date
        //            ];
        //            $rows[] = $row;
        //        }
        $sections = [
            [
                'id' => 'userCreateRoles',
                'sectionComponent' => 'AcceptInvitationReview',
                'type' => 'table',
                'description' => '',
                'rows' => $rows
            ]
        ];
        return [
            'id' => 'userCreateReview',
            'name' => __('invitation.userCreateReview'),
            'reviewName' => 'Roles',
            'stepName' => __('invitation.userCreateReviewStep'),
            'stepButtonName' => __('invitation.userCreateReviewStep.button'),
            'type' => 'review',
            'description' => __('invitation.userCreateReviewDescription'),
            'sections' => $sections,
        ];
    }

    /**
     * Get the url to the create user API endpoint
     * or if user already in the system get accept invitation
     * API endpoint
     */
    protected function getAcceptInvitationApiUrl(Request $request, $invitation): string
    {
        return $request
            ->getDispatcher()
            ->url(
                $request,
                PKPApplication::ROUTE_API,
                $request->getContext()->getPath(),
                'invitations/accept'
            );
    }

    /**
     * get user group by id
     */

    private function getUserGroup($userGroupId)
    {
        return Repo::userGroup()->get($userGroupId);
    }
}
