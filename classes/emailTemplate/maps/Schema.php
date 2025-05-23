<?php
/**
 * @file classes/emailTemplate/maps/Schema.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Schema
 *
 * @brief Map email templates to the properties defined in the email template schema
 */

namespace PKP\emailTemplate\maps;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Support\Enumerable;
use PKP\core\PKPApplication;
use PKP\emailTemplate\EmailTemplate;
use PKP\services\PKPSchemaService;

class Schema extends \PKP\core\maps\Schema
{
    /** @copydoc \PKP\core\maps\Schema::$collection */
    public Enumerable $collection;

    /** @copydoc \PKP\core\maps\Schema::$schema */
    public string $schema = PKPSchemaService::SCHEMA_EMAIL_TEMPLATE;

    /**
     * Map an email template
     *
     * Includes all properties in the email template schema.
     */
    public function map(EmailTemplate $item): array
    {
        return $this->mapByProperties($this->getProps(), $item);
    }

    /**
     * Summarize an email template
     *
     * Includes properties with the apiSummary flag in the email template schema.
     */
    public function summarize(EmailTemplate $item, ?string $mailableClass = null): array
    {
        return $this->mapByProperties($this->getSummaryProps(), $item, $mailableClass);
    }

    /**
     * Map a collection of email templates
     *
     * @see self::map
     */
    public function mapMany(Enumerable $collection): Enumerable
    {
        $this->collection = $collection;
        return $collection->map(function ($item) {
            return $this->map($item);
        });
    }

    /**
     * Summarize a collection of email templates
     *
     * @see self::summarize
     */
    public function summarizeMany(Enumerable $collection, ?string $mailableClass = null): Enumerable
    {
        $this->collection = $collection;
        return $collection->map(function ($item) use ($mailableClass) {
            return $this->summarize($item, $mailableClass);
        });
    }

    /**
     * Map schema properties of an Email Template to an assoc array
     */
    protected function mapByProperties(array $props, EmailTemplate $item, ?string $mailableClass = null): array
    {
        $output = [];
        $context = Application::get()->getRequest()->getContext();
        $contextId = $context->getId();
        $mailableClass = $mailableClass ?? Repo::mailable()->get($item->getData('alternateTo') ?? $item->getData('key'), $context);

        foreach ($props as $prop) {
            switch ($prop) {
                case '_href':
                    $output[$prop] = $this->request->getDispatcher()->url(
                        $this->request,
                        PKPApplication::ROUTE_API,
                        $this->context->getData('urlPath'),
                        'emailTemplates/' . $item->getData('key')
                    );
                    break;
                case 'isUnrestricted':
                    $output['isUnrestricted'] = Repo::emailTemplate()->isTemplateUnrestricted($item->getData('key'), $contextId);
                    break;
                case 'assignedUserGroupIds':
                    if ($mailableClass && Repo::mailable()->isGroupsAssignableToTemplates($mailableClass)) {
                        $output['assignedUserGroupIds'] = Repo::emailTemplate()->getAssignedGroupsIds($item->getData('key'), $contextId);
                    } else {
                        $output['assignedUserGroupIds'] = [];
                    }
                    break;
                default:
                    $output[$prop] = $item->getData($prop);
                    break;
            }
        }

        $output = $this->schemaService->addMissingMultilingualValues($this->schema, $output, $this->context->getSupportedFormLocales());

        ksort($output);

        return $this->withExtensions($output, $item);
    }
}
