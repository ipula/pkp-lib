<?php

namespace PKP\invitation\types;

use PKP\context\Context;
use PKP\invitation\core\Invitation;

abstract class InvitationStepTypes
{
    /**
     * Get the workflow steps for this decision type
     *
     * Returns null if this decision type does not use a workflow.
     * In such cases the decision can be recorded but does not make
     * use of the built-in UI for making the decision
     */
    abstract public function getSteps(?Invitation $invitation, Context $context);
}
