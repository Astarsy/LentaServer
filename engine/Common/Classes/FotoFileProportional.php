<?php

namespace Common\Classes;

class FotoFileProportional{
    // Версия FotoFile с отличающимся алгоритмом вычисления размеров -
    // сохраняет исходные пропорции

    protected $_tmp_name,$_type,$_pdo,$_last_type;

    public $id,$name;

    public function __construct($fields,$pdo,$type){
        // PDO с открытой транзакцией
        $this->_type=$type;
        $this->_pdo=$pdo;

        // сгенерить уникальное имя файла
        $name=Utils::clearFilename($fields['name']);
        foreach(App::$params['foto']['types'] as $k=>$lt); // takes last size of images
        $this->_last_type=$lt;
        $mini_file_path=$lt['images_path'];
        $name=$this->createUniqueFileName($name,$mini_file_path);

        $this->name=$name;
        $this->_tmp_name=$fields['tmp_name'];
    }

    public function createUniqueFileName($name,$dir){
        // Сгенерить имя файла уникальное в Таблице и каталоге
        $i=1;
        $arr=explode('.',$name);
        while($this->existsInDB($name) || file_exists($dir.$name)){
            $p1=$arr[0].'_'.$i++;
            $name=$p1.'.'.$arr[1];
        }
        return $name;
    }

    public function saveFiles(){
        // Создаёт и сохраняет изображения
        // Возвращает ошибку/null

        $hi_w=$this->_last_type['width'];
        $hi_h=$this->_last_type['height'];

        if(!($source_img=imagecreatefromjpeg($this->_tmp_name)))return false;
        list($tmp_w,$tmp_h)=getimagesize($this->_tmp_name);
        if($tmp_w<$hi_w || $tmp_h<$hi_h)return false;

        $r=$tmp_w/$tmp_h;
        foreach(App::$params[$this->_type]['types'] as $foto_type){
            $fn=$foto_type['images_path'].$this->name;
            if($r>=1){
                $dw=$foto_type['width'];
                $dh=$dw/$r;
            }else{
                $dh=$foto_type['height'];
                $dw=$dh*$r;
            }

            $res=self::generateImage($source_img
                ,0,0,$tmp_w,$tmp_h
                ,$dw,$dh,$fn);
            if(!$res)return 'не удалось сохранить изображение: '.$fn;
        }
        imagedestroy($source_img);
        return null;
    }

    protected static function generateImage($si,$sx,$sy,$sw,$sh,$dw,$dh,$dp){
        // Создать и сохранить обрезанное изображение
        $di=imagecreatetruecolor($dw,$dh);
        imagecopyresampled($di,$si,0,0,$sx,$sy,$dw,$dh,$sw,$sh);
        $res=imagejpeg($di,$dp,100);
        imagedestroy($di);
        return $res;
    }

    public static function delete($name,$type='foto'){
        // Удаляет файл по имени во всех папках для данного типа изображений
        // Удалить только файлы, т.к. транзакция будет откачена в UserImageFiles
        $types=App::$params[$type]['types'];
        foreach($types as $foto_type){
            $fn=$foto_type['images_path'].$name;
            @unlink($fn);
        }
    }

    public function existsInDB($name){
        $sql="SELECT COUNT(id) FROM fotos WHERE `name`='$name'";
        return (bool)$this->_pdo->query($sql)->fetch(\PDO::FETCH_NUM)[0];
    }
}
