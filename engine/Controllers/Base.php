<?php

namespace Controllers;

use Common\Classes\Utils;

class Base implements iController{
    protected $_args;

    public function __construct($args=null){
        $this->_args=$args;
    }

    public function render(){
        $mn='default_page';
        if(!empty($this->_args[0]) && method_exists($this,$n=Utils::clearStr($this->_args[0])) )$mn=$n;
        array_shift($this->_args);
        return $this->{$mn}($this->_args);
    }

    protected function error($err="404 Not found"){
        // Вернуть заголовок ощибки
        header($_SERVER["SERVER_PROTOCOL"].' '.$err);
        exit;
    }
}