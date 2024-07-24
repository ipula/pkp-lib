<?php

namespace PKP\invitation\sections;

class Sections
{
    public string $id;
    public string $type;
    public string $name;
    public string $description;
    public string $sectionComponent;
    public array $sections = [];
    public array $props = [];

    public function __construct(string $id, string $name, string $description = '', string $type, string $sectionComponent)
    {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->sectionComponent = $sectionComponent;
        $this->type = $type;
    }
    /**
     * Add a step to the workflow
     *
     * @param bool $prepend Pass true to add this step before other steps
     */
    public function addSection(Section $section, $props, bool $prepend = false)
    {
        if ($prepend) {
            array_unshift($this->sections, $section);
        } else {
            $this->sections[$section->id] = $section;
            $this->props = $props;
        }
    }

    public function getState(): array
    {
        $state = [];
        foreach ($this->sections as $section) {
            if($section->type === 'emptySection') {
                $props = [
                    ...$this->props
                ];
            } else {
                $props = [
                    ...$this->props,
                    $section->type => $section->getState(),
                ];
            }
            $state[] = [
                'id' => $this->id,
                'name' => $this->name,
                'description' => $this->description,
                'sectionComponent' => $this->sectionComponent,
                'props' => $props,
            ];
        }
        return $state;
    }
}
