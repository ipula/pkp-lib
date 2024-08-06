<?php

/**
 * @file classes/invitation/invitations/UserRoleAssignmentInvite.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserRoleAssignmentInvite
 *
 * @brief Assign Roles to User invitation
 */

namespace PKP\invitation\invitations;

use APP\core\Application;
use APP\facades\Repo;
use Exception;
use Illuminate\Mail\Mailable;
use PKP\core\Core;
use PKP\identity\Identity;
use PKP\invitation\core\contracts\IApiHandleable;
use PKP\invitation\core\CreateInvitationController;
use PKP\invitation\core\enums\InvitationStatus;
use PKP\invitation\core\Invitation;
use PKP\invitation\core\InvitationActionRedirectController;
use PKP\invitation\core\ReceiveInvitationController;
use PKP\invitation\core\traits\HasMailable;
use PKP\invitation\core\traits\ShouldValidate;
use PKP\invitation\invitations\handlers\api\UserRoleAssignmentCreateController;
use PKP\invitation\invitations\handlers\api\UserRoleAssignmentReceiveController;
use PKP\invitation\invitations\handlers\UserRoleAssignmentInviteRedirectController;
use PKP\invitation\models\InvitationModel;
use PKP\mail\mailables\UserRoleAssignmentInvitationNotify;
use PKP\security\Validation;
use PKP\userGroup\relationships\enums\UserUserGroupMastheadStatus;
use PKP\userGroup\relationships\UserUserGroup;

class UserRoleAssignmentInvite extends Invitation implements IApiHandleable
{
    use HasMailable;
    use ShouldValidate;

    public const INVITATION_TYPE = 'userRoleAssignment';

    protected array $notAccessibleAfterInvite = [
        'userGroupsToAdd',
        'userGroupsToRemove',
    ];

    public array $propertyType = [
        'userGroupsToAdd' => UserUserGroup::class,
        'userGroupsToRemove' => UserUserGroup::class,
    ];

    protected array $notAccessibleBeforeInvite = [
        // 'orcid',
    ];

    public ?string $orcid = null;
    public ?string $givenName = null;
    public ?string $familyName = null;
    public ?string $affiliation = null;
    public ?string $country = null;

    public ?string $username = null;
    public ?string $password = null;

    public ?string $emailSubject = null;
    public ?string $emailBody = null;
    public ?bool $existingUser = null;

    /**
     * @var UserUserGroup[]
     */
    public array $userGroupsToAdd = [];

    /**
     * @var UserUserGroup[]
     */
    public array $userGroupsToRemove = [];

    public static function getType(): string
    {
        return self::INVITATION_TYPE;
    }

    public function getNotAccessibleAfterInvite(): array
    {
        return array_merge(parent::getNotAccessibleAfterInvite(), $this->notAccessibleAfterInvite);
    }

    public function getNotAccessibleBeforeInvite(): array
    {
        return array_merge(parent::getNotAccessibleBeforeInvite(), $this->notAccessibleBeforeInvite);
    }

    public function getMailable(): Mailable
    {
        $contextDao = Application::getContextDAO();
        $context = $contextDao->getById($this->invitationModel->contextId);
        $locale = $context->getPrimaryLocale();

        // Define the Mailable
        $mailable = new UserRoleAssignmentInvitationNotify($context, $this);
        $mailable->setData($locale);

        // Set the email send data
        $emailTemplate = Repo::emailTemplate()->getByKey($context->getId(), $mailable::getEmailTemplateKey());

        $inviter = $this->getInviter();

        $reciever = $this->getMailableReceiver($locale);

        $mailable
            ->sender($inviter)
            ->recipients([$reciever])
            ->subject($emailTemplate->getLocalizedData('subject', $locale))
            ->body($emailTemplate->getLocalizedData('body', $locale));

        $this->setMailable($mailable);

        return $this->mailable;
    }

    public function getMailableReceiver(?string $locale = null): Identity 
    {
        $locale = $this->getUsedLocale($locale);

        $receiver = parent::getMailableReceiver($locale);

        $receiver->setFamilyName($this->familyName, $locale);
        $receiver->setGivenName($this->givenName, $locale);

        return $receiver;
    }

    protected function preInviteActions(): void
    {
        // Check if everything is in order regarding the properties
        if (empty($this->userGroupsToAdd) && empty($this->userGroupsToRemove)) {
            throw new Exception('The invitation can not be dispatched because you have not defined any user group changes');
        }

        // Invalidate any other related invitation
        InvitationModel::byStatus(InvitationStatus::PENDING)
            ->byType(self::INVITATION_TYPE)
            ->when(isset($this->invitationModel->userId), function ($query) {
                return $query->byUserId($this->invitationModel->userId);
            })
            ->when(!isset($this->invitationModel->userId) && $this->invitationModel->email, function ($query) {
                return $query->byEmail($this->invitationModel->email);
            })
            ->byContextId($this->invitationModel->contextId)
            ->delete();
    }

    public function finalize(): void
    {
        $user = null;

        if ($this->invitationModel->userId) {
            $user = Repo::user()->get($this->invitationModel->userId);

            if (!isset($user)) {
                throw new Exception('The user does not exist');
            }
        }
        else if ($this->invitationModel->email) {
            $user = Repo::user()->getByEmail($this->invitationModel->email);

            if (!isset($user)) {
                $user = Repo::user()->newDataObject();

                $user->setUsername($this->username);

                // Set the base user fields (name, etc.)
                $user->setGivenName($this->givenName, null);
                $user->setFamilyName($this->familyName, null);
                $user->setEmail($this->invitationModel->email);
                $user->setCountry($this->country);
                $user->setAffiliation($this->affiliation, null);

                $user->setOrcid($this->orcid);

                $user->setDateRegistered(Core::getCurrentDate());
                $user->setInlineHelp(1); // default new users to having inline help visible.
                $user->setPassword(Validation::encryptCredentials($this->username, $this->password));

                Repo::user()->add($user);
            }
        }

        foreach ($this->userGroupsToRemove as $userUserGroup) {
            Repo::userGroup()-> deleteAssignmentsByUserId(
                $user->getId(),
                $userUserGroup->userGroupId
            );
        }

        foreach ($this->userGroupsToAdd as $userUserGroup) {
            Repo::userGroup()->assignUserToGroup(
                $user->getId(),
                $userUserGroup->userGroupId,
                $userUserGroup->dateStart,
                $userUserGroup->dateEnd,
                isset($userUserGroup->masthead) && $userUserGroup->masthead 
                    ? UserUserGroupMastheadStatus::STATUS_ON 
                    : UserUserGroupMastheadStatus::STATUS_OFF
            );
        }

        $this->invitationModel->markAs(InvitationStatus::ACCEPTED);
    }

    public function getInvitationActionRedirectController(): ?InvitationActionRedirectController
    {
        return new UserRoleAssignmentInviteRedirectController($this);
    }

    /**
     * @inheritDoc
     */
    public function getCreateInvitationController(Invitation $invitation): CreateInvitationController 
    {
        return new UserRoleAssignmentCreateController($invitation);
    }
    
    /**
     * @inheritDoc
     */
    public function getReceiveInvitationController(Invitation $invitation): ReceiveInvitationController 
    {
        return new UserRoleAssignmentReceiveController($invitation);
    }

    public function updateUserGroupArray(array &$userGroupArray, array $userUserGroupsToAdd): void
    {
        if (is_array($userUserGroupsToAdd) && !empty($userUserGroupsToAdd)) {
            $userGroupArray = [];
            foreach ($userUserGroupsToAdd as $userGroupData) {
                if ($userGroupData['userGroup']) {
                    $newUserUserGroup = new UserUserGroup([
                        'userGroupId' => $userGroupData['userGroup'],
                        'dateStart' => $userGroupData['dateStart'],
                        'dateEnd' => $userGroupData['dateEnd'],
                        'masthead' => $userGroupData['masthead']
                    ]);

                    $this->addUserGroup($userGroupArray, $newUserUserGroup);
                }
            }
        }
    }

    private function addUserGroup(array &$userGroupArray, UserUserGroup $userUserGroup)
    {
        $userGroupArray[] = $userUserGroup;
    }

    /**
     * @inheritDoc
     */
    public function validate(): bool
    {
        // Custom rules
        if (isset($this->userGroupsToAdd)) {
            if (empty($this->userGroupsToAdd)) {
                $this->addError('userGroupsToAdd', 'User groups must be defined');
            }
        }

        return $this->isValid();
    }
}
