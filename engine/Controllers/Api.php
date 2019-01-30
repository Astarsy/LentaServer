<?php

namespace Controllers;

use Common\Classes\App;
use Common\Classes\DB;
use Common\Classes\UserMessage;
use Common\Classes\UserPost;
use Common\Classes\Utils;

class Api extends Base{

    const PAGE_POSTS_COUNT=5;
    const COMMENTS_COUNT=20;
    const MAIN_USER_ID=1;
    const MAX_ITEMS_COUNT=2;

    protected function saveprofile($args){
        // Сохранить профиль
        if(!$user=App::$user)$this->error('403 Forbidden');
        if(isset($_POST['name']))$user->name=Utils::clearStr($_POST['name'],20);
        if(isset($_POST['about']))$user->about=Utils::clearStr($_POST['about'],200);
        if(!empty($_FILES))die('saving fotos');
        if($err=$user->save()){
            $this->error('500 Internal Server Error');
        }
//        var_dump($user); // TODO: DEBUG
//        var_dump($_FILES);
    }

    protected function addcomment($args){
        // Добавить и вернуть новый комментарий в виде объекта
        if(!$user=App::$user)$this->error('403 Forbidden');
        if(!isset($_GET['lastupdate']) || !isset($_GET['pid']) || !isset($_GET['text']))$this->error('500 Internal Server Error');
        if(!isset($_GET['curpage']))$cp=0;
        $pid=Utils::clearUInt($_GET['pid']);
        $text=Utils::clearStr($_GET['text'],App::MAX_TEXT_LENGTH);
        if(!($comment=DB::addPostComment($pid,$user->id,$text)))$this->error('500 Internal Server Error');
        else{
            header('Content-type:application/json');
            die(json_encode($comment));
        }
    }

    protected function getcomments($args){
        // Вернуть массив комментариев для данного поста
        if(!isset($_GET['lastupdate']) || !isset($_GET['pid']))$this->error('500 Internal Server Error');
        if(!isset($_GET['curpage']))$cp=0;
        else $cp=Utils::clearUInt($_GET['curpage']);
        $lu=Utils::clearStr($_GET['lastupdate']);
        $pid=Utils::clearUInt($_GET['pid']);
        $obj=DB::getPostComments($lu,$cp,self::COMMENTS_COUNT,$pid);
        header('Content-type:application/json');
        die(json_encode($obj));
    }

    protected function subscribe($args){
        // Принять uid, подписать п-ля, вернуть п-ля - автора/error 500
        if(!$user=App::$user)$this->error('403 Forbidden');
        if(!isset($_POST['uid']))$this->error('500 Internal Server Error');
        $aid=Utils::clearUInt($_POST['uid']);
        if(!($author=DB::subscribeUserTo($user->id,$aid))){
            $this->error('500 Internal Server Error');

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

    protected function unfriend($args){
        if(!$user=App::$user)$this->error('403 Forbidden');
        if(!isset($_POST['id']))$this->error('500 Internal Server Error');
        $suid=Utils::clearUInt($_POST['id']);
        $err=DB::unfriendUserFrom($user->id,$suid);
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

//            header($_SERVER["SERVER_PROTOCOL"] . " 500 Internal Server Error");
//            die($err);
        }
        die("Ok");
    }

    protected function add($args){
        // Добавить или обновить пост, вернуть пост/error 500
        if(!$user=App::$user) $this->error('403 Forbidden');
        if(!isset($_POST['data']))$this->error();
        $data=json_decode($_POST['data']);

//        var_dump($data);
//        exit;

        if(null===$post=UserPost::create($data))$this->error();

        if($err=$post->save()){
            $this->error('500 Internal Server Error');
            // TODO: debug
            //            header($_SERVER["SERVER_PROTOCOL"] . " 500 Internal Server Error");
            //            die($err);
        }
        header('Content-type:application/json');
        die(json_encode($post->getPost()));
    }

    protected function news($args){
        // Отдать Новые открытые сообщения всех п-лей
        if(!isset($_GET['lastupdate']))$this->error('500 Internal Server Error');
        if(!isset($_GET['curpage']))$cp=0;
        else $cp=Utils::clearUInt($_GET['curpage']);
        $lu=Utils::clearStr($_GET['lastupdate']);
        $pc=Utils::clearUInt($_GET['postcount']);
        if(App::$user)$uid=App::$user->id;
        else $uid=null;
        if(null===($posts=DB::getNewsPosts($lu,$cp,self::PAGE_POSTS_COUNT,$uid)))$this->error('500 Internal Server Error');
        if(count($posts)===$pc)die('Ok');
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
        if(!App::$user)$this->error('403 Forbidden');
        if(!isset($_GET['lastupdate']))$this->error('500 Internal Server Error');
        if(!isset($_GET['curpage']))$cp=0;
        else $cp=Utils::clearUInt($_GET['curpage']);
        $lu=Utils::clearStr($_GET['lastupdate']);
        if(null===($posts=DB::getMyPosts($lu,$cp,self::PAGE_POSTS_COUNT,App::$user->id)))$this->error('500 Internal Server Error');

        if(count($posts)<1)die('Ok');
        header('Content-type:application/json');
        $obj=new \stdClass();
        $obj->lastupdate=Utils::now();
        $obj->posts=$posts;
        die(json_encode($obj));
    }

    protected function friendposts($args){
        // Отдать сообщения Друзей текущего пользователя
        if(!App::$user)$this->error('403 Forbidden');
        if(!isset($_GET['lastupdate']))$this->error('500 Internal Server Error');
        if(!isset($_GET['curpage']))$cp=0;
        else $cp=Utils::clearUInt($_GET['curpage']);
        $lu=Utils::clearStr($_GET['lastupdate']);
        if(null===($posts=DB::getFriendPosts($lu,$cp,self::PAGE_POSTS_COUNT,App::$user->id)))$this->error('500 Internal Server Error');

        if(count($posts)<1)die('Ok');
        header('Content-type:application/json');
        $obj=new \stdClass();
        $obj->lastupdate=Utils::now();
        $obj->posts=$posts;
        die(json_encode($obj));
    }

    protected function messages($args){
        // Отдать личные сообщения текущего пользователя
        if(!App::$user)$this->error('403 Forbidden');
        if(!isset($_GET['lastupdate']))$this->error('500 Internal Server Error');
        if(!isset($_GET['curpage']))$cp=0;
        else $cp=Utils::clearUInt($_GET['curpage']);
        $lu=Utils::clearStr($_GET['lastupdate']);
        if(null===($posts=DB::getMessages($lu,$cp,self::PAGE_POSTS_COUNT,App::$user->id)))$this->error('500 Internal Server Error');

        if(count($posts)<1)die('Ok');
        header('Content-type:application/json');
        $obj=new \stdClass();
        $obj->lastupdate=Utils::now();
        $obj->posts=$posts;
        die(json_encode($obj));
    }

    protected function addmsg($args){
        // Добавить или обновить сообщение, вернуть сообщение/error 500
        if(!$user=App::$user) $this->error('403 Forbidden');
        if(!isset($_POST['data']))$this->error();
        $data=json_decode($_POST['data']);

        //        var_dump($data);
        //        exit;

        if(null===$post=UserMessage::create($data))$this->error();

        if($err=$post->save()){
            //            $this->error('500 Internal Server Error');
            header($_SERVER["SERVER_PROTOCOL"] . " 500 Internal Server Error");
            die($err);
        }
        header('Content-type:application/json');
        die(json_encode($post->getPost()));
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
        if(!App::$user)$this->error('403 Forbidden');
        if(!isset($_GET['lastupdate']))$this->error('500 Internal Server Error');
        if(!isset($_GET['curpage']))$cp=0;
        else $cp=Utils::clearUInt($_GET['curpage']);
        $lu=Utils::clearStr($_GET['lastupdate']);
        if(null===($posts=DB::getUserSubscribePosts($lu,$cp,self::PAGE_POSTS_COUNT,App::$user->id)))$this->error('500 Internal Server Error');

        if(count($posts)<1)die('Ok');
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

    protected function friendusers($args){
        // Отдать список Друзей текущего пользователя
        if(!$user=App::$user) $this->error('403 Forbidden');
        if(!isset($_GET['lastupdate'])) $this->error('500 Internal Server Error');
        if(!isset($_GET['curpage']))$cp=0;
        else $cp=Utils::clearUInt($_GET['curpage']);
        $lu=Utils::clearStr($_GET['lastupdate']);
        if(null===($posts=DB::getUserFriendUsers($lu,$cp,self::PAGE_POSTS_COUNT,$user->id)))$this->error('500 Internal Server Error');

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