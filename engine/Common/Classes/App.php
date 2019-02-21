<?php

namespace Common\Classes;

class App{

    public static $params,$msd='',$user=null;

    public function __construct(){
        session_start();

        $cl=require('../engine/Common/config_loc.php');
        self::$params=require('../engine/Common/config.php');
        self::$params=array_merge($cl,self::$params);

//        echo'<pre>';
//        var_dump(self::$params);exit;

        self::$user=Logger::getUser();

        $sd=new StartData();
        self::$msd=$sd->__toString();
    }

    public static function getStartData(){
        return self::$msd;
    }

    public function run(){

        $url=explode('?',$_SERVER['REQUEST_URI'])[0];
        $pieces=explode('/',$url);
        $cn=Utils::clearStr(ucfirst(strtolower($pieces[1])));
        $args=array_slice($pieces,2);

        $cn="\\Controllers\\$cn";
        if(!class_exists($cn))$this->error();

        $ci=new $cn($args);
        echo $ci->render();
    }

    public function error(){
        header($_SERVER["SERVER_PROTOCOL"] . " 500 Internal Server Error");
        exit;
    }
}