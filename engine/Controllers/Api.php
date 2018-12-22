<?php

namespace Controllers;

use Common\Classes\App;
use Common\Classes\DB;
use Common\Classes\UserPost;
use Common\Classes\Utils;

class Api extends Base{

    const PAGE_POSTS_COUNT=8;
    const MAIN_USER_ID=1;
    const MAX_TEXT_LENGTH=200;
    const MAX_ITEMS_COUNT=2;

    protected function subscribe($args){
        // Принять uid, подписать п-ля, вернуть п-ля - автора/error 500
        if(!$user=App::$user)$this->error('403 Forbidden');
        if(!isset($_POST['uid']))$this->error('500 Internal Server Error');
        $aid=Utils::clearUInt($_POST['uid']);
        if(!($author=DB::subscribeUserTo($user->id,$aid))){
            $this->error('500 Internal Server Error');
            // TODO: debug
//            header($_SERVER["SERVER_PROTOCOL"] . " 500 Internal Server Error");
//            die($err);
        }
        header('Content-type:application/json');
        die(json_encode($author));
    }

    protected function unscribe($args){
        if(!$user=App::$user)$this->error('403 Forbidden');
        if(!isset($_POST['id']))$this->error('500 Internal Server Error');
        $suid=Utils::clearUInt($_POST['id']);
        $err=DB::unscribeUserFrom($user->id,$suid);
        if($err){
//            $this->error('500 Internal Server Error');
            // TODO: debug
            header($_SERVER["SERVER_PROTOCOL"] . " 500 Internal Server Error");
            die($err);
        }
        die("Ok");
    }

    protected function del($args){
//        var_dump($_POST);exit;
        // Удалить пост
        if(!$user=App::$user)$this->error('403 Forbidden');
        if(!isset($_POST['id']))$this->error('500 Internal Server Error');
        $pid=Utils::clearUInt($_POST['id']);
//        $err=UserPost::setDeleted($pid);  // на случай реализации режима Помечания удалённым
        $err=UserPost::delete($pid);
        if($err){
            $this->error('500 Internal Server Error');
            // TODO: debug
//            header($_SERVER["SERVER_PROTOCOL"] . " 500 Internal Server Error");
//            die($err);
        }
        die("Ok");
    }

    protected function add($args){
        //        var_dump($_POST);exit;
        // Добавить или обновить пост, вернуть id поста/error 500
        if(!$user=App::$user) $this->error('403 Forbidden');
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

    protected function news($args){
        // Отдать Новые открытые сообщения всех п-лей
        // в сокращённом виде
        if(!isset($_GET['lastupdate']))$this->error('500 Internal Server Error');
        if(!isset($_GET['curpage']))$cp=0;
        else $cp=Utils::clearUInt($_GET['curpage']);
        $lu=Utils::clearStr($_GET['lastupdate']);
        if(null===($posts=DB::getNewsPosts($lu,$cp,self::PAGE_POSTS_COUNT)))$this->error('500 Internal Server Error');

        if(count($posts)<1)die('Ok');
        header('Content-type:application/json');
        $obj=new \stdClass();
        $obj->lastupdate=Utils::now();
        $obj->posts=$posts;
        die(json_encode($obj));
    }

    protected function main($args){
        // Отдать сообщения Главной ленты
        if(!isset($_GET['lastupdate'])) $this->error('500 Internal Server Error');
        if(!isset($_GET['curpage']))$cp=0;
        else $cp=Utils::clearUInt($_GET['curpage']);
        $lu=Utils::clearStr($_GET['lastupdate']);
        if(null===($posts=DB::getUserPosts($lu,$cp,self::PAGE_POSTS_COUNT,self::MAIN_USER_ID)))$this->error('500 Internal Server Error');

        if(count($posts)<1)die('Ok');
        header('Content-type:application/json');
        $obj=new \stdClass();
        $obj->lastupdate=Utils::now();
        $obj->posts=$posts;
        die(json_encode($obj));
    }

    protected function my($args){
        // Отдать сообщения Личной ленты текущего пользователя
        if(!$user=App::$user)$this->error('403 Forbidden');
        if(!isset($_GET['lastupdate']))$this->error('500 Internal Server Error');
        if(!isset($_GET['curpage']))$cp=0;
        else $cp=Utils::clearUInt($_GET['curpage']);
        $lu=Utils::clearStr($_GET['lastupdate']);
        if(null===($posts=DB::getUserPosts($lu,$cp,self::PAGE_POSTS_COUNT,$user->id,null)))$this->error('500 Internal Server Error');

        if(count($posts)<1)die('Ok');
        header('Content-type:application/json');
        $obj=new \stdClass();
        $obj->lastupdate=Utils::now();
        $obj->posts=$posts;
        die(json_encode($obj));
    }

    protected function user($args){
        // Отдать сообщения Личной ленты Конкретного пользователя
        if(!isset($_GET['uid']))$this->error('500 Internal Server Error');
        if(!isset($_GET['lastupdate']))$this->error('500 Internal Server Error');
        if(!isset($_GET['curpage']))$cp=0;
        else $cp=Utils::clearUInt($_GET['curpage']);
        $uid=Utils::clearUInt($_GET['uid']);
        $lu=Utils::clearStr($_GET['lastupdate']);
        if(null===($posts=DB::getConcreteUserPosts($lu,$cp,self::PAGE_POSTS_COUNT,$uid)))$this->error('500 Internal Server Error');
        if(count($posts)<1)die('Ok');
        header('Content-type:application/json');
        $obj=new \stdClass();
        $obj->lastupdate=Utils::now();
        $obj->posts=$posts;
        die(json_encode($obj));
    }

    protected function subscribeposts($args){
        // Отдать сообщения по подписке текущего пользователя
        if(!$user=App::$user) $this->error('403 Forbidden');
        if(!isset($_GET['lastupdate'])) $this->error('500 Internal Server Error');
        if(!isset($_GET['curpage']))$cp=0;
        else $cp=Utils::clearUInt($_GET['curpage']);
        $lu=Utils::clearStr($_GET['lastupdate']);
        if(null===($posts=DB::getUserSubscribePosts($lu,$cp,self::PAGE_POSTS_COUNT,$user->id)))$this->error('500 Internal Server Error');

        if(count($posts)<1)die("Ok");
        header('Content-type:application/json');
        $obj=new \stdClass();
        $obj->lastupdate=Utils::now();
        $obj->posts=$posts;
        die(json_encode($obj));
    }

    protected function subscribeusers($args){
        // Отдать список подписок текущего пользователя
        if(!$user=App::$user) $this->error('403 Forbidden');
        if(!isset($_GET['lastupdate'])) $this->error('500 Internal Server Error');
        if(!isset($_GET['curpage']))$cp=0;
        else $cp=Utils::clearUInt($_GET['curpage']);
        $lu=Utils::clearStr($_GET['lastupdate']);
        if(null===($posts=DB::getUserSubscribeUsers($lu,$cp,self::PAGE_POSTS_COUNT,$user->id)))$this->error('500 Internal Server Error');

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