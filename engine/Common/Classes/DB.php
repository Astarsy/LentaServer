<?php

namespace Common\Classes;

class DB{

    protected static $_pdo;

    public static function getPDO(){
        if(!self::$_pdo)self::createPDO();
        return self::$_pdo;
    }

    protected static function createPDO(){
        $db=App::$params['db'];
        self::$_pdo=new \PDO(
            'mysql:host=localhost;dbname='.$db['name'].';charset=utf8;',
            $db['user'],$db['passwd']
        );
        if(App::$params['debug'])self::$_pdo->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION);
    }

    public static function addPostComment($pid,$aid,$text){
        // Добавить комментарий, вернуть его-же, как объект
        $pdo=self::getPDO();
        $now=Utils::now();
        try{
            $pdo->beginTransaction();

            $sql="
INSERT INTO comments(author_id, post_id, created_at, text) VALUES(:ai,:pi,:ca,:te)
ON DUPLICATE KEY UPDATE `created_at`=:ca,`text`=:te";
            $stmt=$pdo->prepare($sql);
            $stmt->bindValue(':ai',$aid,\PDO::PARAM_INT);
            $stmt->bindValue(':pi',$pid,\PDO::PARAM_INT);
            $stmt->bindValue(':ca',$now,\PDO::PARAM_INT);
            $stmt->bindValue(':te',$text,\PDO::PARAM_STR);
            $stmt->execute();

            $sql="
SELECT c.*,u.id as author_id,u.name as author_name,u.avatar as author_avatar FROM comments c
  LEFT JOIN users u ON u.id=c.author_id
WHERE author_id=:ai AND post_id=:pi";
            $stmt=$pdo->prepare($sql);
            $stmt->bindValue(':ai',$aid,\PDO::PARAM_INT);
            $stmt->bindValue(':pi',$pid,\PDO::PARAM_INT);
            $stmt->execute();
            $obj=$stmt->fetch(\PDO::FETCH_ASSOC);
            unset($obj->text);

            $pdo->commit();
            return $obj;
        }catch(\PDOException $e){
            $pdo->rollback();
                        die($e->getMessage());
            return null;
        }
    }

    public static function getPostComments($lu,$cp,$op,$pid){
        // Вернуть объект новых активных комментаринв для данного поста и флаг возможности добавления
        $max_comments=5;
        $from=(int)$cp*(int)$op;
        $pdo=self::getPDO();
        try{
            $sql="
SELECT c.*,u.id as author_id,u.name as author_name,u.avatar as author_avatar FROM comments c
  LEFT JOIN users u ON u.id=c.author_id
WHERE c.post_id=:pid AND c.created_at>:lu
ORDER BY c.created_at DESC
LIMIT $from,$op
";
            $stmt=$pdo->prepare($sql);
            $stmt->bindValue(':pid',$pid,\PDO::PARAM_INT);
            $stmt->bindValue(':lu',$lu,\PDO::PARAM_STR);
            $stmt->execute();
            $items=$stmt->fetchAll(\PDO::FETCH_ASSOC);

            if(!($user=App::$user))$can_add=false;
            else{
                $uid=$user->id;
                $sql="SELECT COUNT(id)<=:mc FROM comments WHERE author_id=$uid AND post_id=$pid";
                $stmt=$pdo->prepare($sql);
                $stmt->bindValue(':mc',$max_comments,\PDO::PARAM_INT);
                $stmt->execute();
                $can_add=(bool)$stmt->fetch(\PDO::FETCH_NUM)[0];
            }

            $obj=new \stdClass();
            $obj->items=$items;
            $obj->can_add=$can_add;
            $obj->lastupdate=Utils::now();

            return $obj;
        }catch(\PDOException $e){
//                        die($e->getMessage());
            return null;
        }
    }

    public static function getNewsPosts($lu,$cp,$op,$uid=null){
        // Принять время предыдущего обновления, текущую страницу, кол-во на странице
        // вернуть массив всех новых активных постов/null

//        $from=(int)$cp*(int)$op;
        $from=0;
        $op=$op*($cp+1);

        $pdo=self::getPDO();
        try{
            // Вариант с штучным выбором постов
//            $sql="
//SELECT p.*,u.id as user_id,u.name as user_name,u.avatar as user_avatar FROM
//  (SELECT t2.* FROM
//    (SELECT *,MAX(updated_at)as time FROM posts WHERE status='active' AND ISNULL(access)
//      AND ISNULL(deleted_at)
//      AND updated_at>:lu
//        GROUP BY user_id)t1
//  LEFT JOIN posts t2 ON (t2.updated_at=t1.time AND t2.user_id=t1.user_id))p
//  LEFT JOIN users u ON u.id=p.user_id
//ORDER BY updated_at DESC
//LIMIT $from,$op
//";
            // Вариант с станлартным выбором постов
//            $sql="
//SELECT p.*,u.name as user_name,u.avatar as user_avatar FROM posts p
//  LEFT JOIN users u ON u.id=p.user_id
//WHERE ISNULL(p.deleted_at) AND (p.updated_at>=:lu OR p.created_at>=:lu)
//ORDER BY p.updated_at DESC
//LIMIT $from,$op
//";
            if(null===$uid)$union_sql='';
            else $union_sql="
UNION
SELECT p.*,u.name as user_name,u.avatar as user_avatar FROM friends f
  LEFT JOIN posts p ON p.user_id=f.friend_id
  LEFT JOIN users u ON u.id=p.user_id
WHERE f.user_id=$uid AND p.access='private' AND (p.updated_at>=:lu OR p.created_at>=:lu)
ORDER BY updated_at DESC";

            $sql="
SELECT p.*,u.name as user_name,u.avatar as user_avatar FROM posts p
  LEFT JOIN users u ON u.id=p.user_id 
WHERE ISNULL(p.deleted_at) AND p.status='active' AND ISNULL(p.access) AND (p.updated_at>=:lu OR p.created_at>=:lu)";
            $sql.=$union_sql;
            $sql.=" LIMIT $from,$op";

            $stmt=$pdo->prepare($sql);
            $stmt->bindValue(':lu',$lu,\PDO::PARAM_STR);
            $stmt->execute();
            $posts=$stmt->fetchAll(\PDO::FETCH_ASSOC);
            $posts=self::addItemsToPosts($pdo,$posts);

            // Вариант с сокращёнными итемами постов
//            $max=30;
//            foreach($posts as &$p){
//                $sql="
//SELECT id,post_id,tag,fotos_align,
//  'ico' as fotos_class,
//  IF(CHAR_LENGTH(text)>$max,CONCAT(SUBSTRING(text,1,$max),'...'),SUBSTRING(text,1,$max)) as text,
//  CHAR_LENGTH(text)
//FROM post_items WHERE post_id=".$p['id'];
//                $stmt=$pdo->prepare($sql);
//                $stmt->execute();
//                $items=$stmt->fetchAll(\PDO::FETCH_ASSOC);
//
//                foreach($items as &$item){
//                    $sql="
//SELECT f.id,f.name FROM fotos_of_post_items fi
//  LEFT JOIN fotos f ON f.id=fi.foto_id
//WHERE item_id=".$item['id'];
//                    $stmt=$pdo->prepare($sql);
//                    $stmt->execute();
//                    $item['fotos']=$stmt->fetchAll(\PDO::FETCH_ASSOC);
//                }
//                $p['items']=$items;
//            }

            // Вариант с стандартными мтемами постов
            $posts=self::addItemsToPosts($pdo,$posts);

            return $posts;
        }catch(\PDOException $e){
//            die($e->getMessage());
            return null;
        }
    }

    public static function getUserPost($pid){
        // Вернуть пост

        $pdo=self::getPDO();
        try{
            $sql="
SELECT p.*,u.name as user_name,u.avatar as user_avatar FROM posts p
  LEFT JOIN users u ON u.id=p.user_id
WHERE p.id=:pid
";
            $stmt=$pdo->prepare($sql);
            $stmt->bindValue(':pid',$pid,\PDO::PARAM_INT);
            $stmt->execute();
            $posts=$stmt->fetchAll(\PDO::FETCH_ASSOC);
            $posts=self::addItemsToPosts($pdo,$posts);
            return $posts[0];
        }catch(\PDOException $e){
//            die($e->getMessage());
            return null;
        }
    }

    public static function getUserMessage($pid){
        // Вернуть пост

        $pdo=self::getPDO();
        try{
            $sql="
SELECT p.*,u.name as user_name,u.avatar as user_avatar FROM messages p
  LEFT JOIN users u ON u.id=p.user_id
WHERE p.id=:pid
";
            $stmt=$pdo->prepare($sql);
            $stmt->bindValue(':pid',$pid,\PDO::PARAM_INT);
            $stmt->execute();
            $posts=$stmt->fetchAll(\PDO::FETCH_ASSOC);
            $posts=self::addItemsToMessage($pdo,$posts);
            return $posts[0];
        }catch(\PDOException $e){
//            die($e->getMessage());
            return null;
        }
    }

    public static function getToUserIdByMsgId($mid){
        // Вернуть id/null

        $pdo=self::getPDO();
        try{
            $sql="SELECT user_id FROM  messages WHERE id=:mid";
            $stmt=$pdo->prepare($sql);
            $stmt->bindValue(':mid',$mid,\PDO::PARAM_INT);
            $stmt->execute();
            $uid=$stmt->fetch(\PDO::FETCH_NUM)[0];
            return $uid;
        }catch(\PDOException $e){
//            die($e->getMessage());
            return null;
        }
    }

    public static function getUserPosts($lu,$cp,$op,$uid=null,$status='active'){
        // Принять время предыдущего обновления, текущую страницу, кол-во на странице
        // вернуть массив всех или новых постов/null

        if($status)$stsql=" AND p.status='$status'";
        else $stsql='';

//        $from=(int)$cp*(int)$op;
        $from=0;
        $op=$op+$op*$cp;

        if(null===$uid)$and='';
        else{
            if(!is_array($uid)) $and='=' . $uid;
            else{
                $s=implode(',', $uid);
                $and='IN(' . $s . ')';
            }
        }
        $pdo=self::getPDO();
        try{
            $sql="
SELECT p.*,u.name as user_name,u.avatar as user_avatar FROM posts p
  LEFT JOIN users u ON u.id=p.user_id
WHERE ISNULL(p.deleted_at) AND (p.updated_at>=:lu OR p.created_at>=:lu)$stsql AND p.user_id $and
ORDER BY p.updated_at DESC
LIMIT $from,$op
";
            $stmt=$pdo->prepare($sql);
            $stmt->bindValue(':lu',$lu,\PDO::PARAM_STR);
            $stmt->execute();
            $posts=$stmt->fetchAll(\PDO::FETCH_ASSOC);
            $posts=self::addItemsToPosts($pdo,$posts);
            return $posts;
        }catch(\PDOException $e){
            die($e->getMessage());
//            return null;
        }
    }

    public static function getMyPosts($lu,$cp,$op,$uid){
        // вернуть массив всех или новых постов Текущего п-ля/null
        $from=0;
        $op=$op*($cp+1);
        $pdo=self::getPDO();
        try{
            $sql="
SELECT p.*,u.name as user_name,u.avatar as user_avatar FROM posts p
  LEFT JOIN users u ON u.id=p.user_id
WHERE p.user_id=$uid AND ISNULL(p.deleted_at) AND (p.updated_at>=:lu OR p.created_at>=:lu)
ORDER BY p.updated_at DESC
LIMIT $from,$op
";
            $stmt=$pdo->prepare($sql);
            $stmt->bindValue(':lu',$lu,\PDO::PARAM_STR);
            $stmt->execute();
            $posts=$stmt->fetchAll(\PDO::FETCH_ASSOC);
            $posts=self::addItemsToPosts($pdo,$posts);
            return $posts;
        }catch(\PDOException $e){
//            die($e->getMessage());
            return null;
        }
    }

    public static function getFriendPosts($lu,$cp,$op,$uid){
        // вернуть массив постов Друзей Текущего п-ля/null
        $from=0;
        $op=$op*($cp+1);
        $pdo=self::getPDO();
        try{
            $sql="
SELECT p.*,u.name as user_name,u.avatar as user_avatar FROM friends f
  LEFT JOIN posts p ON p.user_id=f.friend_id
  LEFT JOIN users u ON u.id=f.friend_id
WHERE f.user_id=$uid AND f.status='active' AND ISNULL(p.deleted_at) AND (p.updated_at>=:lu OR p.created_at>=:lu)
ORDER BY p.updated_at DESC
LIMIT $from,$op
";
            $stmt=$pdo->prepare($sql);
            $stmt->bindValue(':lu',$lu,\PDO::PARAM_STR);
            $stmt->execute();
            $posts=$stmt->fetchAll(\PDO::FETCH_ASSOC);
            $posts=self::addItemsToPosts($pdo,$posts);
            return $posts;
        }catch(\PDOException $e){
//            die($e->getMessage());
            return null;
        }
    }

    public static function getMessages($lu,$cp,$op,$uid){
        // вернуть массив личные сообщения текущего пользователя/null
        // формат должен соответствовать формату Tab, т.е как посты
        $from=0;
        $op=$op*($cp+1);
        $pdo=self::getPDO();
        try{
            $sql="
SELECT p.*,u.name as user_name,u.avatar as user_avatar,tu.name as to_user_name,tu.avatar as to_user_avatar FROM messages p
  LEFT JOIN users u ON u.id=p.user_id
  LEFT JOIN users tu ON tu.id=p.to_user_id
WHERE p.to_user_id=$uid AND ISNULL(p.deleted_at) AND (p.updated_at>=:lu OR p.created_at>=:lu)
UNION 
SELECT p.*,u.name as user_name,u.avatar as user_avatar,tu.name as to_user_name,tu.avatar as to_user_avatar FROM messages p
  LEFT JOIN users u ON u.id=p.user_id
  LEFT JOIN users tu ON tu.id=p.to_user_id
WHERE p.user_id=$uid AND ISNULL(p.deleted_at) AND (p.updated_at>=:lu OR p.created_at>=:lu)
ORDER BY updated_at DESC
LIMIT $from,$op
";
            $stmt=$pdo->prepare($sql);
            $stmt->bindValue(':lu',$lu,\PDO::PARAM_STR);
            $stmt->execute();
            $posts=$stmt->fetchAll(\PDO::FETCH_ASSOC);
            $posts=self::addItemsToMessage($pdo,$posts);
            return $posts;
        }catch(\PDOException $e){
//            die($e->getMessage());
            return null;
        }
    }

    protected static function addItemsToMessage($pdo,$posts){
        // Добавить к постам итемы и фото, вернуть массив полстов/null
        foreach($posts as &$p){
            $sql="SELECT * FROM message_items WHERE post_id=".$p['id'];
            $stmt=$pdo->prepare($sql);
            $stmt->execute();
            $items=$stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach($items as &$item){
                $sql="
SELECT f.id,f.name FROM fotos_of_message_items fi
  LEFT JOIN msgfotos f ON f.id=fi.foto_id
WHERE item_id=".$item['id'];
                $stmt=$pdo->prepare($sql);
                $stmt->execute();
                $item['fotos']=$stmt->fetchAll(\PDO::FETCH_ASSOC);
            }
            $p['items']=$items;
        }
        return $posts;
    }

    protected static function addItemsToPosts($pdo,$posts){
        // Добавить к постам итемы и фото, вернуть массив полстов/null
        foreach($posts as &$p){
            $sql="SELECT * FROM post_items WHERE post_id=".$p['id'];
            $stmt=$pdo->prepare($sql);
            $stmt->execute();
            $items=$stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach($items as &$item){
                $sql="
SELECT f.id,f.name FROM fotos_of_post_items fi
  LEFT JOIN fotos f ON f.id=fi.foto_id
WHERE item_id=".$item['id'];
                $stmt=$pdo->prepare($sql);
                $stmt->execute();
                $item['fotos']=$stmt->fetchAll(\PDO::FETCH_ASSOC);
            }
            $p['items']=$items;
        }
        return $posts;
    }

    public static function getConcreteUserPosts($lu,$cp,$op,$uid){
        // Принять время предыдущего обновления, текущую страницу, кол-во на странице
        // вернуть массив всех или новых постов Конкретного пользователя/null

//        $from=(int)$cp*(int)$op;
        $from=0;
        $op=$op*($cp+1);

        $pdo=self::getPDO();
        try{
            $sql="
SELECT p.*,u.name as user_name,u.avatar as user_avatar FROM posts p
  LEFT JOIN users u ON u.id=p.user_id
WHERE p.user_id=$uid 
  AND ISNULL(p.deleted_at) 
  AND (p.updated_at>=:lu OR p.created_at>=:lu)
  AND ISNULL(p.access)
ORDER BY p.updated_at DESC
LIMIT $from,$op
";
            $stmt=$pdo->prepare($sql);
            $stmt->bindValue(':lu',$lu,\PDO::PARAM_STR);
            $stmt->execute();
            $posts=$stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Add items
            foreach($posts as &$p){
                $sql="SELECT * FROM post_items WHERE post_id=".$p['id'];
                $stmt=$pdo->prepare($sql);
                $stmt->execute();
                $items=$stmt->fetchAll(\PDO::FETCH_ASSOC);

                foreach($items as &$item){
                    $sql="
SELECT f.id,f.name FROM fotos_of_post_items fi
  LEFT JOIN fotos f ON f.id=fi.foto_id
WHERE item_id=".$item['id'];
                    $stmt=$pdo->prepare($sql);
                    $stmt->execute();
                    $item['fotos']=$stmt->fetchAll(\PDO::FETCH_ASSOC);
                }
                $p['items']=$items;
            }

            return $posts;
        }catch(\PDOException $e){
            die($e->getMessage());
//            return null;
        }
    }

    public static function subscribeUserTo($uid,$author_id){
        // Подписать п-ля под автора, вернуть автора/null
        $pdo=self::getPDO();
        $pdo->beginTransaction();
        try{
            $sql="INSERT INTO subscribes(user_id, subscribe_at_id, created_at) VALUES($uid,$author_id,NOW())";
            $stmt=$pdo->prepare($sql);
            $stmt->execute();

            $sql="
SELECT u.id,u.name,u.avatar
  ,(SELECT COUNT(p.id)FROM posts p WHERE p.user_id=u.id)as post_count
    FROM users u
WHERE u.id=$author_id";
            $stmt=$pdo->prepare($sql);
            $stmt->execute();
            $author=$stmt->fetch(\PDO::FETCH_ASSOC);

            $pdo->commit();

            return $author;
        }catch(\PDOException $e){
            $pdo->rollback();
            return null;
        }
    }

    public static function friendUserTo($uid,$fid){
        // Добавить нового не активного друга (заявку), вернуть Ошибку/null
        $pdo=self::getPDO();
        try{
            $sql="INSERT INTO friends(user_id, friend_id, created_at, status) VALUES(:uid,:fid,NOW(),'new')";
            $stmt=$pdo->prepare($sql);
            $stmt->bindValue(':uid',$fid,\PDO::PARAM_INT);
            $stmt->bindValue(':fid',$uid,\PDO::PARAM_INT);
            $stmt->execute();
            return null;
        }catch(\PDOException $e){
            return $e->getMessage();
        }
    }

    public static function setUserFriendStatus($uid,$fid,$st){
        // Сохранить статус друга, вернуть Ошибку/null
        $pdo=self::getPDO();
        try{
            $sql="UPDATE friends SET status=:st WHERE user_id=:ui AND friend_id=:fi";
            $stmt=$pdo->prepare($sql);
            $stmt->bindValue(':st',$st,\PDO::PARAM_STR);
            $stmt->bindValue(':ui',$uid,\PDO::PARAM_INT);
            $stmt->bindValue(':fi',$fid,\PDO::PARAM_INT);
            $stmt->execute();
            return null;
        }catch(\PDOException $e){
            return $e->getMessage();
        }
    }

    public static function getUserSubscribeUsers($lu,$cp,$op,$uid){
        // Принять время предыдущего обновления, текущую страницу, кол-во на странице
        // вернуть массив всех или новых друзей/null

        $pdo=self::getPDO();
        try{
            $sql="
SELECT u.id,u.name,u.avatar
  ,(SELECT COUNT(p.id)FROM posts p WHERE p.user_id=s.subscribe_at_id)as post_count
    FROM subscribes s
  LEFT JOIN users u ON u.id=s.subscribe_at_id
WHERE s.user_id=$uid
ORDER BY s.created_at
";
            $stmt=$pdo->prepare($sql);
            $stmt->execute();
            $posts=$stmt->fetchAll(\PDO::FETCH_ASSOC);
            return $posts;
        }catch(\PDOException $e){
//            die($e->getMessage());
                        return null;
        }
    }

    public static function getUserFriendUsers($lu,$cp,$op,$uid,$st){
        // вернуть массив друзей/null

        $pdo=self::getPDO();
        try{
            $sql="
SELECT u.id,u.name,u.avatar
  ,(SELECT COUNT(p.id)FROM posts p WHERE p.user_id=f.friend_id)as post_count
    FROM friends f
  LEFT JOIN users u ON u.id=f.friend_id
WHERE f.user_id=$uid AND f.status='$st'
ORDER BY f.created_at
";
            $stmt=$pdo->prepare($sql);
            $stmt->execute();
            $posts=$stmt->fetchAll(\PDO::FETCH_ASSOC);
            return $posts;
        }catch(\PDOException $e){
//            die($e->getMessage());
                        return null;
        }
    }

    public static function unscribeUserFrom($uid,$suid){
        // Отписать п-ля от автора, вернуть ошибку/null
        $pdo=self::getPDO();
        try{
            $sql="DELETE FROM subscribes WHERE user_id=$uid AND subscribe_at_id=$suid";
            $stmt=$pdo->prepare($sql);
            $stmt->execute();
        }catch(\PDOException $e){
            return $e->getMessage();
        }
        return null;
    }

    public static function unfriendUserFrom($uid,$suid){
        // Удалить из друзей, вернуть ошибку/null
        $pdo=self::getPDO();
        try{
            $sql="UPDATE friends SET status='deleted' WHERE user_id=$uid AND friend_id=$suid";
            $stmt=$pdo->prepare($sql);
            $stmt->execute();
        }catch(\PDOException $e){
            return $e->getMessage();
        }
        return null;
    }

    public static function getUserSubscribePosts($lu,$cp,$op,$uid){
        // вернуть массив посты по Подписке/null
        $from=0;
        $op=$op*($cp+1);
        $pdo=self::getPDO();
        try{
            $sql="
SELECT p.*,u.name as user_name,u.avatar as user_avatar FROM subscribes s
  LEFT JOIN posts p ON p.user_id=s.subscribe_at_id
  LEFT JOIN users u ON u.id=s.subscribe_at_id
WHERE s.user_id=$uid AND ISNULL(p.deleted_at) AND (p.updated_at>=:lu OR p.created_at>=:lu)
ORDER BY p.updated_at DESC
LIMIT $from,$op
";
            $stmt=$pdo->prepare($sql);
            $stmt->bindValue(':lu',$lu,\PDO::PARAM_STR);
            $stmt->execute();
            $posts=$stmt->fetchAll(\PDO::FETCH_ASSOC);
            $posts=self::addItemsToPosts($pdo,$posts);
            return $posts;
        }catch(\PDOException $e){
            //            die($e->getMessage());
            return null;
        }
    }

    public static function getUser($id){
        $pdo=self::getPDO();
        try{
            $sql="
SELECT u.*,r.name as role FROM users u
  LEFT JOIN roles r ON r.id=u.role_id
WHERE u.id=:id
";
            $stmt=$pdo->prepare($sql);
            $stmt->bindParam(':id',$id);
            $stmt->execute();
            $res=$stmt->fetch(\PDO::FETCH_OBJ);
            if(empty($res))$res=null;
        }catch(\PDOException $e){
            $res=null;
        }
        return $res;
    }

    /**
     * @param string $table table name
     * @param string $where WHERE clause like 'WHERE id=0'
     * @param string $order ORDER clause like 'ORDER BY name ASC'
     * @param int $fetch
     * @return mixed array of objects or null if error
     */
    public static function getItemsBy($table,$where='',$order='',$fetch=\PDO::FETCH_OBJ){
        // Усовершенствованный getItems для выборки по параметрам
        $pdo=self::getPDO();
        try{
            $sql="SELECT * FROM $table $where $order";
            $stmt=$pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll($fetch);
        }catch(\PDOException $e){return null;}
    }

    /**
     * @param string $table table name
     * @param string $where WHERE clause like 'WHERE id=0'
     * @param string $order ORDER clause like 'ORDER BY name ASC'
     * @param int $fetch
     * @return mixed object or null if there are not or error
     */
    public static function getItemBy($table,$where='',$order='',$fetch=\PDO::FETCH_OBJ){
        $pdo=self::getPDO();
        try{
            $sql="SELECT * FROM $table $where $order";
            $stmt=$pdo->prepare($sql);
            $stmt->execute();
            $res=$stmt->fetchAll($fetch);
            if(count($res)>0)return $res[0];
            return null;
        }catch(\PDOException $e){return null;}
    }
}