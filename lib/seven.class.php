<?php

defined('_SEVEN') or die('No direct script access allowed');

require_once(dirname(__FILE__) . DS . 'core.class.php');

class Seven extends Core
{
    public function __toString() {
        return __CLASS__;
    }
}
