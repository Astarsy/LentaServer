<?php

namespace Common\Classes;

class StartData{

    protected $_start_data_str;

    public function __construct(){
        $user=App::$user;
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
        $timeout="timeout:".App::$params['mag_start_data']['timeout'].',';
        $this->_start_data_str="
document.mag_start_data={
    $subscribes
    $friends
    $askfriends
    $tabs
    $user_str
    $timeout
    max_post_items_count: ".App::$params['mag_start_data']['max_post_items_count'].",
    max_post_item_text_length: ".App::$params['mag_start_data']['max_post_item_text_length'].",
    auth_host: '".App::$params['AUTH_HOST']."',
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
    }

    public function __toString(){
        return $this->_start_data_str;
    }
}