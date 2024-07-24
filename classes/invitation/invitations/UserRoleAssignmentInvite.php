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

class UserRoleAssignmentInvite extends Invitation implements IApiHandleable
{
    use HasMailable;
    use ShouldValidate;

    public const INVITATION_TYPE = 'userRoleAssignment';

    protected array $notAccessibleAfterInvite = [
        'userGroupsToAdd',
        'userGroupsToRemove',
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

    public array $userGroupsToAdd = [];
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
        $sendIdentity = new Identity();
        if ($this->invitationModel->userId) {
            $user = Repo::user()->get($this->invitationModel->userId);
            
            $sendIdentity->setFamilyName($user->getFamilyName(null), null);
            $sendIdentity->setGivenName($user->getGivenName(null), null);
            $sendIdentity->setEmail($user->getEmail());
        } else {
            $sendIdentity->setFamilyName($this->familyName, null);
            $sendIdentity->setGivenName($this->givenName, null);
            $sendIdentity->setEmail($this->invitationModel->email);
        }

        $request = Application::get()->getRequest();
        $contextDao = Application::getContextDAO();
        $context = $contextDao->getById($this->invitationModel->contextId);

        $mailable = new UserRoleAssignmentInvitationNotify($context, $this);
        $mailable->recipients([$sendIdentity]);
        $mailable->sender($request->getUser());

        $site = $request->getSite();
        $sitePrimaryLocale = $site->getPrimaryLocale();

        $emailTemplate = Repo::emailTemplate()->getByKey(Application::CONTEXT_ID_NONE, $mailable::getEmailTemplateKey());

        if (!isset($this->emailSubject)) {
            $this->emailSubject = $emailTemplate->getLocalizedData('subject', $sitePrimaryLocale);
        }

        if (!isset($this->emailBody)) {
            $this->emailBody = $emailTemplate->getLocalizedData('body', $sitePrimaryLocale);
        }

        $mailable->subject($this->emailSubject)
            ->body($this->emailBody);

        $mailable->setData($sitePrimaryLocale);

        $this->setMailable($mailable);

        return $this->mailable;
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

        // foreach($pendingInvitations as $pendingInvitation) {
        //     $pendingInvitation->markAs(InvitationStatus::DECLINED);
        // }
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

        foreach ($this->userGroupsToRemove as $userGroupData) {
            Repo::userGroup()-> deleteAssignmentsByUserId(
                $user->getId(),
                $userGroupData['userGroup']
            );
        }

        foreach ($this->userGroupsToAdd as $userGroupData) {
            Repo::userGroup()->assignUserToGroup(
                $user->getId(),
                $userGroupData['userGroup'],
                $userGroupData['dateStart'],
                $userGroupData['dateEnd'],
                isset($userGroupData['masthead']) && $userGroupData['masthead'] 
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

    public function updateUserGroupArray(array &$userGroupArray, array $userGroupsToAdd)
    {
        if (is_array($userGroupsToAdd) && !empty($userGroupsToAdd)) {
            foreach ($userGroupsToAdd as $group) {
                if (isset($group['userGroup'])) {
                    $this->removeUserGroup($userGroupArray, $group['userGroup']);
                    $this->addUserGroup($userGroupArray, $group);
                }
            }
        }
    }

    private function addUserGroup(array &$userGroupArray, array $userGroup)
    {
        // $userGroup['userGroupObject'] = Repo::userGroup()->get($userGroup['userGroup']);
        $userGroupArray[] = $userGroup;
    }

    private function removeUserGroup(array &$userGroupArray, int $userGroupId)
    {
        $userGroupArray = array_filter($userGroupArray, function($group) use ($userGroupId) {
            return $group['userGroup'] != $userGroupId;
        });

        // Re-index the array to maintain a consistent array structure
        $userGroupArray = array_values($userGroupArray);
    }

    // public function toJsonArray(): array
    // {
    //     $data = [
    //         'userId' => $this->invitationModel->userId,
    //         'email' => $this->invitationModel->email,
    //         'payload' => $this->invitationModel->paylod,
    //         'orcid' => $this->orcid,
    //         'givenName' => $this->givenName,
    //         'familyName' => $this->familyName,
    //         'affiliationName' => $this->affiliation,
    //         'country' => $this->country,
    //         'emailSubject' => $this->emailSubject,
    //         'emailBody' => $this->emailBody,
    //         'userGroupsToAdd' => $this->userGroupsToAdd,
    //         'userGroupsToRemove' => $this->userGroupsToRemove,
    //     ];

    //     return $data;
    // }
    
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
