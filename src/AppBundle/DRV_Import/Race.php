<?php

namespace AppBundle\DRV_Import;

use AppBundle\DRV_Import\Boat;

class Race
{
    private $boats = array();

    public function __construct(\SimpleXMLElement $x) {
        $this->number = (string)$x['nummer'];
        $this->specification = (string) $x['spezifikation'];
    }

    public function add(Boat $boat) {
        $this->boats[] = $boat;
    }
}