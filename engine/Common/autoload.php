<?php
spl_autoload_register(function($name){
    $base='../engine/';
    $suf='.php';
    $path=$base.str_replace('\\','/',$name).$suf;
    if(@include_once $path.'')return;
});