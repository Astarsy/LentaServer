<?php

namespace Controllers;

use Common\Classes\App;
use Common\Classes\DB;
use Common\Classes\UserPost;
use Common\Classes\Utils;

class Api extends Base{

    const MAIN_PAGE_POSTS_COUNT=8;
    const MAIN_USER_ID=1;
    const MAX_TEXT_LENGTH=200;
    const MAX_ITEMS_COUNT=2;

    protected function del($args){
//        var_dump($_POST);exit;
        // Удалить пост
        if(!$user=App::$user)$this->error();
        if(!isset($_POST['id']))$this->error();
        $pid=Utils::clearUInt($_POST['id']);
//        $err=UserPost::setDeleted($pid);  // на случай реализации режима Помечания удалённым
        $err=UserPost::delete($pid);
        if($err){
            header($_SERVER["SERVER_PROTOCOL"] . " 500 Internal Server Error");
            die($err);// TODO: debug
//            die('Не удалось удалить публикацию... Если эта ситуация повторится, пожалуйста, сообщите нам об этом, мы обязательно поможем!');
        }
        die("Публикация успешно удалена.");
    }

    protected function add($args){
        //        var_dump($_POST);exit;
        // Добавить или обновить пост, вернуть id поста/error 500
        if(!$user=App::$user) $this->error();
        if(!isset($_POST['data']))$this->error();
        $data=json_decode($_POST['data']);
        if(null===$post=UserPost::create($data))$this->error();
        if($err=$post->save()){
            header($_SERVER["SERVER_PROTOCOL"] . " 500 Internal Server Error");
            die($err);  // TODO: debug only
//            die('Похоже, что-то не получилось... Если эта ситуация повторится, пожалуйста, сообщите нам об этом, мы обязательно поможем!');
        }
        echo $post->_data->id;
    }

    protected function main($args){
        // Отдать сообщения Главной ленты
        if(!isset($_GET['lastupdate'])) $this->error();
        if(!isset($_GET['curpage']))$cp=0;
        else $cp=Utils::clearUInt($_GET['curpage']);
        $lu=Utils::clearStr($_GET['lastupdate']);
        $posts=DB::getUserPosts($lu,$cp,self::MAIN_PAGE_POSTS_COUNT,self::MAIN_USER_ID);
        if(count($posts)<1)die('Ok');
        header('Content-type:application/json');
        $obj=new \stdClass();
        $obj->lastupdate=Utils::now();
        $obj->posts=$posts;
        die(json_encode($obj));
    }

    protected function my($args){
        // Отдать сообщения Личной ленты текущего пользователя
        if(!$user=App::$user) $this->error();
        if(!isset($_GET['lastupdate'])) $this->error();
        if(!isset($_GET['curpage']))$cp=0;
        else $cp=Utils::clearUInt($_GET['curpage']);
        $lu=Utils::clearStr($_GET['lastupdate']);
        $posts=DB::getUserPosts($lu,$cp,self::MAIN_PAGE_POSTS_COUNT,$user->id,null);
        if(count($posts)<1)die('Ok');
        header('Content-type:application/json');
        $obj=new \stdClass();
        $obj->lastupdate=Utils::now();
        $obj->posts=$posts;
        die(json_encode($obj));
    }

    protected function friends($args){
        // Отдать сообщения друзей текущего пользователя
        if(!$user=App::$user) $this->error();
        if(!isset($_GET['lastupdate'])) $this->error();
        if(!isset($_GET['curpage']))$cp=0;
        else $cp=Utils::clearUInt($_GET['curpage']);
        $lu=Utils::clearStr($_GET['lastupdate']);
        $posts=DB::getUserFrendsPosts($lu,$cp,self::MAIN_PAGE_POSTS_COUNT,$user->id);
        if(count($posts)<1)die("Ok");
        header('Content-type:application/json');
        $obj=new \stdClass();
        $obj->lastupdate=Utils::now();
        $obj->posts=$posts;
        die(json_encode($obj));
    }

    protected function default_page(){
         $this->error();
    }
}