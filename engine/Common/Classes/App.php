<?php

namespace Common\Classes;

class App{

    public static $params=[
        'AUTH_HOST'=>'http://100tkaney.loc',
        'db'=>[
            'name'=>'u0232960_lenta451888',
            'user'=>'u0232960_lent',
            'passwd'=>'5K4z9X4y'
        ],
        'SES_NAME'=>'lsn18081',
        'AUTH_NAME'=>'tK0918s',
        // TODO: реализован только режим Удаления - 'kill_mode'=>true
        'kill_mode'=>true,  // удалять или помечать как удалённые посты, итемы и фото
        'foto'=>[
            'types'=>[
                'ico'=>[
                    'width'=>84,
                    'height'=>84,
                    'images_path'=>'img/fotos/ico/',
                ],
                'mini'=>[
                    'width'=>375,
                    'height'=>375,
                    'images_path'=>'img/fotos/mini/'
                ],
                'big'=>[
                    'width'=>667,
                    'height'=>667,
                    'images_path'=>'img/fotos/big/'
                ],
                'hi'=>[
                    'width'=>1920,
                    'height'=>1080,
                    'images_path'=>'img/fotos/hi/'
                ]
            ],
            'max_file_size'=>20000000,
            'max_count'=>16,
            'field_name'=>'userFiles'
        ],
        'mag_start_data'=>[
            'timeout'=>30*1000, // обновление клиента, msec
            'max_post_items_count'=>4,
            'max_post_item_text_length'=> 400,
            'colors'=>['#fff','#fee','#efe','#eef','#ffe','#fef','#eff'],
        ]
    ];

    public static $user=null;

    public static function getStartData(){
        $user=self::$user;
        $mag_start_data=self::$params['mag_start_data'];
        $timeout="timeout:".$mag_start_data['timeout'].',';

        if(!$user){
            $my_tab='';
            $friends_tab='';
            $user_str='';
            $subscribes='';
        }else{
            $subscribes='subscribes:'.json_encode(DB::getUserSubscribeUsers(0,0,0,$user->id)).',';
            $my_tab="
    {
        name: 'Моя',
        type: 'my',
        canadd: true
    },";
            $friends_tab="
    {
        name: 'Подписки',
        type: 'subscribeposts'
    },";
            $user_str="
    user: {
        id: $user->id,
        et: '$user->enter_token',
        name: '$user->name'
    },";
        }

        $tabs=" tabs: [
        {
            name: 'Новые',
            type: 'news'
        },
        {
            name: 'Анонсы',
            type: 'main'
        },$my_tab
        $friends_tab
    ],";

        $start_data_str="
document.mag_start_data={
    $subscribes
    $tabs
    $user_str
    $timeout
    max_post_items_count: ".$mag_start_data['max_post_items_count'].",
    max_post_item_text_length: ".$mag_start_data['max_post_item_text_length'].",
    colors: ['#fff','#fee','#efe','#eef','#ffe','#fef','#eff'],
    foto: {
        ico:{
            width: 84,
            height: 84,
            max_count: 4
        },
        mini:{ 
            type: 'mini',
            width: 300,
            height: 300,
            max_count: 2
        },
    }
}";

        return $start_data_str;
    }

    public function __construct(){
        session_start();
        // TODO: проверить и утановить режим отладки
        self::$params['debug']=true;

        // FIXED: логинить здесь
        self::$user=Logger::getUser();
    }

    public function run(){

        $url=explode('?',$_SERVER['REQUEST_URI'])[0];
        $pieces=explode('/',$url);
        $cn=Utils::clearStr(ucfirst(strtolower($pieces[1])));
        $args=array_slice($pieces,2);

        $cn="\\Controllers\\$cn";
        if(!class_exists($cn) || !is_subclass_of($cn,'\\Controllers\\iController'))$this->error();

        new $cn($args);
    }

    public function error(){
        header($_SERVER["SERVER_PROTOCOL"] . " 500 Internal Server Error");
        exit;
    }
}