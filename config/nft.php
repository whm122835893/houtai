<?php

// NFT 多 C 端开放 API 配置
return [
    // C 端响应契约风格：zhenhai={status,code,message,data}（源站包络）| standard=BuildAdmin 原生 {code,msg,time,data}
    // 每套 C 端独立部署实例时，通过环境变量 C_END_STYLE 指定该实例对接的 C 端契约
    'c_end_style' => env('C_END_STYLE', 'zhenhai'),

    // 短信调试模式：未接入短信网关时，验证码固定为 123456 并随接口返回
    // ⚠️ 生产环境必须置 false 并接入真实短信通道
    'sms_debug' => env('NFT_SMS_DEBUG', true),

    // RSA 密钥对（PKCS1，C 端密码加密传输；生成：openssl genrsa -out rsa.key 2048 && openssl rsa -in rsa.key -pubout -out rsa.pub）
    // 填 PEM 内容或 base64 裸密钥均可；未配置且 sms_debug=true 时允许明文密码（仅本地调试）
    'rsa_public_key' => env('NFT_RSA_PUBLIC', ''),
    'rsa_private_key' => env('NFT_RSA_PRIVATE', ''),

    // 应用品牌信息（GET /basic/app 返回体；多 C 端实例各自差异化）
    'brand' => [
        'app_name'      => env('NFT_APP_NAME', '臻海数藏'),
        'app_logo'      => env('NFT_APP_LOGO', ''),
        'home_banner'   => env('NFT_HOME_BANNER', ''),
        'hero_title_1'  => env('NFT_HERO_TITLE_1', '守护文化根脉'),
        'hero_title_2'  => env('NFT_HERO_TITLE_2', '链上数字藏品'),
        'hero_subtitle' => env('NFT_HERO_SUBTITLE', '让每一件藏品都有据可查'),
    ],
];
