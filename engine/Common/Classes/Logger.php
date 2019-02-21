<?php

namespace Common\Classes;

class Logger{
    // Пытается звлогинить по кукам (срок жизни ограничен в function login),
    // если куки нет - запросить удалённый логин

    protected static $_user;

    public static function getUser(){
        if(!self::$_user)self::$_user=self::loginByCoockie();
        return self::$_user;
    }

    protected static function loginByCoockie(){
        // Если уже залогинен куками - загрузить п-ля
        $data=self::getData();
        if(empty($data['token']))return null;
        else{
            $token=Utils::clearStr($data['token']);
            $user=new User();
            if(($err=$user->loadBy('enter_token',$token))|| empty($user->id))return null;
            return $user;
        }
    }

    public static function loginByRemote($args){
        // Запросить удалённую аутентификацию по id, enter_token,
        // проверить ответ по хэшу enter_token,
        // проверить, есть ли он в БД, извлечь или создать,
        // залогинить куками, вернуть user/null

        if(count($args)<2)return null;
        $suid=Utils::clearUInt($args[0]);
        $et=Utils::clearStr($args[1]);

        $url=App::$params['AUTH_HOST'].'/api/lenta/login';
        $params['hid']=Utils::getHash($suid);
        $params['et']=$et;

        $json_resp=self::request($url,$params);
        if(null==($res_arr=json_decode($json_resp)))return null;
        $het_in=$res_arr->her;
        $het_fact=Utils::getHash($et);

        if($het_fact!==$het_in)return null;
        else{
            $user=new User();
            if($err=$user->loadBy('shop_user_id',$suid))throw new \Exception('ошибка загрузки пользователя');

            if($user->id){
                // old user is loaded
                if($user->enter_token!==$et){
                    $user->enter_token=$et;
                    if($err=$user->save())throw new \Exception('ошибка обновления пользователя');
                }
            }else{
                // new user
                $user->shop_user_id=$suid;
                $user->enter_token=$et;
                $user->status='active';
                $user->name=$res_arr->username;
                if($err=$user->save())throw new \Exception('ошибка при создании пользователя');
            }
            return self::login($user);
        }
    }

    protected static function request($url,$params){
        // Отправить запрос, вернуть ответ
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // вернуть ответ
        curl_setopt($curl, CURLOPT_POSTFIELDS,$params);
        $resp=curl_exec($curl);
        curl_close($curl);
        return $resp;
    }

    public static function login($user){
        // Залогинить, т.е сохранить данные для приложения
        $_SESSION[App::$params['SES_NAME']]=$user->enter_token;

        $t=time()+60*60*24*365; // куки на s*m*h*d
//        $t=time()+0.1*60; // DEBUG

        setcookie(
            App::$params['AUTH_NAME']
            ,json_encode([
            'token'=>$user->enter_token
        ]),$t,'/');
        self::$_user=$user;
        return $user;
    }

    public static function logout(){
        // Разалогинить
        unset($_SESSION[App::$params['SES_NAME']]);
        setcookie(App::$params['AUTH_NAME'],null,0,'/');
        self::$_user=null;
    }

    protected static function getData(){
        // Получить данные сессии и куки текущего пользователя
        $data=[];
        if(!empty($_SESSION[App::$params['SES_NAME']])){
            $data['email']=$_SESSION[App::$params['SES_NAME']];
        }
        $an=App::$params['AUTH_NAME'];
        if(!empty($_COOKIE[$an])){
            $c=json_decode($_COOKIE[$an],true);
            if(is_array($c))$data=array_merge($data,$c);
        }
        return $data;
    }
}