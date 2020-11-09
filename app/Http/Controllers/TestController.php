<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use GuzzleHttp\Client;

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

    public function guzzle1()
    {
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".env('WX_APPID')."&secret=".env('WX_APPSEC');

        //使用guzzle发起get请求
        $client = new Client();         //实例化 客户端
        $response = $client->request('GET',$url,['verify'=>false]);       //发起请求并接收响应

        $json_str = $response->getBody();       //服务器的响应数据
        echo $json_str;

    }

    public function guzzle2()
    {
        $access_token = "";
        $type = 'image';
        $url = 'https://api.weixin.qq.com/cgi-bin/media/upload?access_token='.$access_token.'&type='.$type;
        //使用guzzle发起get请求
        $client = new Client();         //实例化 客户端
        $response = $client->request('POST',$url,[
            'verify'    => false,
            'multipart' => [
                [
                    'name'  => 'media',
                    'contents'  => fopen('gsl.jpg','r')
                ],         //上传的文件路径]


            ]
        ]);       //发起请求并接收响应

        $data = $response->getBody();
        echo $data;

    }


}
