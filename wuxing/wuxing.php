<?php
/**
 * 五行穿衣模組
 * 根據日地支五行計算每日穿衣顏色吉凶
 */

// 設定台北時區
date_default_timezone_set('Asia/Taipei');

require_once __DIR__ . '/lunar.php';

class WuXing {
    // 地支對應五行 (0=木, 1=火, 2=土, 3=金, 4=水)
    // 子=水, 丑=土, 寅=木, 卯=木, 辰=土, 巳=火, 午=火, 未=土, 申=金, 酉=金, 戌=土, 亥=水
    private static $zhiWuXing = [
        0 => 4,   // 子 - 水
        1 => 2,   // 丑 - 土
        2 => 0,   // 寅 - 木
        3 => 0,   // 卯 - 木
        4 => 2,   // 辰 - 土
        5 => 1,   // 巳 - 火
        6 => 1,   // 午 - 火
        7 => 2,   // 未 - 土
        8 => 3,   // 申 - 金
        9 => 3,   // 酉 - 金
        10 => 2,  // 戌 - 土
        11 => 4,  // 亥 - 水
    ];

    // 五行名稱
    private static $wuXingName = ['木', '火', '土', '金', '水'];

    // 五行對應顏色
    private static $wuXingColors = [
        0 => ['name' => '綠色、青色、翠色、淺綠系', 'colors' => ['綠色', '青色', '翠色', '淺綠']],
        1 => ['name' => '紅色、粉色、橙色、紫色、花色系', 'colors' => ['紅色', '粉色', '橙色', '紫色']],
        2 => ['name' => '黃色、咖啡、棕色、卡其、褐色系', 'colors' => ['黃色', '咖啡', '棕色', '卡其']],
        3 => ['name' => '白色、銀色、杏色、乳白色系', 'colors' => ['白色', '銀色', '杏色', '乳白']],
        4 => ['name' => '黑色、藍色、灰色系', 'colors' => ['黑色', '藍色', '灰色']],
    ];

    // 五行顯示色塊顏色
    private static $displayColors = [
        0 => '#4CAF50',  // 木 - 綠
        1 => '#F44336',  // 火 - 紅
        2 => '#FFC107',  // 土 - 黃
        3 => '#E0E0E0',  // 金 - 淺灰（代表白）
        4 => '#333333',  // 水 - 黑
    ];

    // ========== 星座相關資料 ==========
    // 12星座名稱（按黃道順序，從白羊座開始）
    private static $zodiacSigns = [
        0 => '白羊座',  // Aries (3/21-4/19)
        1 => '金牛座',  // Taurus (4/20-5/20)
        2 => '雙子座',  // Gemini (5/21-6/20)
        3 => '巨蟹座',  // Cancer (6/21-7/22)
        4 => '獅子座',  // Leo (7/23-8/22)
        5 => '處女座',  // Virgo (8/23-9/22)
        6 => '天秤座',  // Libra (9/23-10/22)
        7 => '天蠍座',  // Scorpio (10/23-11/21)
        8 => '射手座',  // Sagittarius (11/22-12/21)
        9 => '魔羯座',  // Capricorn (12/22-1/19)
        10 => '水瓶座', // Aquarius (1/20-2/18)
        11 => '雙魚座', // Pisces (2/19-3/20)
    ];

    // 星座元素分類
    // 火象星座(Fire): 白羊(0)、獅子(4)、射手(8)
    // 土象星座(Earth): 金牛(1)、處女(5)、魔羯(9)
    // 風象星座(Air): 雙子(2)、天秤(6)、水瓶(10)
    // 水象星座(Water): 巨蟹(3)、天蠍(7)、雙魚(11)
    private static $zodiacElements = [
        0 => 'fire',   // 白羊
        1 => 'earth',  // 金牛
        2 => 'air',    // 雙子
        3 => 'water',  // 巨蟹
        4 => 'fire',   // 獅子
        5 => 'earth',  // 處女
        6 => 'air',    // 天秤
        7 => 'water',  // 天蠍
        8 => 'fire',   // 射手
        9 => 'earth',  // 魔羯
        10 => 'air',   // 水瓶
        11 => 'water', // 雙魚
    ];

    // 元素相容關係（基於占星學 Trine 三分相 120° 和 Sextile 六分相 60°）
    // Trine: 同元素星座（最和諧）
    // Sextile: 火-風、土-水（次和諧）
    // Square: 火-水、土-風（挑戰性）
    private static $elementCompatibility = [
        'fire' => ['trine' => 'fire', 'sextile' => 'air', 'square' => ['water', 'earth']],
        'earth' => ['trine' => 'earth', 'sextile' => 'water', 'square' => ['fire', 'air']],
        'air' => ['trine' => 'air', 'sextile' => 'fire', 'square' => ['water', 'earth']],
        'water' => ['trine' => 'water', 'sextile' => 'earth', 'square' => ['fire', 'air']],
    ];

    // 星座日期範圍（月, 日開始, 日結束）
    private static $zodiacDates = [
        0 => [3, 21, 4, 19],   // 白羊座
        1 => [4, 20, 5, 20],   // 金牛座
        2 => [5, 21, 6, 20],   // 雙子座
        3 => [6, 21, 7, 22],   // 巨蟹座
        4 => [7, 23, 8, 22],   // 獅子座
        5 => [8, 23, 9, 22],   // 處女座
        6 => [9, 23, 10, 22],  // 天秤座
        7 => [10, 23, 11, 21], // 天蠍座
        8 => [11, 22, 12, 21], // 射手座
        9 => [12, 22, 1, 19],  // 魔羯座
        10 => [1, 20, 2, 18],  // 水瓶座
        11 => [2, 19, 3, 20],  // 雙魚座
    ];

    /**
     * 五行相生：木生火、火生土、土生金、金生水、水生木
     * 我生者
     */
    private static function getSheng($wuxing) {
        return ($wuxing + 1) % 5;
    }

    /**
     * 五行相剋：木剋土、火剋金、土剋水、金剋木、水剋火
     * 我剋者
     */
    private static function getKe($wuxing) {
        return ($wuxing + 2) % 5;
    }

    /**
     * 生我者
     */
    private static function getBeSheng($wuxing) {
        return ($wuxing + 4) % 5;
    }

    /**
     * 剋我者
     */
    private static function getBeKe($wuxing) {
        return ($wuxing + 3) % 5;
    }

    /**
     * 計算穿衣顏色吉凶 - 使用日地支五行
     */
    public static function calculateDressColors($dayZhiIdx) {
        $dayWuXing = self::$zhiWuXing[$dayZhiIdx];

        // 吉（大吉色）：被當日五行所生 = 我生者
        $daJi = self::getSheng($dayWuXing);

        // 次吉（幸運色）：與當日五行相同
        $ciJi = $dayWuXing;

        // 平（平平色）：剋當日五行 = 剋我者
        $ping = self::getBeKe($dayWuXing);

        // 較差（消耗色）：生當日五行 = 生我者
        $xiaoCha = self::getBeSheng($dayWuXing);

        // 不宜（不利色）：被當日五行所剋 = 我剋者
        $buYi = self::getKe($dayWuXing);

        return [
            'dayWuXing' => self::$wuXingName[$dayWuXing],
            'levels' => [
                [
                    'level' => '大吉',
                    'name' => '旺運色',
                    'wuxing' => self::$wuXingName[$daJi],
                    'colors' => self::$wuXingColors[$daJi]['name'],
                    'displayColor' => self::$displayColors[$daJi],
                    'description' => '今天穿這色超旺der～大環境幫你Carry，貴人自動找上門，桃花運也跟著來，整個氣場對了！',
                ],
                [
                    'level' => '次吉',
                    'name' => '好運色',
                    'wuxing' => self::$wuXingName[$ciJi],
                    'colors' => self::$wuXingColors[$ciJi]['name'],
                    'displayColor' => self::$displayColors[$ciJi],
                    'description' => '跟今日磁場同頻～談合作、聊生意都很OK，人際關係順順的！',
                ],
                [
                    'level' => '平',
                    'name' => '平平色',
                    'wuxing' => self::$wuXingName[$ping],
                    'colors' => self::$wuXingColors[$ping]['name'],
                    'displayColor' => self::$displayColors[$ping],
                    'description' => '要拚一點才有收穫，但只要肯努力，成功了就是大豐收！適合想挑戰自我的人～',
                ],
                [
                    'level' => '較差',
                    'name' => '耗能色',
                    'wuxing' => self::$wuXingName[$xiaoCha],
                    'colors' => self::$wuXingColors[$xiaoCha]['name'],
                    'displayColor' => self::$displayColors[$xiaoCha],
                    'description' => '穿這色會比較累，好像一直在輸出能量給環境，心臟要夠大顆再挑戰！',
                ],
                [
                    'level' => '不宜',
                    'name' => 'NG色',
                    'wuxing' => self::$wuXingName[$buYi],
                    'colors' => self::$wuXingColors[$buYi]['name'],
                    'displayColor' => self::$displayColors[$buYi],
                    'description' => '今天最好避開這色～容易卡卡的、事倍功半，做白工的機率偏高QQ',
                ],
            ],
        ];
    }

    /**
     * 根據日期取得當日太陽星座索引
     * 基於占星學，太陽每月約在同一時間進入下一個星座
     */
    public static function getSunSignIndex($month, $day) {
        // 星座順序對照日期
        $signs = [
            // [開始月, 開始日, 結束月, 結束日, 星座索引]
            [3, 21, 4, 19, 0],   // 白羊座
            [4, 20, 5, 20, 1],   // 金牛座
            [5, 21, 6, 20, 2],   // 雙子座
            [6, 21, 7, 22, 3],   // 巨蟹座
            [7, 23, 8, 22, 4],   // 獅子座
            [8, 23, 9, 22, 5],   // 處女座
            [9, 23, 10, 22, 6],  // 天秤座
            [10, 23, 11, 21, 7], // 天蠍座
            [11, 22, 12, 21, 8], // 射手座
            [12, 22, 12, 31, 9], // 魔羯座（12月）
            [1, 1, 1, 19, 9],    // 魔羯座（1月）
            [1, 20, 2, 18, 10],  // 水瓶座
            [2, 19, 3, 20, 11],  // 雙魚座
        ];

        foreach ($signs as $range) {
            if ($month == $range[0] && $day >= $range[1]) {
                if ($range[0] == $range[2] || $month < $range[2]) {
                    return $range[4];
                }
            }
            if ($month == $range[2] && $day <= $range[3]) {
                return $range[4];
            }
        }

        return 9; // 預設魔羯座（不應該到達這裡）
    }

    /**
     * 計算星座運勢 - 基於占星學相位理論
     *
     * 算法依據：
     * - Trine（三分相 120°）：同元素星座最和諧
     * - Sextile（六分相 60°）：相容元素次和諧（火-風、土-水）
     * - Square（四分相 90°）：挑戰性相位（火-水、土-風）
     *
     * 參考來源：
     * - Cafe Astrology: https://cafeastrology.com/articles/aspectsinastrology.html
     * - Astrologyk Element Compatibility: https://astrologyk.com/zodiac/elements/compatibility
     */
    public static function calculateZodiacHoroscope($month, $day) {
        // 取得當日太陽星座
        $sunSignIdx = self::getSunSignIndex($month, $day);
        $sunElement = self::$zodiacElements[$sunSignIdx];

        $teJi = [];   // 特吉 - Trine（同元素）
        $ciJi = [];   // 次吉 - Sextile（相容元素）
        $zhuYi = [];  // 注意 - Square（挑戰元素）

        // 根據元素相位分類所有星座
        for ($i = 0; $i < 12; $i++) {
            $signElement = self::$zodiacElements[$i];
            $signName = self::$zodiacSigns[$i];

            if ($signElement === self::$elementCompatibility[$sunElement]['trine']) {
                // Trine - 同元素，最和諧
                $teJi[] = $signName;
            } elseif ($signElement === self::$elementCompatibility[$sunElement]['sextile']) {
                // Sextile - 相容元素，次和諧
                $ciJi[] = $signName;
            } elseif (in_array($signElement, self::$elementCompatibility[$sunElement]['square'])) {
                // Square - 挑戰元素
                $zhuYi[] = $signName;
            }
        }

        return [
            'sunSign' => self::$zodiacSigns[$sunSignIdx],
            'sunElement' => $sunElement,
            'teJi' => $teJi,
            'ciJi' => $ciJi,
            'zhuYi' => $zhuYi,
        ];
    }

    /**
     * 取得今日五行穿衣完整資訊
     */
    public static function getTodayInfo($year = null, $month = null, $day = null) {
        $dateInfo = LunarCalendar::getFullDateInfo($year, $month, $day);
        // 改用日地支計算五行穿衣
        $dressColors = self::calculateDressColors($dateInfo['ganZhi']['dayZhiIdx']);
        // 改用太陽星座計算星座運勢
        $horoscope = self::calculateZodiacHoroscope(
            $dateInfo['solar']['month'],
            $dateInfo['solar']['day']
        );

        return [
            'date' => $dateInfo,
            'dress' => $dressColors,
            'horoscope' => $horoscope,
        ];
    }

    /**
     * 生成 Flex Message
     * @param bool $isTomorrow 是否為明日
     */
    public static function generateFlexMessage($year = null, $month = null, $day = null, $isTomorrow = false) {
        $info = self::getTodayInfo($year, $month, $day);
        $solar = $info['date']['solar'];
        $lunar = $info['date']['lunar'];
        $ganZhi = $info['date']['ganZhi'];
        $dress = $info['dress'];
        $horoscope = $info['horoscope'];

        // 顏色區塊
        $colorBoxes = [];
        foreach ($dress['levels'] as $level) {
            $bgColor = $level['displayColor'];
            $colorBoxes[] = [
                'type' => 'box',
                'layout' => 'horizontal',
                'contents' => [
                    [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [],
                        'width' => '28px',
                        'height' => '28px',
                        'backgroundColor' => $bgColor,
                        'cornerRadius' => '6px',
                    ],
                    [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => "{$level['level']}《{$level['name']}》：{$level['colors']}",
                                'size' => 'md',
                                'weight' => 'bold',
                                'color' => '#333333',
                                'wrap' => true,
                            ],
                            [
                                'type' => 'text',
                                'text' => $level['description'],
                                'size' => 'sm',
                                'color' => '#666666',
                                'wrap' => true,
                                'margin' => 'sm',
                            ],
                        ],
                        'flex' => 1,
                        'paddingStart' => '12px',
                    ],
                ],
                'margin' => 'xl',
                'alignItems' => 'flex-start',
            ];
        }

        // 星座運勢區塊（基於占星學相位理論）
        // 元素名稱對照
        $elementNames = [
            'fire' => '火象',
            'earth' => '土象',
            'air' => '風象',
            'water' => '水象',
        ];
        $elementName = $elementNames[$horoscope['sunElement']] ?? '';

        $horoscopeBox = [
            'type' => 'box',
            'layout' => 'vertical',
            'contents' => [
                [
                    'type' => 'separator',
                    'margin' => 'xl',
                ],
                [
                    'type' => 'text',
                    'text' => "今日星座運勢（{$horoscope['sunSign']}・{$elementName}）",
                    'weight' => 'bold',
                    'size' => 'md',
                    'color' => '#333333',
                    'margin' => 'xl',
                ],
                [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => '特吉：' . implode('、', $horoscope['teJi']),
                            'size' => 'sm',
                            'color' => '#4CAF50',
                            'weight' => 'bold',
                            'wrap' => true,
                        ],
                    ],
                    'margin' => 'md',
                ],
                [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => '次吉：' . implode('、', $horoscope['ciJi']),
                            'size' => 'sm',
                            'color' => '#333333',
                            'wrap' => true,
                        ],
                    ],
                    'margin' => 'sm',
                ],
                [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => '注意：' . implode('、', $horoscope['zhuYi']),
                            'size' => 'sm',
                            'color' => '#F44336',
                            'wrap' => true,
                        ],
                    ],
                    'margin' => 'sm',
                ],
            ],
        ];

        // 根據是否為明日調整標題
        $titlePrefix = $isTomorrow ? '明日' : '今日';
        $headerColor = $isTomorrow ? '#2E7D32' : '#8B4513';  // 明日用綠色，今日用棕色

        $flex = [
            'type' => 'flex',
            'altText' => "{$titlePrefix}五行穿衣 - {$solar['dateStr']}",
            'contents' => [
                'type' => 'bubble',
                'size' => 'mega',
                'header' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => "{$titlePrefix}五行穿搭指南",
                            'weight' => 'bold',
                            'size' => 'xl',
                            'color' => '#ffffff',
                            'align' => 'center',
                        ],
                    ],
                    'backgroundColor' => $headerColor,
                    'paddingAll' => '18px',
                ],
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => array_merge(
                        [
                            // 日期區塊
                            [
                                'type' => 'box',
                                'layout' => 'horizontal',
                                'contents' => [
                                    [
                                        'type' => 'box',
                                        'layout' => 'vertical',
                                        'contents' => [
                                            [
                                                'type' => 'text',
                                                'text' => (string)$solar['day'],
                                                'size' => '3xl',
                                                'weight' => 'bold',
                                                'color' => '#333333',
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => "{$solar['year']}年",
                                                'size' => 'sm',
                                                'color' => '#666666',
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => "{$solar['month']}月",
                                                'size' => 'sm',
                                                'color' => '#666666',
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => $solar['weekStr'],
                                                'size' => 'sm',
                                                'color' => '#666666',
                                            ],
                                        ],
                                        'flex' => 0,
                                        'alignItems' => 'center',
                                    ],
                                    [
                                        'type' => 'box',
                                        'layout' => 'vertical',
                                        'contents' => [
                                            [
                                                'type' => 'text',
                                                'text' => "{$lunar['lunarMonthName']}{$lunar['lunarDayName']}",
                                                'size' => 'xl',
                                                'weight' => 'bold',
                                                'color' => '#333333',
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => "{$ganZhi['yearGanZhi']}年 {$ganZhi['monthGanZhi']}月 {$ganZhi['dayGanZhi']}日",
                                                'size' => 'md',
                                                'color' => '#666666',
                                                'margin' => 'md',
                                            ],
                                            [
                                                'type' => 'text',
                                                'text' => "{$titlePrefix}五行：{$dress['dayWuXing']}",
                                                'size' => 'md',
                                                'color' => $headerColor,
                                                'weight' => 'bold',
                                                'margin' => 'sm',
                                            ],
                                        ],
                                        'flex' => 1,
                                        'paddingStart' => '20px',
                                        'justifyContent' => 'center',
                                    ],
                                ],
                                'paddingBottom' => '15px',
                            ],
                            [
                                'type' => 'separator',
                                'margin' => 'md',
                            ],
                        ],
                        $colorBoxes,
                        [$horoscopeBox]
                    ),
                    'paddingAll' => '18px',
                ],
                'footer' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => $isTomorrow ? [
                        // 明日圖卡：顯示「今日五行穿衣」按鈕
                        [
                            'type' => 'button',
                            'style' => 'primary',
                            'color' => '#8B4513',
                            'action' => [
                                'type' => 'message',
                                'label' => '今日五行穿衣',
                                'text' => '五行穿衣',
                            ],
                        ],
                        [
                            'type' => 'button',
                            'style' => 'secondary',
                            'action' => [
                                'type' => 'message',
                                'label' => '返回主選單',
                                'text' => '選單',
                            ],
                            'margin' => 'sm',
                        ],
                    ] : [
                        // 今日圖卡：顯示「明日五行穿衣」按鈕
                        [
                            'type' => 'button',
                            'style' => 'primary',
                            'color' => '#2E7D32',
                            'action' => [
                                'type' => 'message',
                                'label' => '明日五行穿衣',
                                'text' => '明日五行穿衣',
                            ],
                        ],
                        [
                            'type' => 'button',
                            'style' => 'secondary',
                            'action' => [
                                'type' => 'message',
                                'label' => '返回主選單',
                                'text' => '選單',
                            ],
                            'margin' => 'sm',
                        ],
                    ],
                    'paddingAll' => '12px',
                ],
            ],
        ];

        return $flex;
    }

    /**
     * 生成明日五行穿衣 Flex Message
     */
    public static function generateTomorrowFlexMessage() {
        $tomorrow = strtotime('+1 day');
        $year = (int)date('Y', $tomorrow);
        $month = (int)date('n', $tomorrow);
        $day = (int)date('j', $tomorrow);

        return self::generateFlexMessage($year, $month, $day, true);
    }
}
