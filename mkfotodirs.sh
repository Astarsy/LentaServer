#!/bin/bash

imgpath=www/img
f='/fotos'
mf='/msgfotos'

if [ -d $imgpath$f ]
    then echo 'Deleting '$imgpath$f
    rm -r $imgpath$f
fi

echo 'Creating '$imgpath$f
mkdir $imgpath$f
mkdir $imgpath$f'/hi'
mkdir $imgpath$f'/big'
mkdir $imgpath$f'/mini'
mkdir $imgpath$f'/ico'

if [ -d $imgpath$mf ]
    then echo 'Deleting '$imgpath$mf
    rm -r $imgpath$mf
fi

echo 'Creating '$imgpath$mf
mkdir $imgpath$mf
mkdir $imgpath$mf'/hi'
mkdir $imgpath$mf'/big'
mkdir $imgpath$mf'/mini'
mkdir $imgpath$mf'/ico'