<?php

return [
    'type' => [
//        'fast_three' => '快三',
        'minute' => '分分彩',
    ],
    'options' => [
//        'fast_three' => [],
        'minute' => [
            'sum' => [
                'big', 'small', 'single', 'double', 'dragon', 'tiger', 'draw'
            ],
            'ball_1' => [
                '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'big', 'small', 'single', 'double'
            ],
            'ball_2' => [
                '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'big', 'small', 'single', 'double'
            ],
            'ball_3' => [
                '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'big', 'small', 'single', 'double'
            ],
            'ball_4' => [
                '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'big', 'small', 'single', 'double'
            ],
            'ball_5' => [
                '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'big', 'small', 'single', 'double'
            ],
            // 豹子，顺子，对子，半顺，杂六
            'before_three' => ['trips', 'sequence', 'pair', 'half_sequence', 'other'],
            'middle_three' => ['trips', 'sequence', 'pair', 'half_sequence', 'other'],
            'after_three' => ['trips', 'sequence', 'pair', 'half_sequence', 'other'],
        ],
    ],

    'options_text' => [
//        'fast_three' => [],
        'minute' => [
            'ball_1' => '第一球',
            'ball_2' => '第二球',
            'ball_3' => '第三球',
            'ball_4' => '第四球',
            'ball_5' => '第五球',
            'big' => '大',
            'small' => '小',
            'single' => '单',
            'double' => '双',
            'sum' => '总和,龙虎和',
            'dragon' => '龙',
            'tiger' => '虎',
            'draw' => '和',
            // 豹子，顺子，对子，半顺，杂六
            'three' => '前三/中三/后三',
            'before_three' => '前三',
            'middle_three' => '中三',
            'after_three' => '后三',
            'trips' => '豹子',
            'sequence' => '顺子',
            'pair' => '对子',
            'half_sequence' => '半顺',
            'other' => '杂六',
        ],
    ],


    'ball_numbers' => [
        'fast_three' => 3,
        'minute' => 5,
    ],
];