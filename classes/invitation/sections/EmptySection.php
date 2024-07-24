<?php

namespace PKP\invitation\sections;

use Exception;
use stdClass;

class EmptySection extends Section
{
    public string $type = 'emptySection';

    /**
     * @throws Exception
     */
    public function __construct(string $id, string $name, string $description)
    {
        parent::__construct($id, $name, $description);
    }

    public function getState(): stdClass
    {
        return parent::getState();
    }
}
