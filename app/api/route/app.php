<?php

// 多 C 端开放 API 路由（与源站路径一致，C 端前端零改动直连）
//
// 中间件：
//   BearerToken     —— Authorization: Bearer → ba-user-token（复用 BuildAdmin 会员鉴权）
//   CStyleResponse  —— 响应包络按 config('nft.c_end_style') 适配（zhenhai={status,code,message,data}）
//
// 覆盖闭环：账号/藏品/订单/持仓 + 基础运营（basic）+ 二级市场（寄售/求购/委托/市场列表）。
// 静态段一律 completeMatch 并先于动态段注册，避免被 :iid/:id 部分匹配吞掉。

use app\api\middleware\BearerToken;
use app\api\middleware\CStyleResponse;
use think\facade\Route;

Route::group(function () {

    /* ---------- 账号与鉴权 ---------- */
    Route::post('passport/signup', 'nft.Passport/signup');
    Route::post('passport/signin', 'nft.Passport/signin');
    Route::post('passport/signout', 'nft.Passport/signout');
    Route::get('passport/account', 'nft.Passport/account');
    Route::get('passport/captcha/image', 'nft.Passport/captchaImage');
    Route::post('passport/captcha/sms', 'nft.Passport/captchaSms');
    Route::put('passport/secondPassword', 'nft.Passport/secondPassword');

    /* ---------- 藏品目录与关注 ---------- */
    // 静态段开启完整匹配，避免 /item/5 被 item 规则部分匹配吞掉
    Route::get('item', 'nft.Item/index')->completeMatch();
    Route::get('item/category', 'nft.Item/category')->completeMatch();
    Route::post('item/follow/:iid', 'nft.Item/follow')->pattern(['iid' => '\d+']);
    Route::delete('item/unfollow/:iid', 'nft.Item/unfollow')->pattern(['iid' => '\d+']);
    // 静态段必须先于 :iid 动态段注册（路由首匹配即命中）
    Route::get('item/:iid', 'nft.Item/detail')->pattern(['iid' => '\d+']);

    /* ---------- 订单（一级 + 二级） ---------- */
    Route::post('order/primary', 'nft.Order/primary');
    // 二级市场下单（静态段先于 order/secondary 无参路由）
    Route::post('order/secondary/quick', 'nft.Order/secondaryQuick')->completeMatch();
    Route::post('order/secondary/batch', 'nft.Order/secondaryBatch')->completeMatch();
    Route::post('order/secondary/apply', 'nft.Order/secondaryApply')->completeMatch();
    Route::post('order/secondary/trust', 'nft.Order/secondaryTrust')->completeMatch();
    Route::post('order/secondary', 'nft.Order/secondary')->completeMatch();
    Route::post('order/pay/balance', 'nft.Order/payBalance')->completeMatch();
    Route::put('order/cancel', 'nft.Order/cancel')->completeMatch();
    Route::get('order', 'nft.Order/index')->completeMatch();
    Route::get('order/:id', 'nft.Order/detail')->pattern(['id' => '\d+']);

    /* ---------- 持仓与寄售 ---------- */
    Route::get('nft', 'nft.Nft/index')->completeMatch();
    Route::get('nft/sell/sold', 'nft.Nft/sellSold')->completeMatch();
    Route::post('nft/sell/up/batch', 'nft.Nft/sellUpBatch')->completeMatch();
    Route::post('nft/sell/up', 'nft.Nft/sellUp')->completeMatch();
    Route::put('nft/sell/down', 'nft.Nft/sellDown')->completeMatch();
    Route::post('nft/sell/apply', 'nft.Nft/sellApply')->completeMatch();

    /* ---------- 二级市场 ---------- */
    Route::get('nft/secondary', 'nft.Nft/secondary')->completeMatch();
    Route::get('nft/secondary/sell', 'nft.Nft/secondarySell')->completeMatch();
    Route::get('nft/secondary/sold', 'nft.Nft/secondarySold')->completeMatch();
    Route::get('nft/secondary/apply', 'nft.Nft/secondaryApply')->completeMatch();
    Route::get('nft/secondary/trust', 'nft.Nft/secondaryTrust')->completeMatch();
    Route::get('nft/secondary/follow', 'nft.Nft/secondaryFollow')->completeMatch();
    Route::get('nft/secondary/:iid', 'nft.Nft/secondaryDetail')->pattern(['iid' => '\d+']);
    // 动态持仓详情放最后
    Route::get('nft/:nftId', 'nft.Nft/detail')->pattern(['nftId' => '\d+']);

    /* ---------- 基础运营 ---------- */
    Route::get('basic/app', 'nft.Basic/app')->completeMatch();
    Route::get('basic/banner', 'nft.Basic/banner')->completeMatch();
    Route::get('basic/notice/category', 'nft.Basic/noticeCategory')->completeMatch();
    Route::get('basic/notice/detail', 'nft.Basic/noticeDetail')->completeMatch();
    Route::get('basic/notice', 'nft.Basic/notice')->completeMatch();
    Route::get('basic/notice/:id', 'nft.Basic/noticeInfo')->pattern(['id' => '\d+']);

    /* ---------- 活动：签到 / 积分 / 抽奖 ---------- */
    Route::get('activity/sign', 'nft.Activity/sign')->completeMatch();
    Route::post('activity/sign', 'nft.Activity/doSign')->completeMatch();
    Route::get('activity/points/balance', 'nft.Activity/pointsBalance')->completeMatch();
    Route::get('activity/lottery/active', 'nft.Activity/lotteryActive')->completeMatch();
    Route::get('activity/lottery/log', 'nft.Activity/lotteryLog')->completeMatch();
    Route::post('activity/lottery/start/:id', 'nft.Activity/lotteryStart')->pattern(['id' => '\d+']);

    /* ---------- 合成（浏览免登录，兑换登录） ---------- */
    Route::get('item/merge', 'nft.Item/mergeIndex')->completeMatch();
    Route::get('item/merge/:id', 'nft.Item/mergeDetail')->pattern(['id' => '\d+']);
    Route::post('nft/merge/v1', 'nft.Nft/mergeV1')->completeMatch();

    /* ---------- 盲盒 ---------- */
    Route::post('nft/box/open', 'nft.Nft/boxOpen')->completeMatch();

    /* ---------- IM 群组 ---------- */
    Route::get('im/group', 'nft.Im/group')->completeMatch();
    Route::post('im/group/join/:id', 'nft.Im/join')->pattern(['id' => '\d+']);

})->middleware([BearerToken::class, CStyleResponse::class]);
