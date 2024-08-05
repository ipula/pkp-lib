<?php

namespace PKP\components\forms\invitation;

use PKP\components\forms\FieldSelect;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;
use PKP\facades\Locale;

define('ACCEPT_FORM_USER_DETAILS', 'acceptUserDetails');
class AcceptUserDetailsForm extends FormComponent
{
    /** @copydoc FormComponent::$id */
    public $id = ACCEPT_FORM_USER_DETAILS;

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

        $countries = [];
        foreach (Locale::getCountries() as $country) {
            $countries[] = [
                'value' => $country->getAlpha2(),
                'label' => $country->getLocalName()
            ];
        }

        usort($countries, function ($a, $b) {
            return strcmp($a['label'], $b['label']);
        });

        $this->addField(new FieldText('givenName', [
            'label' => __('user.givenName'),
            'description' => __('acceptInvitation.userDetailsForm.givenName.description'),
            'isRequired' => true,
            'size' => 'large',
            'value' => ''
        ]))
            ->addField(new FieldText('familyName', [
                'label' => __('user.familyName'),
                'description' => __('acceptInvitation.userDetailsForm.familyName.description'),
                'isRequired' => true,
                'size' => 'large',
                'value' => ''
            ]))
            ->addField(new FieldText('affiliation', [
                'label' => __('user.affiliation'),
                'description' => __('acceptInvitation.userDetailsForm.affiliation.description'),
                'isRequired' => true,
                'size' => 'large',

            ]))
            ->addField(new FieldSelect('country', [
                'label' => __('acceptInvitation.userDetailsForm.countyOfAffiliation.label'),
                'description' => __('acceptInvitation.userDetailsForm.countyOfAffiliation.description'),
                'options' => $countries,
                'isRequired' => true,
                'size' => 'large',
            ]));

    }
}
