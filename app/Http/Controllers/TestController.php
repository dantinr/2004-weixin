<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class TestController extends Controller
{

    public function test1()
    {
//        echo __METHOD__;
//
//        $list = DB::table('p_users')->limit(5)->get()->toArray();
//        echo '<pre>';print_r($list);echo '</pre>';


        $key = 'wx2004';
        Redis::set($key,time());
        echo Redis::get($key);
    }

    public function test3()
    {
        echo '<pre>';print_r($_GET);echo '</pre>';
    }

    public function test4()
    {
        //echo '<pre>';print_r($_POST);echo '</pre>';
        $xml_str = file_get_contents("php://input");


        //将 xml 转换为 对象或数组
        $xml_obj = simplexml_load_string($xml_str);
        //echo '<pre>';print_r($xml_obj);echo '</pre>';

        echo $xml_obj->ToUserName;

    }


    public function wx()
    {
        echo __METHOD__;
    }

    public function wx2()
    {
        echo '<pre>';print_r($_POST);echo '</pre>';
    }


}
