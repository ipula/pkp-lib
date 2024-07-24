<?php

namespace PKP\invitation\sections;

use Exception;
use PKP\components\forms\FormComponent;
use stdClass;

class Form extends Section
{
    public string $type = 'form';
    public FormComponent $form;

    /**
     * @param FormComponent $form The form to show in this step
     *
     * @throws Exception
     */
    public function __construct(string $id, string $name, string $description, FormComponent $form)
    {
        parent::__construct($id, $name, $description);
        $this->form = $form;
    }

    public function getState(): stdClass
    {
        $config = parent::getState();
        $config->id = $this->form->getConfig()['id'];
        $config->method = $this->form->getConfig()['method'];
        $config->action = $this->form->getConfig()['action'];
        $config->fields = $this->form->getConfig()['fields'];
        $config->groups = $this->form->getConfig()['groups'];
        $config->hiddenFields = $this->form->getConfig()['hiddenFields'];
        $config->pages = $this->form->getConfig()['pages'];
        $config->primaryLocale = $this->form->getConfig()['primaryLocale'];
        $config->visibleLocales = $this->form->getConfig()['visibleLocales'];
        $config->supportedFormLocales = $this->form->getConfig()['supportedFormLocales'];
        $config->errors = $this->form->getConfig()['errors'];
        ;

        //        dd($this->form->getConfig()['id']);
        // Decision forms shouldn't have submit buttons
        // because the step-by-step decision wizard includes
        // next/previous buttons
        unset($config->pages[0]['submitButton']);

        return $config;
    }
}
