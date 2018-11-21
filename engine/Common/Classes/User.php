<?php

namespace Common\Classes;

class User extends Model{
    public function __construct($fields=[]){

        parent::__construct($fields,'users');
    }
}