<?php

namespace PKP\invitation\steps;

use stdClass;

class Step
{
    public string $id;
    public string $type;
    public string $name;
    public string $description;
    public string $reviewName;
    public string $nextButtonLabel;
    public array $sections = [];

    /**
     * @param string $id A unique id for this step
     * @param string $name The name of this step. Shown to the user.
     * @param string $description A description of this step. Shown to the user.
     */
    public function __construct(string $id, string $name, string $description = '', string $reviewName, string $nextButtonLabel, string $type)
    {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->reviewName = $reviewName;
        $this->nextButtonLabel = $nextButtonLabel;
        $this->type = $nextButtonLabel;
    }

    /**
     * Compile initial state data to pass to the frontend
     */
    public function getState(): stdClass
    {
        $config = new stdClass();
        $config->id = $this->id;
        $config->name = $this->name;
        $config->description = $this->description;
        $config->nextButtonLabel = $this->nextButtonLabel;
        $config->reviewName = $this->reviewName;
        $config->sections = $this->sections;
        return $config;
    }

    /**
     * Add a step to the workflow
     */
    public function addSectionToStep($sections): void
    {
        $this->sections = $sections;
    }
}
