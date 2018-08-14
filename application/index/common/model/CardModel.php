<?php
namespace app\index\common\model;

class CardModel
{

    public function getCardTypeModel($type)
    {
        $model = array();
        switch($type){
            case '1':
                $model = array('cardId','contentTitle','contentDesc','image','link');
                break;

            case '2':
                $model = array('cardId','title','image','link');
                break;

            case '3':
                $model = array('cardId','image','link');
                break;

            case '4':
                $model = array('cardId','contentTitle','contentDesc','buttonContent','image','link');
                break;

            case '5':
                $model = array('cardId','title','image','link');
                break;

            case '6':
                $model = array('cardId','title','contentTitle','contentDesc','mixAmount','maxAmount','isRecommend','tags','image','link');
                break;

            case '7':
                $model = array('cardId','contentTitle','contentDesc','buttonContent','image','link');
                break;

            case '8':
                $model = array('cardId','contentTitle','contentDesc','link');
                break;

            case '9':
                $model = array('cardId','title','contentTitle','image','link');
                break;

            case '10':
                $model = array('cardId','image','link');
                break;

            case '11':
                $model = array('cardId','contentTitle','image','link');
                break;

            case '12':
                $model = array('cardId','title','contentTitle','contentDesc','image','link');
                break;
        }
        return $model;
    }

}