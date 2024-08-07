<?php

namespace PKP\invitation\stepTypes;

use PKP\context\Context;
use PKP\invitation\core\Invitation;
use PKP\invitation\invitations\userRoleAssignment\UserRoleAssignmentInvite;

abstract class InvitationStepTypes
{
    /**
     * Get the invitation steps
     * use of the built-in UI for making the invitation
     */
    abstract public function getSteps(?Invitation $invitation, Context $context);

    /** fake invitation for email template
     */
    protected function getFakeInvitation(): UserRoleAssignmentInvite
    {
        return new UserRoleAssignmentInvite();
    }

}
