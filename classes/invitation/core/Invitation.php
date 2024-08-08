<?php

/**
 * @file classes/invitation/core/Invitation.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Invitation
 *
 * @brief Abstract class for all Invitations
 */

namespace PKP\invitation\core;

use APP\core\Application;
use APP\facades\Repo;
use Carbon\Carbon;
use Exception;
use Identity;
use Illuminate\Support\Facades\Mail;
use PhpCsFixer\Fixer\FunctionNotation\VoidReturnFixer;
use PKP\config\Config;
use PKP\context\Context;
use PKP\invitation\core\enums\InvitationAction;
use PKP\invitation\core\enums\InvitationStatus;
use PKP\invitation\core\traits\HasMailable;
use PKP\invitation\core\traits\ShouldValidate;
use PKP\invitation\models\InvitationModel;
use PKP\pages\invitation\InvitationHandler;
use PKP\security\Validation;
use PKP\user\User;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Mailer\Exception\TransportException;

abstract class Invitation
{
    public const DEFAULT_EXPIRY_DAYS = 3;

    private ?string $key = null;

    public InvitationModel $invitationModel;

    protected array $notAccessibleBeforeInvite = [];
    protected array $notAccessibleAfterInvite = [];
    protected array $payloadAccessibleProperties = [];

    abstract public static function getType(): string;
    abstract protected function preInviteActions(): void;
    abstract public function getInvitationActionRedirectController(): ?InvitationActionRedirectController;

    public function __construct(?InvitationModel $invitationModel = null)
    {
        $this->invitationModel = $invitationModel ?: new InvitationModel([
            'type' => $this->getType()
        ]);

        $this->fillFromPayload();
    }

    public function initialize(?int $userId = null, ?int $contextId = null, ?string $email = null, ?int $inviterId = null)
    {
        if (!isset($userId) && !isset($email)) {
            throw new Exception("Invitation should contain the user id or an invited email.')");
        }

        $userIdUsed = null;
        $emailUsed = null;
        if (isset($userId)) {
            $userIdUsed = $userId;
        } else {
            $emailUsed = $email;
        }

        InvitationModel::byStatus(InvitationStatus::INITIALIZED)
            ->when($userIdUsed !== null, function ($query) use ($userIdUsed) {
                return $query->byUserId($userIdUsed);
            })
            ->when($contextId !== null, function ($query) use ($contextId) {
                return $query->byContextId($contextId);
            })
            ->when($emailUsed !== null, function ($query) use ($emailUsed) {
                return $query->byEmail($emailUsed);
            })
            ->byType($this->getType())
            ->delete();

        $this->invitationModel->userId = $userIdUsed;
        $this->invitationModel->contextId = $contextId;
        $this->invitationModel->email = $emailUsed;
        $this->invitationModel->inviterId = $inviterId;

        $this->invitationModel->status = InvitationStatus::INITIALIZED;

        $this->invitationModel->save();
    }

    /**
     * This is filled with correlation between an invitation property
     * and the Object that this property corresponds to.
     */
    protected array $propertyType = [];

    /**
     * Used to fill the invitation's properties from the model's payload values.
     * if the $propertyType has values in, then the property is filled by the 
     * fromArray function of the given Object correlation
     */
    protected function fillFromPayload()
    {
        if ($this->invitationModel->payload) {
            foreach ($this->invitationModel->payload as $key => $value) {
                if (property_exists($this, $key)) {
                    if (property_exists($this, 'propertyType') && !empty($this->propertyType) && array_key_exists($key, $this->propertyType)) {
                        if (is_array($value)) {
                            $this->{$key} = array_map(function ($item) use ($key) {
                                return $this->propertyType[$key]::fromArray($item);
                            }, $value);
                        } else {
                            $this->{$key} = $this->propertyType[$key]::fromArray($item);
                        }
                    } else {
                        $this->{$key} = $value;
                    }
                }
            }
        }
    }

    /**
     * Use that when you have an array of values that the invitation needs to fill its 
     * properties with.
     */
    public function fillFromArgs(array $args): void
    {
        foreach ($args as $propName => $value) {
            if ($this->getStatus() == InvitationStatus::INITIALIZED) {
                if (in_array($propName, $this->notAccessibleBeforeInvite)) {
                    continue;
                }
            } elseif ($this->getStatus() == InvitationStatus::PENDING) {
                if (in_array($propName, $this->notAccessibleAfterInvite)) {
                    continue;
                }
            } else {
                throw new Exception('You can not modify the Invitation in this stage');
            }

            if ($propName !== 'invitationModel' && property_exists($this, $propName)) {
                $this->{$propName} = $value;
            }
        }
    }

    /**
     * Saves the payload to the database, after it passes a sanity check 
     */
    public function updatePayload(): ?bool
    {
        $payload = $this->invitationModel->payload ?: [];

        $payloadAccessibleProperties = $this->getPayloadAccessibleProperties();
        if (!empty($payloadAccessibleProperties)) {
            foreach ($payloadAccessibleProperties as $payloadAccessibleProperty) {
                if ($propName !== 'invitationModel' && property_exists($this, $payloadAccessibleProperty)) {
                    $payload[$payloadAccessibleProperty] = $this->{$payloadAccessibleProperty};
                }
            }
        } else {
            // Create a ReflectionClass instance for the current object
            $reflection = new ReflectionClass($this);

            // Get public properties only
            $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

            foreach ($properties as $property) {
                $propName = $property->getName();

                if ($propName !== 'invitationModel' && property_exists($this, $propName)) {
                    // if the initial payload does not have the specific property name, and no value is set 
                    // currently for that property name, don't add the property to the payload
                    if ((!isset($this->invitationModel->payload) || !array_key_exists($propName, $this->invitationModel->payload)) && !isset($this->{$propName})) {
                        continue;
                    }

                    $payload[$propName] = $this->{$propName};
                }
            }
        }

        if (!$this->checkPayloadIntegrity($this->invitationModel->payload ?? [], $payload)) {
            return null;
        }

        // Update the payload attribute on the invitation
        $this->invitationModel->setAttribute('payload', $payload);

        return $this->invitationModel->save();
    }

    public function getNotAccessibleBeforeInvite(): array
    {
        return $this->notAccessibleBeforeInvite;
    }

    public function getNotAccessibleAfterInvite(): array
    {
        return $this->notAccessibleAfterInvite;
    }

    public function getPayloadAccessibleProperties(): array
    {
        return $this->payloadAccessibleProperties;
    }

    protected function checkForKey()
    {
        if (!isset($this->invitationModel->keyHash)) {
            if (!isset($this->key)) {
                $this->key = Validation::generatePassword();
            }

            $this->invitationModel->keyHash = self::makeKeyHash($this->key);
        }
    }

    public function setExpiryDate(Carbon $expiryDate)
    {
        if ($this->getStatus() !== InvitationStatus::INITIALIZED) {
            throw new Exception('Can not change expiry date at this stage');
        }

        $this->invitationModel->expiryDate = $expiryDate;
    }

    public function invite(): bool
    {
        if ($this->getStatus() !== InvitationStatus::INITIALIZED) {
            throw new Exception('The invitation can not be dispatched');
        }

        // Need to return error messages also?
        $this->preInviteActions();

        if (in_array(ShouldValidate::class, class_uses($this))) {
            if (!$this->isValid()) {
                return false;
            }
        }

        $this->checkForKey();

        $this->setExpiryDate(Carbon::now()->addDays($this->getExpiryDays()));

        if (in_array(HasMailable::class, class_uses($this))) {
            $mailable = $this->getMailable();

            if (isset($mailable)) {
                try {
                    Mail::send($mailable);
                } catch (TransportException $e) {
                    trigger_error('Failed to send email invitation: ' . $e->getMessage(), E_USER_ERROR);
                }
            }
        }

        $this->invitationModel->status = InvitationStatus::PENDING;

        $this->invitationModel->save();

        return true;
    }

    public function getInviter(): ?User
    {
        if (!isset($this->invitationModel->inviterId)) {
            return null;
        }

        return Repo::user()->get($this->invitationModel->inviterId);
    }

    public function getContext(): ?Context
    {
        if (!isset($this->invitationModel->contextId)) {
            return null;
        }

        $contextDao = Application::getContextDAO();
        return $contextDao->getById($this->invitationModel->contextId);
    }

    public function getMailableReceiver(?string $locale = null): Identity
    {
        $locale = $this->getUsedLocale($locale);

        $sendIdentity = new Identity();
        $user = null;
        if ($this->invitationModel->userId) {
            $user = Repo::user()->get($this->invitationModel->userId);
            
            $sendIdentity->setFamilyName($user->getFamilyName($locale), $locale);
            $sendIdentity->setGivenName($user->getGivenName($locale), $locale);
            $sendIdentity->setEmail($user->getEmail());
        } else {
            $sendIdentity->setEmail($this->invitationModel->email);
        }

        return $sendIdentity;
    }

    public function getUsedLocale(?string $locale = null): string
    {
        if (isset($locale)) {
            return $locale;
        }

        if (isset($this->invitationModel->contextId)) {
            $contextDao = Application::getContextDAO();
            $context = $contextDao->getById($this->invitationModel->contextId);
            return $context->getPrimaryLocale();
        }

        $request = Application::get()->getRequest();
        $site = $request->getSite();
        return $site->getPrimaryLocale();
    }

    private static function makeKeyHash($key): string
    {
        return password_hash($key, PASSWORD_BCRYPT);
    }

    public function getId(): ?int
    {
        if (isset($this->invitationModel)) {
            return $this->invitationModel->id;
        }

        return null;
    }

    public function getStatus(): ?InvitationStatus
    {
        if (isset($this->invitationModel)) {
            return $this->invitationModel->status;
        }

        return null;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function getActionURL(InvitationAction $invitationAction): ?string
    {
        return InvitationHandler::getActionUrl($invitationAction, $this);
    }

    public function checkPayloadIntegrity(array $initialPayload, array $modifiedPayload): bool
    {
        $checkArray = null;

        if ($this->getStatus() == InvitationStatus::INITIALIZED) {
            $checkArray = $this->getNotAccessibleBeforeInvite();
        } elseif ($this->getStatus() == InvitationStatus::PENDING) {
            $checkArray = $this->getNotAccessibleAfterInvite();
        } else {
            throw new Exception('You can not modify the Invitation in this stage');
        }

        foreach ($modifiedPayload as $key => $value) {
            // Check if the key exists in the initial payload
            if (!array_key_exists($key, $initialPayload)) {
                // Key does not exist in initial, so this is a modification
                if (in_array($key, $checkArray)) {
                    throw new Exception('The property ' . $key . ' can not be modified in this stage');
                }
            }

            // The key exists; now compare values
            if ($initialPayload[$key] !== $value) {
                // Different value detected, this is a modification
                if (in_array($key, $checkArray)) {
                    throw new Exception('The property ' . $key . ' can not be modified in this stage');
                }
            }
        }

        if (in_array(ShouldValidate::class, class_uses($this))) {
            return $this->validate();
        }

        return true;
    }

    public function decline(): void
    {
        $this->invitationModel->markAs(InvitationStatus::DECLINED);
    }

    protected function getExpiryDays(): int
    {
        return (int) Config::getVar('invitations', 'expiration_days', self::DEFAULT_EXPIRY_DAYS);
    }

    /**
     * this function is overriden by custom invitations if necessary
     * so that properties of the invitation are filled before returning 
     * the invitation object to the code.
     * Used in InvitationFactory::getExisting.
     */
    public function fillCustomProperties(): void
    {
        return;
    }
}
