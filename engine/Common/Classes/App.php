<?php

namespace Common\Classes;

class App{

    const MAX_TEXT_LENGTH=400;

    public static $params=[
        'AUTH_HOST'=>'http://100tkaney.loc',
        'db'=>[
            'name'=>'u0232960_lenta451888',
            'user'=>'u0232960_lent',
            'passwd'=>'5K4z9X4y'
        ],
        'SES_NAME'=>'lsn18081',
        'AUTH_NAME'=>'tK0918s',
        'avatar'=>[
            'types'=>[
                'ico'=>[
                    'width'=>40,
                    'height'=>40,
                    'images_path'=>'img/avatars/',
                ]
            ],
            'max_file_size'=>6000000,
            'max_count'=>1,
            'field_name'=>'userFiles'
        ],
        'foto'=>[
            'types'=>[
                'ico'=>[
                    'width'=>160,
                    'height'=>160,
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
        'msgfoto'=>[
            'types'=>[
                'ico'=>[
                    'width'=>160,
                    'height'=>160,
                    'images_path'=>'img/msgfotos/ico/',
                ],
                'mini'=>[
                    'width'=>375,
                    'height'=>375,
                    'images_path'=>'img/msgfotos/mini/'
                ],
                'big'=>[
                    'width'=>667,
                    'height'=>667,
                    'images_path'=>'img/msgfotos/big/'
                ],
                'hi'=>[
                    'width'=>1920,
                    'height'=>1080,
                    'images_path'=>'img/msgfotos/hi/'
                ]
            ],
            'max_file_size'=>20000000,
            'max_count'=>16,
            'field_name'=>'userFiles'
        ],
        'mag_start_data'=>[
            'timeout'=>30*1000, // обновление клиента, msec
            'max_post_items_count'=>4,
            'max_post_item_text_length'=> self::MAX_TEXT_LENGTH,
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
            $subscribes_tab='';
            $friends_tab='';
            $messages_tab='';
            $user_str='';
            $subscribes='';
            $friends='';
            $askfriends='';
        }else{
            $subscribes='subscribes:'.json_encode(DB::getUserSubscribeUsers(0,0,0,$user->id)).',';
            $friends='friends:'.json_encode(DB::getUserFriendUsers(0,0,0,$user->id,'active')).',';
            $askfriends='askfriends:'.json_encode(DB::getUserFriendUsers(0,0,0,$user->id,'new')).',';
            $my_tab="
    {
        name: 'Моя',
        type: 'my',
        canadd: true,
        comp: 'mainlent'
    },";
            $subscribes_tab="
    {
        name: 'Подписки',
        type: 'subscribeposts',
        comp: 'mainlent'
    },";
            $friends_tab="
    {
        name: 'Друзья',
        type: 'friendposts',
        comp: 'mainlent'
    },";
            $messages_tab="
    {
        name: 'Сообщения',
        type: 'messages',
        canadd: true,
        comp: 'messageslent'
    },";

            $user_str="
    user: {
        id: $user->id,
        et: '$user->enter_token',
        name: '$user->name',
        about: '$user->about',
        avatar: '$user->avatar'
    },";

        }

        $tabs=" tabs: [
        {
            name: 'Все',
            type: 'news',
            comp: 'mainlent'
        },
        {
            name: 'Анонсы',
            type: 'main',
            comp: 'mainlent'
        },$my_tab
        $subscribes_tab
        $friends_tab
        $messages_tab
    ],";

        $start_data_str="
document.mag_start_data={
    $subscribes
    $friends
    $askfriends
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