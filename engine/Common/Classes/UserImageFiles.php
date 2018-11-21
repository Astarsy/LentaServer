<?php
/**
    Обработка загруженных файлов изображений
 */

namespace Common\Classes;


class UserImageFiles{

    protected $_files=[],$_pdo,$_itemOfFile;
    public $fotos=[];

    public function __construct($pdo,$itemOfFile){
        $this->_pdo=$pdo;
        $this->_itemOfFile=$itemOfFile;
    }

    public function save(){
        // Сохранить вложения
        $params_foto=App::$params['foto'];
        $max_size=$params_foto['max_file_size'];
        $max_count=$params_foto['max_count'];
        $field_name=$params_foto['field_name'];

        $count=count($_FILES[$field_name]['name']);
        if($count>$max_count)$count=$max_count;

        for($i=0;$i<$count;$i++){
            $files_array=self::normalizeFilesArray($field_name,$i);
            if( !empty($files_array['error'])
                || $files_array['size']>$max_size
                || "image/jpeg"!==$files_array['type'])continue;

            $file=new FotoFile($files_array,$this->_pdo);
            $file->saveFiles();
            $this->_files[]=$file;
            $file->saveToDB();

            $this->fotos[]= [
                'id'=>$file->id,
                'name'=>$file->name,
                'itemIndex'=>$this->_itemOfFile[$i]
            ];
        }
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