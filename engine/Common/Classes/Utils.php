<?php

namespace Common\Classes;

class Utils{
    public static function clearStr($s,$l=50){
        return mb_substr(trim($s),0,$l,'UTF-8');
    }

    public static function clearInt($i){
        return (int)$i;
    }

    public static function clearUInt($i){
        $_i=self::clearInt($i);
        return abs($_i);
    }

    public static function clearFilename($str){
        $res=self::clearStr($str,50);
        $res=str_replace(' ','',$res);
        return preg_replace('/[^A-Za-z0-9\-\.]/', '', $res);
    }

    public static function now(){
        return date("Y-m-d H:i:s");
    }

    public static function getHash($str){
        // Дублируется на стороне Lenta
        $i=8;
        $salt='nsdavoiyudqodf87webqhe2g89';
        while($i--)$str=sha1($str,$salt);
        return base64_encode($str);
    }
}