<?php

/**
 * @file pages/invitation/InitializeInvitationUIHandler.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InitializeInvitationUIHandler
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
use PKP\invitation\core\enums\InvitationAction;
use PKP\invitation\core\Invitation;
use PKP\invitation\invitations\userRoleAssignment\handlers\UserRoleAssignmentInviteUIController;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRequiredPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;

class InitializeInvitationUIHandler extends Handler
{
    public function __construct()
    {
        parent::__construct();

        $this->addRoleAssignment(
            [
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                ROLE::ROLE_ID_ASSISTANT,
            ],
            [
                'initializeUI',
            ]
        );
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new UserRequiredPolicy($request));

        $this->addPolicy(new UserRolesRequiredPolicy($request), true);

        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);
        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Create an invitation for a user to accept new roles
     * @throws \Exception
     */
    public function initializeUI(array $args, Request $request): void
    {
        if (empty($args) || count($args) < 1) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        $this->setupTemplate($request);

        $arg = $args[0];

        if (is_numeric($arg)) {
            // Handle existing invitation by ID
            $invitationId = (int) $arg;
            $invitation = Repo::invitation()->getById($invitationId);
            if (!$invitation) {
                throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
            }
            $invitationHandler = $invitation->getInvitationUIActionRedirectController();
            $invitationHandler->editHandle($request);
        } else {
            // Handle new invitation by type
            $invitationType = $arg;
            $invitation = app(Invitation::class)->createNew($invitationType);
            $invitationHandler = $invitation->getInvitationUIActionRedirectController();
            $invitationHandler->createHandle($request);
        }
    }
}
