<?php
/**
    Пост обычного пользователя
 */

namespace Common\Classes;


class UserPost{

    protected static $_iids;

    protected $_pdo,$_images;

    public $_data;

    public function __construct($data){
        if(get_class()!==get_called_class()){
            throw new \Exception("Use a fabric method 'create' instead.");
        }

        $this->_data=$data;
        $this->_pdo=DB::getPDO();
    }

    public static function create($data){
        // Фабричный метод - проверить все данные, создать и вернуть объект/null

        // clear all data
        $mag_start_data=App::$params['mag_start_data'];
        if(!isset($data->items)
            || !is_array($data->items)
            || count($data->items)>$mag_start_data['max_post_items_count']
            || count($data->items)===0)return null;
        $data->bgci=Utils::clearUInt($data->bgci);
        if($data->bgci>=count($mag_start_data['colors']))return null;
        if(isset($data->access) && $data->access)$data->access=Utils::clearStr($data->access,11);
        else $data->access=null;
        if(isset($data->id))$data->id=Utils::clearUInt($data->id);
        $f_c=0;
        $iof=[];
        $foto_ids=[];
        foreach($data->items as &$item){
            if(isset($item->id))self::$_iids[]=$item->id;
            if(isset($item->text)&&$item->text)$item->text=Utils::clearStr($item->text,$mag_start_data['max_post_item_text_length']);
            else $item->text=null;
            if(isset($item->tag)&&$item->tag)$item->tag=Utils::clearStr($item->tag,20);
            else $item->tag=null;
            if(isset($item->fotos_class)&&$item->fotos_class)$item->fotos_class=Utils::clearStr($item->fotos_class,20);
            else $item->fotos_class=null;
            if(isset($item->fotos_align)&&$item->fotos_align)$item->fotos_align=Utils::clearStr($item->fotos_align,20);
            else $item->fotos_align=null;
            if(isset($item->fotos) && is_array($item->fotos)){
                foreach($item->fotos as &$foto){
                    $foto->id=Utils::clearUInt($foto->id);
                    $foto->name=Utils::clearStr($foto->name,100);
                }
                foreach($item->foto_ids as &$id){
                    $foto_ids[]=Utils::clearUInt($id);
                }
            }
            if(isset($item->files) && is_array($item->files)){
                foreach($item->files as &$file){
                    $file=Utils::clearStr($file, 100);
                    $iof[]=Utils::clearUInt($data->item_of_file[$f_c]);
                    $f_c++;
                }
            }
        }
        $data->foto_ids=$foto_ids;
        $data->item_of_file=$iof;

        // create an instance
        $user_post=new self($data);

        return $user_post;
    }

    public function save(){
        // Сохранить пост, итемы, фото, вернуть ошибку/null
        try{
            $this->_pdo->beginTransaction();

            if(isset($this->_data->id))$old_files=$this->deleteUnusedPostComponents();
            else $old_files=null;

            // save new files
            if(!empty($_FILES)){
                $this->_images=new UserImageFiles($this->_pdo,$this->_data->item_of_file);
                $this->_images->save();
            }

            $this->insert_update();
            if($old_files)self::deleteOldFiles($old_files);
            $this->_pdo->commit();

        }catch(\PDOException $e){
            $this->_pdo->rollback();
            if(isset($this->_images))$this->_images->delete();
            return $e->getMessage();
        }

        return null;
    }

    protected function deleteUnusedPostComponents(){
        // Удалить не используемые в посте фото из БД
        // Вернуть подлежащие удалению файлы в виде массива объектов/null
        $pid=$this->_data->id;
        if(is_array(self::$_iids))$existed_iids=implode(',',self::$_iids);
        else $existed_iids='';
        $existed_fids=implode(',',$this->_data->foto_ids);
        $sql="
DROP TABLE IF EXISTS tmp1;
CREATE TEMPORARY TABLE tmp1(
  id INT(11),
  name TEXT
);
INSERT INTO tmp1(id,name) SELECT id,name FROM fotos WHERE id IN(
  SELECT fopi.foto_id FROM fotos_of_post_items fopi
    LEFT JOIN post_items pi ON pi.id=fopi.item_id
  WHERE pi.post_id=$pid
) AND id NOT IN ($existed_fids);
DELETE FROM fotos WHERE id IN(SELECT id FROM tmp1);
";
        $stmt=$this->_pdo->prepare($sql);
        $stmt->execute();

        $stmt=$this->_pdo->prepare("
DELETE FROM post_items WHERE post_id=$pid AND id  NOT IN($existed_iids);");
        $stmt->execute();

        $stmt=$this->_pdo->prepare("SELECT * FROM tmp1;");
        $stmt->execute();

        $fotos=$stmt->fetchAll(\PDO::FETCH_OBJ);
        return $fotos;
    }

    protected function insert_update(){
        if(isset($this->_data->id))$pid=$this->_data->id;
        else $pid='DEFAULT';
        $sql="
INSERT INTO posts (`id`,`user_id`,`created_at`,`bgci`,`status`,`access`)
  VALUES ($pid,:ui,:now,:bg,'new',:ac)
ON DUPLICATE KEY UPDATE `bgci`=:bg,`updated_at`=NOW(),status=:us,access=:ac";
        $stmt=$this->_pdo->prepare($sql);
        $stmt->bindValue(':ui',App::$user->id);
        $stmt->bindValue(':us',App::$user->default_post_status);
        $stmt->bindValue(':now',Utils::now());
        $stmt->bindValue(':bg',$this->_data->bgci);
        $stmt->bindValue(':ac',$this->_data->access);
        $stmt->execute();

        if($pid=='DEFAULT'){
            $stmt=$this->_pdo->prepare("SELECT LAST_INSERT_ID()");
            $stmt->execute();
            $pid=$stmt->fetch(\PDO::FETCH_NUM)[0];
            $this->_data->id=$pid;
        }

        foreach($this->_data->items as &$item){
//            if(isset($item->id))throw new \Exception('Unexpected item prop - id');
            if(isset($item->id))$iid=$item->id;
            else $iid='DEFAULT';
            $sql="
INSERT INTO post_items(`id`,`post_id`,`tag`,`text`,`fotos_class`,`fotos_align`)
  VALUES($iid,:pi,:ta,:tx,:fc,:fa)
ON DUPLICATE KEY UPDATE `tag`=:ta,`text`=:tx,`fotos_class`=:fc,`fotos_align`=:fa";
            $stmt=$this->_pdo->prepare($sql);
            $stmt->bindValue(':pi',$pid);
            $stmt->bindValue(':ta',$item->tag);
            $stmt->bindValue(':tx',$item->text);
            $stmt->bindValue(':fc',$item->fotos_class);
            $stmt->bindValue(':fa',$item->fotos_align);
            $stmt->execute();

            if($iid=='DEFAULT'){
                $stmt=$this->_pdo->prepare("SELECT LAST_INSERT_ID()");
                $stmt->execute();
                $iid=$stmt->fetch(\PDO::FETCH_NUM)[0];
                $item->id=$iid;
            }
        }

        if(isset($this->_images)){
            foreach($this->_images->fotos as &$foto){
                $sql="
INSERT INTO fotos_of_post_items (`item_id`,`foto_id`,`class`)
VALUES(:ii,:fi,:cl)";
                $stmt=$this->_pdo->prepare($sql);
                $cur_item=$this->_data->items[$foto['itemIndex']];
                $stmt->bindValue(':ii', $cur_item->id);
                $stmt->bindValue(':fi', $foto['id']);
                $stmt->bindValue(':cl', $cur_item->fotos_class);
                $stmt->execute();
            }
        }
    }

    public static function deleteOldFiles($files){
        // Удалить файлы изображений всех типо-размеров
        foreach($files as $file){
            FotoFile::delete($file->name);
        }
    }

    public static function setDeleted($pid){
        // Установить текущее время удаления поста, вернуть ошибку/null
        $uid=App::$user->id;
        $pdo=DB::getPDO();
        try{
            $sql="UPDATE posts SET deleted_at=NOW() WHERE id=$pid AND user_id=$uid";
            $pdo->query($sql);
            return null;
        }catch(\PDOException $e){
            return $e->getMessage();
        }
    }

    public static function delete($pid){
        // Удалить пост и связи и файлы, вернуть ошибку/null
        $uid=App::$user->id;
        $pdo=DB::getPDO();
        try{
            $pdo->beginTransaction();

            $sql="
DROP TABLE IF EXISTS tmp_delete;
CREATE TEMPORARY TABLE tmp_delete(
  id INT(11),
  name TEXT
);
INSERT INTO tmp_delete (id,name) SELECT f.id,f.name FROM fotos_of_post_items fi
  LEFT JOIN post_items pi ON pi.id=fi.item_id
  LEFT JOIN posts p ON p.id=pi.post_id
  LEFT JOIN fotos f ON f.id=fi.foto_id
WHERE p.id=$pid AND p.user_id=$uid;
";
            $stmt=$pdo->prepare($sql);
            $stmt->execute();

            $sql="DELETE FROM fotos WHERE id IN(SELECT id FROM tmp_delete)";
            $stmt=$pdo->prepare($sql);
            $stmt->execute();

            $sql="DELETE FROM posts WHERE id=$pid";
            $stmt=$pdo->prepare($sql);
            $stmt->execute();

            $sql="SELECT * FROM tmp_delete";
            $stmt=$pdo->prepare($sql);
            $stmt->execute();
            $files=$stmt->fetchAll(\PDO::FETCH_OBJ);

            $pdo->commit();
            self::deleteOldFiles($files);
        }catch(\PDOException $e){
            $pdo->rollback();
            return $e->getMessage();
        }
        return null;
    }
}