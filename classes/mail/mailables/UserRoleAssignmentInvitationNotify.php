<?php

/**
 * @file classes/mail/mailables/UserRoleAssignmentInvitationNotify.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserRoleAssignmentInvitationNotify
 *
 * @brief Email sent when a user is invited to participate into specific roles
 */

namespace PKP\mail\mailables;

use APP\core\Application;
use APP\facades\Repo;
use PKP\context\Context;
use PKP\core\Core;
use PKP\facades\Locale;
use PKP\identity\Identity;
use PKP\invitation\core\enums\InvitationAction;
use PKP\invitation\invitations\UserRoleAssignmentInvite;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;
use PKP\security\Role;
use PKP\userGroup\relationships\UserUserGroup;

class UserRoleAssignmentInvitationNotify extends Mailable
{
    use Recipient;
    use Configurable;
    use Sender;

    protected static ?string $name = 'mailable.userRoleAssignmentInvitationNotify.name';
    protected static ?string $description = 'mailable.userRoleAssignmentInvitationNotify.description';
    protected static ?string $emailTemplateKey = 'USER_ROLE_ASSIGNMENT_INVITATION';
    protected static array $groupIds = [self::GROUP_OTHER];
    protected static array $fromRoleIds = [
        self::FROM_SYSTEM,
    ];
    protected static array $toRoleIds = [
        Role::ROLE_ID_SUB_EDITOR,
        Role::ROLE_ID_ASSISTANT,
        Role::ROLE_ID_AUTHOR,
        Role::ROLE_ID_READER,
        Role::ROLE_ID_REVIEWER,
        Role::ROLE_ID_SUBSCRIPTION_MANAGER,
    ];

    protected static string $recipientName = 'recipientName';
    protected static string $inviteeName = 'inviteeName';
    protected static string $inviteeRole = 'inviteeRole';
    protected static string $rolesAdded = 'rolesAdded';
    protected static string $rolesAddedDetails = 'rolesAddedDetails';
    protected static string $rolesRemoved = 'rolesRemoved';
    protected static string $existingRoles = 'existingRoles';
    protected static string $acceptUrl = 'acceptUrl';
    protected static string $declineUrl = 'declineUrl';

    private UserRoleAssignmentInvite $invitation;

    public function __construct(Context $context, UserRoleAssignmentInvite $invitation)
    {
        parent::__construct(array_slice(func_get_args(), 0, -1));

        $this->invitation = $invitation;
    }

    /**
     * Add description to a new email template variables
     */
    public static function getDataDescriptions(): array
    {
        $variables = parent::getDataDescriptions();

        $variables[static::$recipientName] = __('emailTemplate.variable.invitation.recipientName');
        $variables[static::$inviteeName] = __('emailTemplate.variable.invitation.inviteeName');
        $variables[static::$inviteeRole] = __('emailTemplate.variable.invitation.inviteeRole');
        $variables[static::$rolesAdded] = __('emailTemplate.variable.invitation.rolesAdded');
        $variables[static::$rolesAddedDetails] = __('emailTemplate.variable.invitation.rolesAddedDetails');
        $variables[static::$rolesRemoved] = __('emailTemplate.variable.invitation.rolesRemoved');
        $variables[static::$existingRoles] = __('emailTemplate.variable.invitation.existingRoles');
        $variables[static::$acceptUrl] = __('emailTemplate.variable.invitation.acceptUrl');
        $variables[static::$declineUrl] = __('emailTemplate.variable.invitation.declineUrl');
        
        return $variables;
    }

    /**
     * Set localized email template variables
     */
    public function setData(?string $locale = null): void
    {
        parent::setData($locale);
        if (is_null($locale)) {
            $locale = Locale::getLocale();
        }

        // Invitation User
        $sendIdentity = new Identity();
        $user = null;
        if ($this->invitation->invitationModel->userId) {
            $user = Repo::user()->get($this->invitation->invitationModel->userId);
            
            $sendIdentity->setFamilyName($user->getFamilyName($locale), $locale);
            $sendIdentity->setGivenName($user->getGivenName($locale), $locale);
            $sendIdentity->setEmail($user->getEmail());
        } else {
            $sendIdentity->setFamilyName($this->invitation->familyName, $locale);
            $sendIdentity->setGivenName($this->invitation->givenName, $locale);
            $sendIdentity->setEmail($this->invitation->invitationModel->email);
        }

        // Invitee
        $request = Application::get()->getRequest();
        $invitee = $request->getUser();

        $contextDao = Application::getContextDAO();
        $context = $contextDao->getById($this->invitation->invitationModel->contextId);

        // Roles Added
        $userGroupsAdded = '';
        $userGroupsAddedDetails = '<p><h2>Newly assigned roles</h2></p>';

        $count = 1;
        foreach ($this->invitation->userGroupsToAdd as $userGroupData) {
            $userGroup = Repo::userGroup()->get($userGroupData['userGroup']);
            $userGroupsAdded = $userGroupsAdded . ',' . $userGroup->getName($locale);

            $userGroupSection ='<div class="section">
                <div class="section-number">' . $count . '.</div>
                <div class="section-content">
                    <h2>' . $userGroup->getName($locale) . '</h2>
                    <p>Starting from ' . $userGroupData['dateStart'] . '</p>';

            if (isset($userGroupData['dateEnd'])) {
                $userGroupSection = $userGroupSection . '<p>Ending at ' . $userGroupData['dateEnd'] . ' </p>';
            }

            if (isset($userGroupData['masthead']) && $userGroupData['masthead']) {
                $userGroupSection = $userGroupSection . '<p>Your name will appear in the journal’s masthead as a ' . $userGroup->getName($locale) . '</p>';
            } else {
                $userGroupSection = $userGroupSection . '<p>Your name will not appear in ' . $context->getName($locale) . ' masthead</p>';
            }

            $userGroupSection = $userGroupSection . '</div></div>';

            $userGroupsAddedDetails = $userGroupsAddedDetails . $userGroupSection;

            $count++;
        }

        // Roles Removed
        $userGroupsremoved = '';
        foreach ($this->invitation->userGroupsToRemove as $userGroup) {
            $userGroupsremoved = $userGroupsremoved . ',' . $userGroup['userGroup'];
        }

        // Existing Roles
        $existingUserGroups = '';
        $user = Repo::user()->get(1);
        if (isset($user)) {
            $existingUserGroups = '<p><h2>Already assigned roles</h2></p>';

            $userGroups = Repo::userGroup()->getCollector()
                ->filterByContextIds([$this->invitation->invitationModel->contextId])
                ->filterByUserIds([$user->getId()])
                ->getMany();
            
            foreach ($userGroups as $userGroup) {
                $userUserGroups = UserUserGroup::withUserId($user->getId())
                    ->withUserGroupId($userGroup->getId())
                    ->get();
                
                $count = 1;
                foreach ($userUserGroups as $userUserGroup) {
                    $userGroupSection ='<div class="section">
                        <div class="section-number">' . $count . '.</div>
                        <div class="section-content">
                            <h2>' . $userGroup->getName($locale) . '</h2>
                            <p>Starting from ' . $userUserGroup->dateStart . '</p>';

                    if (isset($userUserGroup->dateEnd)) {
                        $userGroupSection = $userGroupSection . '<p>Ending at ' . $userUserGroup->dateEnd . ' </p>';
                    }

                    if (isset($userUserGroup->masthead) && $userUserGroup->masthead) {
                        $userGroupSection = $userGroupSection . '<p>Your name will appear in ' . $context->getName($locale) . ' masthead as a ' . $userGroup->getName($locale) . '</p>';
                    } else {
                        $userGroupSection = $userGroupSection . '<p>Your name will not appear in ' . $context->getName($locale) . ' masthead</p>';
                    }

                    $userGroupSection = $userGroupSection . '</div></div>';

                    $existingUserGroups = $existingUserGroups . $userGroupSection;

                    $count++;
                }
            }
        }

        $targetPath = Core::getBaseDir() . '/lib/pkp/styles/mailables/style.css';
        $emailTemplateStyle = file_get_contents($targetPath);

        // Set view data for the template
        $this->viewData = array_merge(
            $this->viewData,
            [
                static::$recipientName => $sendIdentity->getFullName(),
                static::$inviteeName => $invitee->getFullName(),
                static::$acceptUrl => $this->invitation->getActionURL(InvitationAction::ACCEPT),
                static::$declineUrl => $this->invitation->getActionURL(InvitationAction::DECLINE),
                static::$rolesAdded => $userGroupsAdded,
                static::$rolesAddedDetails => $userGroupsAddedDetails,
                static::$rolesRemoved => $userGroupsremoved,
                static::$existingRoles => $existingUserGroups,
                'emailTemplateStyle' => $emailTemplateStyle,
                
            ]
        );
    }
}
