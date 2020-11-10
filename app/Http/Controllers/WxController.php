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
            file_put_contents('wx_event.log',$xml_str,、、);

            // 将接收来的数据转化为对象
            $obj = simplexml_load_string($xml_str);//将文件转换成 对象

            if($obj->MsgType=="event") {
                if($obj->Event=="subscribe"){
                $content = "欢迎关注";
                echo $this->xiaoxi($obj, $content);
            }

            }
            // TODO 处理业务逻辑


            echo "";
            die;

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


    public function  xiaoxi($obj,$content){
        $ToUserName=$obj->FromUserName;
        $FromUserName=$obj->$ToUserName;


        $xml="<xml>
              <ToUserName><![CDATA[".$ToUserName."]]></ToUserName>
              <FromUserName><![CDATA[".$FromUserName."]]></FromUserName>
              <CreateTime>time()</CreateTime>
              <MsgType><![CDATA[text]]></MsgType>
              <Content><![CDATA[".$content."]]></Content>
              <MsgId>%s</MsgId>
       </xml>";
echo $xml;
    }
}
