<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use GuzzleHttp\Client;
use App\Model\WxMediaModel;

class WxController extends Controller
{

    protected $xml_obj;

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
     * 验证请求是否来自微信
     */
    private function check()
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
            return true;
        }else{
            return false;
        }
    }

    /**
     * 处理推送事件
     */
    public function wxEvent()
    {

        //验签
//        if($this->check()==false)
//        {
//            //TODO 验签不通过
//            echo "";
//            exit;
//        }

        // 1 接收数据
        $xml_str = file_get_contents("php://input");


        // 记录日志
        $log_str = date('Y-m-d H:i:s') . ' >>>>>  ' . $xml_str ." \n\n";
        file_put_contents('wx_event.log',$log_str,FILE_APPEND);

        // 将接收来的数据转化为对象
        $obj = simplexml_load_string($xml_str);//将文件转换成 对象
        $this->xml_obj = $obj;

        $msg_type = $obj->MsgType;      //推送事件的消息类型
        switch($msg_type)
        {
            case 'event' :

                if($obj->Event=='subscribe')        // subscribe 扫码关注
                {
                    echo $this->subscribe();
                    exit;
                }elseif($obj->Event=='unsubscribe')     // // unsubscribe 取消关注
                {
                    echo "";
                    exit;
                }elseif ($obj->Event=='CLICK')          // 菜单点击事件
                {
                    $this->clickHandler();
                    // TODO
                }elseif($obj->Event=='VIEW')            // 菜单 view点击 事件
                {
                    // TODO
                }


                break;

            case 'text' :           //处理文本信息
                $this->textHandler();
                break;

            case 'image' :          // 处理图片信息
                $this->imageHandler();
                break;

            case 'voice' :          // 语音
                $this->voiceHandler();
                break;
            case 'video' :          // 视频
                $this->videoHandler();
                break;

            default:
                echo 'default';
        }

        echo "";

    }

    /**
     * 处理文本消息
     */
    protected function textHandler()
    {
        echo '<pre>';print_r($this->xml_obj);echo '</pre>';
        $data = [
            'open_id'       => $this->xml_obj->FromUserName,
            'msg_type'      => $this->xml_obj->MsgType,
            'msg_id'        => $this->xml_obj->MsgId,
            'create_time'   => $this->xml_obj->CreateTime,
        ];

        //入库
        WxMediaModel::insertGetId($data);

    }

    /**
     * 处理图片消息
     */
    protected function imageHandler(){

        //入库

        //下载素材


    }

    /**
     * 处理语音消息
     */
    protected function voiceHandler(){}


    /**
     * 处理视频消息
     */
    protected function videoHandler(){}


    /**
     * 处理菜单点击事件
     * click类型的菜单 创建时会有key，根据key做相应的逻辑处理
     */
    protected function clickHandler()
    {
        $event_key = $this->xml_obj->EventKey;      //菜单 click key
        echo $event_key;

        switch ($event_key){
            case 'checkin' :
                // TODO 签到逻辑
                break;

            case 'weather':
                // TODO 获取天气
                break;

            default:
                // TODO 默认
                break;
        }

        echo "";

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
     * 回复扫码关注
     * @param $obj
     * @param $content
     * @return string
     */
    public function  subscribe(){

        $content = "欢迎关注 现在时间是：" . date("Y-m-d H:i:s");
        $ToUserName=$this->xml_obj->FromUserName;
        $FromUserName=$this->xml_obj->ToUserName;

        $xml="<xml>
              <ToUserName><![CDATA[".$ToUserName."]]></ToUserName>
              <FromUserName><![CDATA[".$FromUserName."]]></FromUserName>
              <CreateTime>time()</CreateTime>
              <MsgType><![CDATA[text]]></MsgType>
              <Content><![CDATA[".$content."]]></Content>
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
                    'name'          => '二级菜单',
                    'sub_button'    => [
                        [
                            'type'  => 'click',
                            'name'  => '签到',
                            'key'   => 'checkin'
                        ],
                        [
                            'type'  => 'pic_photo_or_album',
                            'name'  => '传图',
                            'key'   => 'uploadimg'
                        ],
                        [
                            'type'  => 'click',
                            'name'  => '天气',
                            'key'   => 'weather'
                        ]
                    ]
                ],

            ]
        ];

        //使用guzzle发起 POST 请求
        $client = new Client();         //实例化 客户端
        $response = $client->request('POST',$url,[
            'verify'    => false,
            'body'  => json_encode($menu,JSON_UNESCAPED_UNICODE)
        ]);

        $json_data = $response->getBody();

        //判断接口返回
        $info = json_decode($json_data,true);

        if($info['errcode'] > 0)        //判断错误码
        {
            // TODO 处理错误
            echo '<pre>';print_r($info);echo '</pre>';
        }else{
            // TODO 创建菜单成功逻辑
            echo "创建菜单成功";
        }



    }
}
