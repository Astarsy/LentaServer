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
                $sql="SELECT COUNT(id)<=0 FROM comments WHERE author_id=$uid AND post_id=$pid";
                $stmt=$pdo->prepare($sql);
                $stmt->execute();
                $can_add=$stmt->fetch(\PDO::FETCH_NUM)[0];
            }

            $obj=new \stdClass();
            $obj->items=$items;
            $obj->can_add=(bool)$can_add;
            $obj->lastupdate=Utils::now();

            return $obj;
        }catch(\PDOException $e){
                        die($e->getMessage());
            return null;
        }
    }

    public static function getNewsPosts($lu,$cp,$op){
        // Принять время предыдущего обновления, текущую страницу, кол-во на странице
        // вернуть массив всех новых активных постов/null
        $from=(int)$cp*(int)$op;
        $pdo=self::getPDO();
        try{
            $sql="
SELECT p.*,u.id as user_id,u.name as user_name,u.avatar as user_avatar FROM
  (SELECT t2.* FROM
    (SELECT *,MAX(updated_at)as time FROM posts WHERE status='active' AND ISNULL(access)
      AND ISNULL(deleted_at)
      AND updated_at>:lu
        GROUP BY user_id)t1
  LEFT JOIN posts t2 ON (t2.updated_at=t1.time AND t2.user_id=t1.user_id))p
  LEFT JOIN users u ON u.id=p.user_id
ORDER BY updated_at DESC
LIMIT $from,$op
";
            $stmt=$pdo->prepare($sql);
            $stmt->bindValue(':lu',$lu,\PDO::PARAM_STR);
            $stmt->execute();
            $posts=$stmt->fetchAll(\PDO::FETCH_ASSOC);
            $posts=self::addItemsToPosts($pdo,$posts);

            $max=30;
            foreach($posts as &$p){
                $sql="
SELECT id,post_id,tag,fotos_align,
  'ico' as fotos_class,
  IF(CHAR_LENGTH(text)>$max,CONCAT(SUBSTRING(text,1,$max),'...'),SUBSTRING(text,1,$max)) as text,
  CHAR_LENGTH(text)
FROM post_items WHERE post_id=".$p['id'];
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
            die($e->getMessage());
//            return null;
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

        $from=(int)$cp*(int)$op;
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
            $sql="INSERT INTO subscribes(user_id, subscribe_at_id, created_at, last_view_at) VALUES($uid,$author_id,NOW(),NOW())";
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
            die($e->getMessage());
            //            return null;
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

    public static function getUserSubscribePosts($lu,$cp,$op,$uid){
        // Принять время предыдущего обновления, текущую страницу, кол-во на странице
        // вернуть массив всех или новых постов друзей/null

        $pdo=self::getPDO();
        try{
            $sql="
SELECT p.*,u.name as user_name,u.avatar as user_avatar FROM posts p
  LEFT JOIN subscribes s ON s.subscribe_at_id=p.user_id 
  LEFT JOIN users u ON u.id=p.user_id 
    AND IF(ISNULL(p.access),TRUE,s.access=p.access) 
    AND ISNULL(p.deleted_at) AND p.status='active'
WHERE s.user_id=$uid;
";
            $stmt=$pdo->prepare($sql);
            $stmt->execute();
            $posts=$stmt->fetchAll(\PDO::FETCH_ASSOC);
            $posts=self::addItemsToPosts($pdo,$posts);
            return $posts;
        }catch(\PDOException $e){
            die($e->getMessage());
//            return null;
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