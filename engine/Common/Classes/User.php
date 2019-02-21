<?php

namespace Common\Classes;

class User extends Model implements \JsonSerializable{
    public function __construct($fields=null){

        parent::__construct($fields,'users');
    }

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