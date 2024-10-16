<?php

return [
    'action_wearing' => '佩戴',
    'admin' => [
        'list' => [
            'page_title' => '勋章列表'
        ]
    ],
    'get_types' => [
        \App\Models\Medal::GET_TYPE_EXCHANGE => '兑换',
        \App\Models\Medal::GET_TYPE_GRANT => '授予',
    ],
    'column' => [
        'image_large' => '图片',
        'description' => '描述',
        'enablebuytime' => '可购买时间',
        'duration' => '购买后有效期(天)',
        'bonus' => '魔力加成',
        'price' => '价格',
        'stock' => '库存',
        'buy' => '购买',
        'gift' => '赠送',
    ],
    'grant_only'=>'仅授予',
    'purchase_start'=>'超过购买时间',
    'purchase_end'=>'超过购买时间',
    'stock'=>'库存不足？',
    'confirm_to_buy'=>'确认购买？',
    'confirm_to_gift'=>'确认赠送？'
];
