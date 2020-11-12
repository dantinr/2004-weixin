<?php

namespace App\Http\Controllers;

use App\Model\WxUserModel;
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


        //下载素材
        $token = $this->getAccessToken();
        $media_id = $this->xml_obj->MediaId;
        $url = 'https://api.weixin.qq.com/cgi-bin/media/get?access_token='.$token.'&media_id='.$media_id;
        $img = file_get_contents($url);
        $media_path = 'upload/cat.jpg';
        $res = file_put_contents($media_path,$img);
        if($res)
        {
            // TODO 保存成功
        }else{
            // TODO 保存失败
        }

        //入库
        $info = [
            'media_id'  => $media_id,
            'open_id'   => $this->xml_obj->FromUserName,
            'msg_type'  => $this->xml_obj->MsgType,
            'msg_id'  => $this->xml_obj->MsgId,
            'create_time'  => $this->xml_obj->CreateTime,
            'media_path'    => $media_path
        ];
        WxMediaModel::insertGetId($info);

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
            return $token;
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
            return $token;
        }



    }



    /**
     * 回复扫码关注
     * @param $obj
     * @param $content
     * @return string
     */
    public function  subscribe(){

        $ToUserName=$this->xml_obj->FromUserName;       // openid
        $FromUserName=$this->xml_obj->ToUserName;
        //检查用户是否存在
        $u = WxUserModel::where(['openid'=>$ToUserName])->first();
        if($u)
        {
            // TODO 用户存在
            $content = "欢迎回来 现在时间是：" . date("Y-m-d H:i:s");
        }else{
            //获取用户信息，并入库
            $user_info = $this->getWxUserInfo();

            //入库
            unset($user_info['subscribe']);
            unset($user_info['remark']);
            unset($user_info['groupid']);
            unset($user_info['substagid_listcribe']);
            unset($user_info['qr_scene']);
            unset($user_info['qr_scene_str']);
            unset($user_info['tagid_list']);

            WxUserModel::insertGetId($user_info);
            $content = "欢迎关注 现在时间是：" . date("Y-m-d H:i:s");

        }

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
                    'type'  => 'view',
                    'name'  => '商城',
                    'url'   => 'http://2004shop.comcto.com'
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
            echo date("Y-m-d H:i:s").  "创建菜单成功";
        }



    }


    /**
     * 下载媒体
     */
    public function dlMedia()
    {
        $token = $this->getAccessToken();
        $media_id = '2EOz5TyVOVA728B6cETWByk8_w33mS17Ye1e1C6AuAv2SMS7l4R4HoQFl9mmgprw';
        $url = 'https://api.weixin.qq.com/cgi-bin/media/get?access_token='.$token.'&media_id='.$media_id;
        echo $url;die;
        $img = file_get_contents($url);
        $res = file_put_contents('cat.jpg',$img);
        var_dump($res);

    }


    /**
     * 上传素材接口
     * 参考  https://developers.weixin.qq.com/doc/offiaccount/Asset_Management/New_temporary_materials.html
     */
    public function uploadMedia()
    {
        $access_token = $this->getAccessToken();
        $type = 'video';        //素材类型 image voice video thumb
        $url = 'https://api.weixin.qq.com/cgi-bin/media/upload?access_token='.$access_token.'&type='.$type;

        $media = 'tmp/heshang.mp4';     //要上传的素材
        //使用guzzle发起get请求
        $client = new Client();         //实例化 客户端
        $response = $client->request('POST',$url,[
            'verify'    => false,
            'multipart' => [
                [
                    'name'  => 'media',
                    'contents'  => fopen($media,'r')
                ],         //上传的文件路径]
            ]
        ]);       //发起请求并接收响应

        $data = $response->getBody();
        echo $data;
    }

    /**
     * 群发消息
     */
    public function sendAll()
    {
        //根据openid 群发   https://developers.weixin.qq.com/doc/offiaccount/Message_Management/Batch_Sends_and_Originality_Checks.html#3
        $access_token = $this->getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/message/mass/send?access_token='.$access_token;

        //使用guzzle发起POST请求

        $data = [
            'filter'    => [
                'is_to_all' => false,
                'tag_id'    => 2
            ],
            'touser'    => [
                'oLreB1gfi87dPCO2gRiUecC5ZAbc',
                'oLreB1ruWsNCS-iMr_scTyVSUyY0',
                'oLreB1gnCH7es_CbLhRvM6yQO-kQ',
                'oLreB1mi55VwI2wai2y1uicTG5sk',
                'oLreB1hSqDSoz7VkTDin6J75ez4M',
                'oLreB1nsTnJSYPgmEUe1YW1xdAOw',
                'oLreB1i2Ig7OlI9YMI_nUBdGDmU8',
                'oLreB1qa7IVU3qpe0Tg1LShlzkww',
                'oLreB1kVep716f8n1i2Ace6r6UnA',
                'oLreB1kCnRGCqWu0Mur4A08usNRM',
                'oLreB1upyFz8UPNt5OTNLfP_9ciM',
                'oLreB1hfXdA_H-A-kJzXotMvlL1s',
                'oLreB1obDfuVfyBO8cBIH8FibAiA',
                'oLreB1m47p6J4mfY5Z6CQCMwFX4Q',
                'oLreB1hjx82-74x7qKxmkyeWbC7I',
                'oLreB1rcEhV6sMK9-X5Vgw_Sghqo',
                'oLreB1jG5XZ-F5QokhugIxdpe2lk',
                'oLreB1jAnJFzV_8AGWUZlfuaoQto',
                'oLreB1rTYjCsM8lp40yGky1fDcAQ',
                'oLreB1tqqKpg4n53ujarU47tQnSM',
                'oLreB1nGcCmNvEXScOpVNgfBifLA',
                'oLreB1inC1l0NjUy3Vz6rD5DoLDM',
                'oLreB1uh30YcGZGLDMPbm8cpu81E',
                'oLreB1qNMROnUTIbIAFSRoekMdfw',
                'oLreB1sehZ4x0N7T93-elf6f5hYg',
                'oLreB1tvM636Yof_F4WTh0nP6fOY',
                'oLreB1oWQYSQJUKL5i6kamigrj8g',
                'oLreB1oPHycqKR383DQtdhnHjP2U',
                'oLreB1ikgAe1kq2ES0M6SWQdGVqY',
            ],
            'images'    => [
                'media_ids' => [
                    '2EOz5TyVOVA728B6cETWByk8_w33mS17Ye1e1C6AuAv2SMS7l4R4HoQFl9mmgprw'
                ],
            ],
            'msgtype'   => 'image'
        ];

        $client = new Client();         //实例化 客户端
        $response = $client->request('POST',$url,[
            'verify'    => false,
            'body'      => json_encode($data,JSON_UNESCAPED_UNICODE)
        ]);       //发起请求并接收响应

        $data = $response->getBody();
        echo $data;

    }


    /**
     * 获取用户基本信息
     */
    public function getWxUserInfo()
    {

        $token = $this->getAccessToken();
        $openid = $this->xml_obj->FromUserName;
        $url = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token='.$token.'&openid='.$openid.'&lang=zh_CN';

        //请求接口
        $client = new Client();
        $response = $client->request('GET',$url,[
            'verify'    => false
        ]);
        return  json_decode($response->getBody(),true);
    }






}
