<?php

/**
 * Interface IController
 */

namespace Controllers;

interface iController{
    public function __construct($args);
    public function render();
}