<?php

return [

    'index' => [
        'page_title' => '私信列表',
    ],
    'show' => [
        'page_title' => '私信详情',
    ],
    'field_value_change_message_body' => ':field 被管理员 :operator 从 :old 改为 :new。理由：:reason。',
    'field_value_change_message_subject' => ':field 改变',

    'download_disable' => [
        'subject' => '下载权限取消',
        'body' => '你的下载权限被取消，可能的原因是过低的分享率或行为不当。By: :operator',
    ],
    'download_enable' => [
        'subject' => '下载权限恢复',
        'body' => '你的下载权限恢复，你现在可以下载种子。By: :operator',
    ],
    'receive_medal' => [
        'subject' => '收到赠送勋章',
        'body' => '用户 :username 花费魔力 :cost_bonus 购买了勋章[:medal_name]并赠送与你。此勋章价值 :price，手续费 :gift_fee_total，你将拥有此勋章有效期至: :expire_at，勋章的魔力加成系数为: :bonus。',
    ],
];
