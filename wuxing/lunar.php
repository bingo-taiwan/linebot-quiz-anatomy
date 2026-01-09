<?php
/**
 * 農曆轉換模組
 * 西曆轉農曆、計算天干地支
 */

class LunarCalendar {
    // 農曆資料 (1900-2100)
    // 每年資料格式: bits 0-3: 閏月月份(0=無), bits 4-15: 月份大小(1=30天,0=29天), bit 16: 閏月大小
    private static $lunarInfo = [
        0x04bd8, 0x04ae0, 0x0a570, 0x054d5, 0x0d260, 0x0d950, 0x16554, 0x056a0, 0x09ad0, 0x055d2,  // 1900-1909
        0x04ae0, 0x0a5b6, 0x0a4d0, 0x0d250, 0x1d255, 0x0b540, 0x0d6a0, 0x0ada2, 0x095b0, 0x14977,  // 1910-1919
        0x04970, 0x0a4b0, 0x0b4b5, 0x06a50, 0x06d40, 0x1ab54, 0x02b60, 0x09570, 0x052f2, 0x04970,  // 1920-1929
        0x06566, 0x0d4a0, 0x0ea50, 0x06e95, 0x05ad0, 0x02b60, 0x186e3, 0x092e0, 0x1c8d7, 0x0c950,  // 1930-1939
        0x0d4a0, 0x1d8a6, 0x0b550, 0x056a0, 0x1a5b4, 0x025d0, 0x092d0, 0x0d2b2, 0x0a950, 0x0b557,  // 1940-1949
        0x06ca0, 0x0b550, 0x15355, 0x04da0, 0x0a5b0, 0x14573, 0x052b0, 0x0a9a8, 0x0e950, 0x06aa0,  // 1950-1959
        0x0aea6, 0x0ab50, 0x04b60, 0x0aae4, 0x0a570, 0x05260, 0x0f263, 0x0d950, 0x05b57, 0x056a0,  // 1960-1969
        0x096d0, 0x04dd5, 0x04ad0, 0x0a4d0, 0x0d4d4, 0x0d250, 0x0d558, 0x0b540, 0x0b6a0, 0x195a6,  // 1970-1979
        0x095b0, 0x049b0, 0x0a974, 0x0a4b0, 0x0b27a, 0x06a50, 0x06d40, 0x0af46, 0x0ab60, 0x09570,  // 1980-1989
        0x04af5, 0x04970, 0x064b0, 0x074a3, 0x0ea50, 0x06b58, 0x055c0, 0x0ab60, 0x096d5, 0x092e0,  // 1990-1999
        0x0c960, 0x0d954, 0x0d4a0, 0x0da50, 0x07552, 0x056a0, 0x0abb7, 0x025d0, 0x092d0, 0x0cab5,  // 2000-2009
        0x0a950, 0x0b4a0, 0x0baa4, 0x0ad50, 0x055d9, 0x04ba0, 0x0a5b0, 0x15176, 0x052b0, 0x0a930,  // 2010-2019
        0x07954, 0x06aa0, 0x0ad50, 0x05b52, 0x04b60, 0x0a6e6, 0x0a4e0, 0x0d260, 0x0ea65, 0x0d530,  // 2020-2029
        0x05aa0, 0x076a3, 0x096d0, 0x04afb, 0x04ad0, 0x0a4d0, 0x1d0b6, 0x0d250, 0x0d520, 0x0dd45,  // 2030-2039
        0x0b5a0, 0x056d0, 0x055b2, 0x049b0, 0x0a577, 0x0a4b0, 0x0aa50, 0x1b255, 0x06d20, 0x0ada0,  // 2040-2049
        0x14b63, 0x09370, 0x049f8, 0x04970, 0x064b0, 0x168a6, 0x0ea50, 0x06b20, 0x1a6c4, 0x0aae0,  // 2050-2059
        0x0a2e0, 0x0d2e3, 0x0c960, 0x0d557, 0x0d4a0, 0x0da50, 0x05d55, 0x056a0, 0x0a6d0, 0x055d4,  // 2060-2069
        0x052d0, 0x0a9b8, 0x0a950, 0x0b4a0, 0x0b6a6, 0x0ad50, 0x055a0, 0x0aba4, 0x0a5b0, 0x052b0,  // 2070-2079
        0x0b273, 0x06930, 0x07337, 0x06aa0, 0x0ad50, 0x14b55, 0x04b60, 0x0a570, 0x054e4, 0x0d160,  // 2080-2089
        0x0e968, 0x0d520, 0x0daa0, 0x16aa6, 0x056d0, 0x04ae0, 0x0a9d4, 0x0a2d0, 0x0d150, 0x0f252,  // 2090-2099
        0x0d520,  // 2100
    ];

    // 天干
    private static $tianGan = ['甲', '乙', '丙', '丁', '戊', '己', '庚', '辛', '壬', '癸'];

    // 地支
    private static $diZhi = ['子', '丑', '寅', '卯', '辰', '巳', '午', '未', '申', '酉', '戌', '亥'];

    // 生肖
    private static $shengXiao = ['鼠', '牛', '虎', '兔', '龍', '蛇', '馬', '羊', '猴', '雞', '狗', '豬'];

    // 農曆月份名稱
    private static $lunarMonthName = ['正', '二', '三', '四', '五', '六', '七', '八', '九', '十', '冬', '臘'];

    // 農曆日期名稱
    private static $lunarDayName = [
        '初一', '初二', '初三', '初四', '初五', '初六', '初七', '初八', '初九', '初十',
        '十一', '十二', '十三', '十四', '十五', '十六', '十七', '十八', '十九', '二十',
        '廿一', '廿二', '廿三', '廿四', '廿五', '廿六', '廿七', '廿八', '廿九', '三十'
    ];

    // 星期
    private static $weekDay = ['日', '一', '二', '三', '四', '五', '六'];

    /**
     * 取得農曆年閏月月份 (0表示無閏月)
     */
    private static function getLeapMonth($year) {
        $idx = $year - 1900;
        if ($idx < 0 || $idx >= count(self::$lunarInfo)) return 0;
        return self::$lunarInfo[$idx] & 0xf;
    }

    /**
     * 取得農曆年閏月的天數
     */
    private static function getLeapMonthDays($year) {
        if (self::getLeapMonth($year)) {
            $idx = $year - 1900;
            return (self::$lunarInfo[$idx] & 0x10000) ? 30 : 29;
        }
        return 0;
    }

    /**
     * 取得農曆某月的天數
     */
    private static function getLunarMonthDays($year, $month) {
        $idx = $year - 1900;
        if ($idx < 0 || $idx >= count(self::$lunarInfo)) return 30;
        return (self::$lunarInfo[$idx] & (0x10000 >> $month)) ? 30 : 29;
    }

    /**
     * 取得農曆年的總天數
     */
    private static function getLunarYearDays($year) {
        $sum = 348;
        $idx = $year - 1900;
        if ($idx < 0 || $idx >= count(self::$lunarInfo)) return 365;

        for ($i = 0x8000; $i > 0x8; $i >>= 1) {
            $sum += (self::$lunarInfo[$idx] & $i) ? 1 : 0;
        }
        return $sum + self::getLeapMonthDays($year);
    }

    /**
     * 西曆轉農曆 - 改良版
     */
    public static function solarToLunar($year, $month, $day) {
        // 1900年1月31日 = 農曆庚子年正月初一
        $baseDate = strtotime('1900-01-31');
        $targetDate = strtotime("$year-$month-$day");
        $offset = intval(($targetDate - $baseDate) / 86400);

        // 計算農曆年
        $lunarYear = 1900;
        while ($lunarYear < 2100) {
            $daysInYear = self::getLunarYearDays($lunarYear);
            if ($offset < $daysInYear) break;
            $offset -= $daysInYear;
            $lunarYear++;
        }

        // 計算農曆月和日
        $leapMonth = self::getLeapMonth($lunarYear);
        $isLeap = false;
        $lunarMonth = 1;

        while ($lunarMonth <= 12) {
            $daysInMonth = self::getLunarMonthDays($lunarYear, $lunarMonth);

            // 先處理閏月前的正常月份
            if ($offset < $daysInMonth) {
                break;
            }
            $offset -= $daysInMonth;

            // 處理閏月
            if ($leapMonth == $lunarMonth) {
                $leapDays = self::getLeapMonthDays($lunarYear);
                if ($offset < $leapDays) {
                    $isLeap = true;
                    break;
                }
                $offset -= $leapDays;
            }

            $lunarMonth++;
        }

        $lunarDay = $offset + 1;

        return [
            'lunarYear' => $lunarYear,
            'lunarMonth' => $lunarMonth,
            'lunarDay' => (int)$lunarDay,
            'isLeap' => $isLeap,
            'lunarMonthName' => ($isLeap ? '閏' : '') . self::$lunarMonthName[$lunarMonth - 1] . '月',
            'lunarDayName' => self::$lunarDayName[(int)$lunarDay - 1],
        ];
    }

    /**
     * 計算干支
     */
    public static function getGanZhi($year, $month, $day) {
        // 年干支 - 以農曆年計算（簡化處理，不考慮立春）
        $lunar = self::solarToLunar($year, $month, $day);
        $lunarYear = $lunar['lunarYear'];

        $yearGanIdx = ($lunarYear - 4) % 10;
        $yearZhiIdx = ($lunarYear - 4) % 12;
        if ($yearGanIdx < 0) $yearGanIdx += 10;
        if ($yearZhiIdx < 0) $yearZhiIdx += 12;

        // 月干支 - 農曆月對應地支（正月=寅，二月=卯，...，十一月=子，十二月=丑）
        // 月地支 = (農曆月 + 1) % 12 (正月=2=寅)
        $monthZhiIdx = ($lunar['lunarMonth'] + 1) % 12;

        // 月天干 = 年干 * 2 + 月地支調整
        // 甲己年起丙寅月，乙庚年起戊寅月，丙辛年起庚寅月，丁壬年起壬寅月，戊癸年起甲寅月
        $yearGanBase = $yearGanIdx % 5;
        $monthGanStart = ($yearGanBase * 2 + 2) % 10; // 寅月的天干
        $monthOffset = ($lunar['lunarMonth'] - 1);
        $monthGanIdx = ($monthGanStart + $monthOffset) % 10;

        // 日干支 - 使用精確公式
        // 基準：1900年1月1日 = 甲戌日 (天干=0, 地支=10)
        $baseDate = strtotime('1900-01-01');
        $targetDate = strtotime("$year-$month-$day");
        $daysDiff = intval(($targetDate - $baseDate) / 86400);

        $dayGanIdx = (0 + $daysDiff) % 10;
        $dayZhiIdx = (10 + $daysDiff) % 12;
        if ($dayGanIdx < 0) $dayGanIdx += 10;
        if ($dayZhiIdx < 0) $dayZhiIdx += 12;

        return [
            'yearGan' => self::$tianGan[$yearGanIdx],
            'yearZhi' => self::$diZhi[$yearZhiIdx],
            'yearGanZhi' => self::$tianGan[$yearGanIdx] . self::$diZhi[$yearZhiIdx],
            'monthGan' => self::$tianGan[$monthGanIdx],
            'monthZhi' => self::$diZhi[$monthZhiIdx],
            'monthGanZhi' => self::$tianGan[$monthGanIdx] . self::$diZhi[$monthZhiIdx],
            'dayGan' => self::$tianGan[$dayGanIdx],
            'dayZhi' => self::$diZhi[$dayZhiIdx],
            'dayGanZhi' => self::$tianGan[$dayGanIdx] . self::$diZhi[$dayZhiIdx],
            'dayGanIdx' => $dayGanIdx,
            'dayZhiIdx' => $dayZhiIdx,
            'shengXiao' => self::$shengXiao[$yearZhiIdx],
        ];
    }

    /**
     * 取得星期幾
     */
    public static function getWeekDay($year, $month, $day) {
        $weekIdx = date('w', strtotime("$year-$month-$day"));
        return self::$weekDay[$weekIdx];
    }

    /**
     * 取得完整日期資訊
     */
    public static function getFullDateInfo($year = null, $month = null, $day = null) {
        if ($year === null) $year = (int)date('Y');
        if ($month === null) $month = (int)date('n');
        if ($day === null) $day = (int)date('j');

        $lunar = self::solarToLunar($year, $month, $day);
        $ganZhi = self::getGanZhi($year, $month, $day);
        $weekDay = self::getWeekDay($year, $month, $day);

        return [
            'solar' => [
                'year' => $year,
                'month' => $month,
                'day' => $day,
                'weekDay' => $weekDay,
                'dateStr' => "{$year}年{$month}月{$day}日",
                'weekStr' => "星期{$weekDay}",
            ],
            'lunar' => $lunar,
            'ganZhi' => $ganZhi,
        ];
    }
}
