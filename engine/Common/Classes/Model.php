<?php

namespace Common\Classes;

class Model{
    // Универсальная модель на базе одной таблицы. Поля хранятся в виде открытых полей Модели.
    // Имена открытых полей, которые не должны загружаться из БД, дожны начинаться с _
    protected $_table_name; // имя таблицы в БД
    public $_fields;
    public function __construct($fields,$table_name){
        // имя таблицы по-умолчанию создать из имени класса
        if($table_name)$this->_table_name=$table_name;
        $this->fillFields($fields);
    }

    public function fields(){
        return $this->_fields;
    }

    protected function fillFields($arr){
        // ожидается массив полей
        if(!is_array($arr))return;
        foreach($arr as $k=>$v){
            $this->{$k}=$v;
        }
    }

    public function load(){
        // загрузить по ID, вернуть ошибку/false
        if(!isset($this->id))return 'не указан идентификатор';
        $pdo=DB::getPDO();
        try{
            $sql="SELECT * FROM $this->_table_name WHERE id=$this->id";
            $stmt=$pdo->prepare($sql);
            $stmt->execute();
            $this->fillFields($stmt->fetch(\PDO::FETCH_ASSOC));
            return false;
        }catch(\Exception $e){return $e->getMessage();}
    }

    public function loadBy($field,$value){
        // TODO: переписать для приёма массива параметров
        // загрузить модель из БД, вернуть ошибку/false
        if($value===null)return $this;
        $pdo=DB::getPDO();
        try{
            $stmt=$pdo->prepare("SELECT * FROM {$this->_table_name} WHERE $field=:value");
            $stmt->bindParam(':value',$value);
            $stmt->execute();
            $this->fillFields($stmt->fetch(\PDO::FETCH_ASSOC));
        }catch(\PDOException $e){return $e->getMessage();}
        return false;
    }

    public function save(){
        // сохранить модель в БД или создать новую
        if(isset($this->id))return $this->update();
        return $this->insert();
    }

    protected function get_fields(){
        $vars=get_object_vars($this);
        foreach($vars as $k=>$v){
            if(substr($k,0,1)==='_')unset($vars[$k]);
        }
        return $vars;
    }

    protected function update(){
        $vars=$this->get_fields();
        unset($vars['id']);
        $arr=[];
        foreach($vars as $k=>$v){
            $arr[]=$k.'=:'.$k;
        }
        $set_str=implode(', ',$arr);
        $sql="UPDATE $this->_table_name SET $set_str WHERE id=:id";
        $pdo=DB::getPDO();
        try{
            $stmt=$pdo->prepare($sql);
            $stmt->bindParam(':id',$this->id);
            foreach($vars as $k=>$v){
                // Внимание! В bindParam нельзя использовать ссылочные $k=>$v, только $vars[$k] !
                $fn=':'.$k;
                if(null===$v)$stmt->bindParam($fn,$vars[$k],\PDO::PARAM_NULL);
                else $stmt->bindParam($fn,$vars[$k]);
            }
            $stmt->execute();
        }catch(\Exception $e){die($e->getMessage());}
        return false;
    }

    protected function insert(){
        $pdo=DB::getPDO();
        $vars=$this->get_fields();
        $fields_str=implode(',',array_keys($vars));
        $values=[];
        foreach($vars as $k=>$v)$values[]=':'.$k;
        $values_str=implode(',',$values);
        try{
            $stmt=$pdo->prepare("INSERT INTO $this->_table_name ($fields_str) VALUES ($values_str)");
            foreach($vars as $k=>$v){
                $fn=':'.$k;
                if(null===$v)$stmt->bindParam($fn,$vars[$k],\PDO::PARAM_NULL);
                else $stmt->bindParam($fn,$vars[$k]);
            }
            $stmt->execute();
            $stmt=$pdo->prepare("SELECT LAST_INSERT_ID()");
            $stmt->execute();
            $this->id=$stmt->fetch(\PDO::FETCH_NUM)[0];
        }catch(\Exception $e){die($e->getMessage());}

        return false;
    }

    public function delete(){
        $pdo=DB::getPDO();
        try{
            $stmt=$pdo->prepare("DELETE FROM $this->_table_name WHERE id=:id");
            $stmt->bindParam(':id',$this->id);
            $stmt->execute();
        }catch(\Exception $e){die($e->getMessage());}
        return false;
    }
}