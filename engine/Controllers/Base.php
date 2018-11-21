<?php

namespace Controllers;

use Common\Classes\Utils;

class Base implements iController{
    public function __construct($args=null){
        $mn='default_page';
        if(!empty($args[0]) && method_exists($this,$n=Utils::clearStr($args[0])) )$mn=$n;

        array_shift($args);
        $page=$this->{$mn}($args);

        echo $page;
        exit;
    }

    protected function error($err="404 Not found"){
        // Вернуть заголовок ощибки
        header($_SERVER["SERVER_PROTOCOL"].' '.$err);
        exit;
    }
}