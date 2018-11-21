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

    public static function getUserPosts($lu,$cp,$op,$uid,$status='active'){
        // Принять время предыдущего обновления, текущую страницу, кол-во на странице
        // вернуть массив всех или новых постов/null

        if($status)$stsql=" AND p.status='$status'";
        else $stsql='';

        $from=(int)$cp*(int)$op;

        if(!is_array($uid))$and='='.$uid;
        else{
            $s=implode(',',$uid);
            $and='IN('.$s.')';
        }

        $pdo=self::getPDO();
        try{
            $sql="
SELECT p.*,u.name as user_name FROM posts p
  LEFT JOIN users u ON u.id=p.user_id
WHERE ISNULL(p.deleted_at) AND (p.updated_at>=:lu OR p.created_at>=:lu)$stsql AND p.user_id $and
ORDER BY p.updated_at DESC
LIMIT $from,$op
";
            $stmt=$pdo->prepare($sql);
            $stmt->bindValue(':lu',$lu,\PDO::PARAM_STR);
            $stmt->execute();
            $posts=$stmt->fetchAll(\PDO::FETCH_ASSOC);

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

    public static function getUserFrendsPosts($lu,$cp,$op,$uid){
        // Принять время предыдущего обновления, текущую страницу, кол-во на странице
        // вернуть массив всех или новых постов друзей/null

        $pdo=self::getPDO();
        try{
            $sql="
SELECT u.id FROM friends f
  LEFT JOIN users u ON u.id=f.friend_id
WHERE f.user_id=$uid
";
            $stmt=$pdo->prepare($sql);
            $stmt->execute();
            $res=$stmt->fetchAll(\PDO::FETCH_NUM);
            $fids=[];
            foreach($res as $i)$fids[]=$i[0];
            return self::getUserPosts($lu,$cp,$op,$fids);
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