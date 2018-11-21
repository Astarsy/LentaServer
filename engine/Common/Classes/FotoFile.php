<?php

namespace Common\Classes;

class FotoFile{

    protected $_tmp_name,$_type,$_pdo;

    public $id,$name;

    public function __construct($fields,$pdo){
        // PDO с открытой транзакцией
        $this->_pdo=$pdo;

        // сгенерить уникальное имя файла
        $name=Utils::clearFilename($fields['name']);
        $mini_file_path=App::$params['foto']['types']['mini']['images_path'];
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

        $hi_w=App::$params['foto']['types']['hi']['width'];
        $hi_h=App::$params['foto']['types']['hi']['height'];

        if(!($source_img=imagecreatefromjpeg($this->_tmp_name)))return false;
        list($tmp_w,$tmp_h)=getimagesize($this->_tmp_name);
        if($tmp_w<$hi_w || $tmp_h<$hi_h)return false;

        foreach(App::$params['foto']['types'] as $foto_type){
            $w=$foto_type['width'];
            $h=$foto_type['height'];
            $fn=$foto_type['images_path'].$this->name;
            $img_coo=self::calculateCoords($w,$h,$tmp_w,$tmp_h);
            $res=self::generateImage($source_img
                ,$img_coo['sx'],$img_coo['sy'],$img_coo['sw'],$img_coo['sh']
                ,$w,$h,$fn);
            if(!$res)return 'не удалось сохранить изображение: '.$fn;
        }
        imagedestroy($source_img);
        return null;
    }

    public function saveToDB(){
        // Сохранить в БД, присвоить id
        // Выполняется в рамках транзакции родителя

        $sql="INSERT INTO fotos (`created_at`,`name`) VALUES(:ca,:na)";
        $stmt=$this->_pdo->prepare($sql);
        $stmt->bindValue(':ca',Utils::now());
        $stmt->bindValue(':na',$this->name);
        $stmt->execute();

        $stmt=$this->_pdo->prepare("SELECT LAST_INSERT_ID()");
        $stmt->execute();

        $this->id=$stmt->fetch(\PDO::FETCH_NUM)[0];
    }

    public static function delete($name){
        // Удаляет файл по имени во всех папках для данного типа изображений
        // Удалить только файлы, т.к. транзакция будет откачена в UserImageFiles
        $types=App::$params['foto']['types'];
        foreach($types as $foto_type){
            $fn=$foto_type['images_path'].$name;
            @unlink($fn);
        }
    }

    protected static function calculateCoords($dw,$dh,$sw,$sh){
        // Расчитать координаты и размеры областей для преобразования изображений
        // вернуть ассоц массив [sx,sy,sw,sh]
        $res=[];
        $dk=$dw/$dh;
        $sk=$sw/$sh;
        if($dk<$sk){
            // взять за опорную y
            $res['sh']=$sh;
            $res['sw']=(int)($sh*$dk);
            $res['sx']=(int)(($sw-$res['sw'])/2);
            $res['sy']=0;
        }else{
            // взять за опорную x
            $res['sw']=$sw;
            $res['sh']=(int)($sw/$dk);
            $res['sx']=0;
            $res['sy']=(int)(($sh-$res['sh'])/2);
        }
        return $res;
    }

    protected static function generateImage($si,$sx,$sy,$sw,$sh,$dw,$dh,$dp){
        // Создать и сохранить обрезанное изображение
        $di=imagecreatetruecolor($dw,$dh);
        imagecopyresampled($di,$si,0,0,$sx,$sy,$dw,$dh,$sw,$sh);
        $res=imagejpeg($di,$dp,100);
        imagedestroy($di);
        return $res;
    }

    public function existsInDB($name){
        $sql="SELECT COUNT(id) FROM fotos WHERE `name`='$name'";
        return (bool)$this->_pdo->query($sql)->fetch(\PDO::FETCH_NUM)[0];
    }
}
