<?php

namespace PKP\components\forms\invitation;

use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;

define('ACCEPT_FORM_USER_DETAILS', 'acceptUserDetails');
class AcceptUserDetailsForm extends FormComponent
{
    /** @copydoc FormComponent::$id */
    public $id = FORM_USER_DETAILS;

    /** @copydoc FormComponent::$method */
    public $method = 'POST';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     */
    public function __construct($action, $locales)
    {
        $this->action = $action;
        $this->locales = $locales;

        $this->addField(new FieldText('orcid', [
            'label' => __('user.orcid'),
            'isRequired' => false,
            'size' => 'large',
            'value' => ''
        ]))
            ->addField(new FieldText('givenName', [
                'label' => __('user.givenName'),
                'isRequired' => true,
                'size' => 'large',
                'value' => ''
            ]))
            ->addField(new FieldText('familyName', [
                'label' => __('user.familyName'),
                'isRequired' => true,
                'size' => 'large',
                'value' => ''
            ]))
            ->addField(new FieldText('affiliation', [
                'label' => __('user.affiliation'),
                'isRequired' => true,
                'size' => 'large',

            ]))
            ->addField(new FieldText('country', [
                'label' => __('user.countyOfAffiliation'),
                'isRequired' => true,
                'size' => 'large',
            ]));

    }
}
