<?php
/**
 * @file classes/invitation/stepType/SendInvitationStep.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SendInvitationStep
 *
 * @brief create accept invitation steps.
 */
namespace PKP\invitation\stepTypes;

use APP\core\Application;
use Exception;
use PKP\components\forms\invitation\UserDetailsForm;
use PKP\context\Context;
use PKP\facades\Repo;
use PKP\invitation\core\Invitation;
use PKP\invitation\sections\Email;
use PKP\invitation\sections\Form;
use PKP\invitation\sections\Sections;
use PKP\invitation\steps\Step;
use PKP\mail\mailables\UserRoleAssignmentInvitationNotify;
use stdClass;

class SendInvitationStep extends InvitationStepTypes
{
    /**
     * get send invitation steps
     *
     * @throws Exception
     */
    public function getSteps(?Invitation $invitation, Context $context): array
    {
        $steps = [];
        if(!$invitation) {
            $steps[] = $this->invitationSearchUser();
        }
        $steps[] = $this->invitationDetailsForm($context);
        $steps[] = $this->invitationInvitedEmail($context);
        return $steps;
    }

    /**
     * create search user section
     */
    private function invitationSearchUser(): stdClass
    {
        $sections = new Sections(
            'searchUserForm',
            __('userInvitation.searchUser.stepName'),
            __('userInvitation.searchUser.stepDescription'),
            'form',
            'UserInvitationSearchFormStep',
            true
        );
        $sections->addSection(
            null,
            [
                'validateFields' => []
            ]
        );
        $step = new Step(
            'searchUser',
            __('userInvitation.searchUser.stepName'),
            __('userInvitation.searchUser.stepDescription'),
            __('userInvitation.searchUser.stepLabel'),
            __('userInvitation.searchUser.nextButtonLabel'),
            'emptySection',
            true
        );
        $step->addSectionToStep($sections->getState());
        return $step->getState();
    }

    /**
     * create user details form section
     *
     * @throws Exception
     */
    private function invitationDetailsForm(Context $context): stdClass
    {
        $localeNames = $context->getSupportedFormLocaleNames();
        $locales = [];
        foreach ($localeNames as $key => $name) {
            $locales[] = [
                'key' => $key,
                'label' => $name,
            ];
        }
        $sections = new Sections(
            'userDetails',
            __('userInvitation.enterDetails.stepName'),
            __('userInvitation.enterDetails.stepDescription'),
            'form',
            'UserInvitationDetailsFormStep'
        );
        $sections->addSection(
            new Form(
                'userDetails',
                __('userInvitation.enterDetails.stepName'),
                __('userInvitation.enterDetails.stepDescription'),
                new UserDetailsForm('post', $locales, $context),
            ),
            [
                'validateFields' => [
                    'orcid',
                    'email',
                    'givenName',
                    'familyName',
                    'userGroupsToAdd',
                ],
                'userGroups' => $this->getAllUserGroup($context)
            ]
        );
        $step = new Step(
            'userDetails',
            __('userInvitation.enterDetails.stepName'),
            __('userInvitation.enterDetails.stepDescription'),
            __('userInvitation.enterDetails.stepLabel'),
            __('userInvitation.enterDetails.nextButtonLabel'),
            'form'
        );
        $step->addSectionToStep($sections->getState());
        return $step->getState();
    }

    /**
     * create email composer for send invite
     *
     * @throws Exception
     */
    private function invitationInvitedEmail(Context $context): stdClass
    {
        $sections = new Sections(
            'userInvitedEmail',
            __('userInvitation.sendMail.stepLabel'),
            __('userInvitation.sendMail.stepName'),
            'email',
            'UserInvitationEmailComposerStep'
        );
        $fakeInvitation = $this->getFakeInvitation();
        $mailable = new UserRoleAssignmentInvitationNotify($context, $fakeInvitation);
        $sections->addSection(
            new Email(
                'userInvited',
                __('userInvitation.sendMail.stepName'),
                __('userInvitation.sendMail.stepDescription'),
                [],
                $mailable
                    ->sender(Application::get()->getRequest()->getUser())
                    ->cc('')
                    ->bcc(''),
                $context->getSupportedFormLocales(),
            ),
            [
                'validateFields' => ['emailComposer']
            ]
        );
        $step = new Step(
            'userInvited',
            __('userInvitation.sendMail.stepName'),
            __('userInvitation.sendMail.stepDescription'),
            __('userInvitation.sendMail.stepLabel'),
            __('userInvitation.sendMail.nextButtonLabel'),
            'email'
        );
        $step->addSectionToStep($sections->getState());
        return $step->getState();
    }

    /**
     * get all user groups
     */
    private function getAllUserGroup(Context $context): array
    {
        $allUserGroups = [];
        $userGroups = Repo::userGroup()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->getMany();
        foreach ($userGroups as $userGroup) {
            $allUserGroups[] = [
                'value' => (int) $userGroup->getId(),
                'label' => $userGroup->getLocalizedName(),
                'disabled' => false
            ];
        }
        return $allUserGroups;
    }
}