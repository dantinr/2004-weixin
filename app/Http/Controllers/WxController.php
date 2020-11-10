<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use GuzzleHttp\Client;

class WxController extends Controller
{
    //

    public function index()
    {
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];

        $token = env('WX_TOKEN');
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode( $tmpArr );
        $tmpStr = sha1( $tmpStr );

        if( $tmpStr == $signature ){
            echo $_GET['echostr'];
        }else{
            echo "111";
        }
    }

    /**
     * 处理推送事件
     */
    public function wxEvent()
    {
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];

        $token = env('WX_TOKEN');
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode( $tmpArr );
        $tmpStr = sha1( $tmpStr );

        if( $tmpStr == $signature ){            //验证通过

            // 1 接收数据
            $xml_str = file_get_contents("php://input") . "\n\n";

            // 记录日志
            file_put_contents('wx_event.log',$xml_str,FILE_APPEND);

            // 将接收来的数据转化为对象
            $obj = simplexml_load_string($xml_str);//将文件转换成 对象

            if($obj->MsgType=="event") {
                if($obj->Event=="subscribe"){           //处理扫码关注

                echo $this->xiaoxi($obj);
            }

            }
            // TODO 处理业务逻辑


        }else{
            echo "";
        }
    }


    /**
     * 获取access_token
     */
    public function getAccessToken()
    {

        $key = 'wx:access_token';

        //检查是否有 token
        $token = Redis::get($key);
        if($token)
        {
            echo "有缓存";echo '</br>';

        }else{

            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".env('WX_APPID')."&secret=".env('WX_APPSEC');
            //使用guzzle发起get请求
            $client = new Client();         //实例化 客户端
            $response = $client->request('GET',$url,['verify'=>false]);       //发起请求并接收响应
            $json_str = $response->getBody();       //服务器的响应数据
            $data = json_decode($json_str,true);
            $token = $data['access_token'];

            //保存到Redis中 时间为 3600
            Redis::set($key,$token);
            Redis::expire($key,3600);

        }

        return $token;

    }


    /**
     * 上传素材
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function guzzle2()
    {
        $access_token = $this->getAccessToken();
        $type = 'image';
        $url = 'https://api.weixin.qq.com/cgi-bin/media/upload?access_token='.$access_token.'&type='.$type;
        //使用guzzle发起get请求
        $client = new Client();         //实例化 客户端
        $response = $client->request('POST',$url,[
            'verify'    => false,
            'multipart' => [
                [
                    'name'  => 'media',
                    'contents'  => fopen('gsl.jpg','r') //上传的文件路径]
                ],
            ]
        ]);

        $data = $response->getBody();

    }


    /**
     * 回复扫码关注
     * @param $obj
     * @param $content
     * @return string
     */
    public function  xiaoxi($obj){

        $content = "欢迎关注11111";
        $ToUserName=$obj->FromUserName;
        $FromUserName=$obj->ToUserName;

        $xml="<xml>
              <ToUserName><![CDATA[".$ToUserName."]]></ToUserName>
              <FromUserName><![CDATA[".$FromUserName."]]></FromUserName>
              <CreateTime>time()</CreateTime>
              <MsgType><![CDATA[text]]></MsgType>
              <Content><![CDATA[".$content."]]></Content>
              <MsgId>%s</MsgId>
       </xml>";

        return $xml;
    }

    /**
     * 创建自定义菜单
     */
    public function createMenu()
    {
        $access_token = $this->getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token='.$access_token;

        $menu = [
            'button'    => [
                [
                    'type'  => 'click',
                    'name'  => 'WX2004',
                    'key'   => 'k_wx_2004'
                ],
                [
                    'type'  => 'view',
                    'name'  => 'BAIDU',
                    'url'   => 'https://www.baidu.com'
                ],

            ]
        ];

        //使用guzzle发起 POST 请求
        $client = new Client();         //实例化 客户端
        $response = $client->request('POST',$url,[
            'verify'    => false,
            'body'  => json_encode($menu)
        ]);

        $json_data = $response->getBody();

        //判断接口返回
        $info = json_decode($json_data,true);

        if($info['errcode'] > 0)        //判断错误码
        {
            // TODO 处理错误
        }else{
            // TODO 创建菜单成功逻辑
        }



    }
}
