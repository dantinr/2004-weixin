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


}
