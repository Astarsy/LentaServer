<?php

namespace Controllers;

use Common\Classes\App;
use Common\Classes\DB;
use Common\Classes\Logger;
use Common\Classes\Utils;

class Html extends Base{

    protected $_doc,$_twig;

    public function __construct($args=null){
        require_once'../vendor/Twig/lib/Twig/Autoloader.php';
        \Twig_Autoloader::register();
        $loader=new \Twig_Loader_Filesystem('../engine/templates');
        $this->_twig=new \Twig_Environment($loader,array('debug'=>false));

        parent::__construct($args);
    }

    protected function test($args){
        $this->title='Test';
        $this->page='TEST';
        echo'<pre>';
        if(!App::$user)App::$user=Logger::loginByRemote($args);

        echo'<pre>';
        var_dump(App::$user);
        return "Ok";
    }

    protected function test_main(){
        $this->title='Test - Main';
        $this->page='TEST MAIN PAGE';
        $this->foto_width=App::$params['foto']['types']['mini']['width'];
        return $this->_twig->render('test_main.twig',['this'=>$this]);
    }

    protected function main(){
        $this->title='Lenta - Main';
        $this->page='MAIN PAGE';
        return $this->_twig->render('main.twig',['this'=>$this]);
    }

    protected function default_page(){
        $this->title=Utils::now().' Lenta - Default';
        $this->start_data=App::getStartData();
        return $this->_twig->render('default.twig',['this'=>$this]);
    }
}