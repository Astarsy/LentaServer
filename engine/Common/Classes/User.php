<?php

namespace Common\Classes;

class User extends Model implements \JsonSerializable{
    public function __construct($fields=[]){

        parent::__construct($fields,'users');
    }

//    public function update(){
//        $pdo=DB::getPDO();
//        try{
//            $pdo->beginTransaction();
//            $sql="UPDATE users SET avatar=:av, name=:na WHERE id=:id";
//            $stmt=$pdo->prepare($sql);
//            $stmt->bindParam(':id',$this->id);
//            $stmt->bindParam(':av',$this->avatar);
//            $stmt->bindParam(':na',$this->name);
//            $stmt->execute();
//// TODO: ???
//            $pdo->commit();
//        }catch(\Exception $e){
//            $pdo->rollback();
//            die($e->getMessage());
//        }
//        return false;
//    }

    public function jsonSerialize(){
        // Implement jsonSerialize() method.
        return [
            'id'=>$this->id,
            'name'=>$this->name,
            'about'=>$this->about,
            'avatar'=>$this->avatar
        ];
    }
}