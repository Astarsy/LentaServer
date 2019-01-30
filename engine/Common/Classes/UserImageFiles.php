<?php
/**
    Обработка загруженных файлов изображений
 */

namespace Common\Classes;


class UserImageFiles{

    protected $_files=[],$_pdo,$_itemOfFile,$_type;
    public $fotos=[];

    public function __construct($pdo,$itemOfFile,$type='foto'){
        $this->_type=$type;
        $this->_pdo=$pdo;
        $this->_itemOfFile=$itemOfFile;
    }

    public function save(){
        // Сохранить вложения
        $params=App::$params[$this->_type];
        $max_size=$params['max_file_size'];
        $max_count=$params['max_count'];
        $field_name=$params['field_name'];

        $count=count($_FILES[$field_name]['name']);
        if($count>$max_count)$count=$max_count;

        for($i=0;$i<$count;$i++){
            $files_array=self::normalizeFilesArray($field_name,$i);
            if( !empty($files_array['error'])
                || $files_array['size']>$max_size
                || "image/jpeg"!==$files_array['type'])continue;

            $file=new FotoFileProportional($files_array,$this->_pdo,$this->_type);
            $file->saveFiles();
            $this->_files[]=$file;

            $this->saveToDB($file);

            if(!isset($this->_itemOfFile[$i]))$iof=null;
            else $iof=$this->_itemOfFile[$i];
            $this->fotos[]= [
                'id'=>$file->id,
                'name'=>$file->name,
                'itemIndex'=>$iof
            ];
        }
    }

    public function saveToDB(&$file){
        // Сохранить в БД, присвоить id
        // Выполняется в рамках транзакции родителя

        $tn=$this->_type.'s';
        $sql="INSERT INTO $tn (`created_at`,`name`) VALUES(:ca,:na)";
        $stmt=$this->_pdo->prepare($sql);
        $stmt->bindValue(':ca',Utils::now());
        $stmt->bindValue(':na',$file->name);
        $stmt->execute();

        $stmt=$this->_pdo->prepare("SELECT LAST_INSERT_ID()");
        $stmt->execute();

        $file->id=$stmt->fetch(\PDO::FETCH_NUM)[0];
    }

    public function delete(){
        // Удалить файлы в случае отката транзакции
        foreach($this->_files as &$file)$file::delete();
    }

    public function normalizeFilesArray($field_name,$index){
        $files_array=[];
        foreach($_FILES[$field_name] as $k=>$v){
            $files_array[$k]=$v[$index];
        }
        return $files_array;
    }
}