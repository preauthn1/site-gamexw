<?php
namespace app\common\lib;
class ReverseUtils {

    /**
     * 生成完场反波胆
     * @param int $gameId
     * @param array $endBets 完场正波胆
     * @return []
     */
    public static function generateEndReverseBet($gameId = 0, $endBets = []) {
        if (!isArrayAndNotEmpty($endBets)) {
            return null;
        }
        // 第1步：计算每个正波胆赔率的倒数之和sum
        $sum = 0;
        $originalOddsMap = [];
        foreach ($endBets as $bet) {
            $originalOdds = $bet['odds'];    //原始正波胆赔率
            if ($originalOdds != 0) {
                $sum += 1 / $originalOdds;
            }
            $originalOddsMap[$bet['option']] = $originalOdds;
        }

//        dump($originalOddsMap);
        \think\facade\Log::write("sum={$sum}");

        // 第2步：计算反波胆：每个正波胆倒数除以所有正波胆倒数之和，乘以全局赔率0.9，乘以随机0.95-1.03，然后随机加减(+0.20%,-0.30%)
        $reverseBetList = [];
        $oddsHomeWinFour = 0;
        $oddsAwayWinFour = 0;
        $i = 0;
        foreach ($originalOddsMap as $option => $odds) {
            $r1 = random_int(0, 103 - 95) + 95;
            $r2 = random_int(0, 20 + 30) - 30;
            if (startsWith($option, "4")) {
                if ($odds > 0) {
                    $oddsHomeWinFour += 1 / $odds / $sum;
                }
            } else if (endsWith($option, "4")) {
                if ($odds > 0) {
                    $oddsAwayWinFour += 1 / $odds / $sum;
                }
            } else if ($option != "other") {    //比分不带4的波胆（其他除外），直接使用
                $i++;
                if ($odds > 0) {
                    $odds = (1 / $odds / $sum * 0.9 * $r1) + $r2 / 100;
                }
                if ($odds < 0) {
                    $odds = 0;
                }

                $betInfo = [
                    'game_id' => $gameId,
                    'type' => 'end',
                    'reverse' => true,
                    'option' => strval($option),
                    'odds' => sprintf("%.2f", $odds),
                    'orderNum' => $i
                ];

                // 重新设置显示排序，反波与正波不同
                if ($option == ("00")) {
                    $betInfo['orderNum'] = 1;
                } else if ($option == ("01")) {
                    $betInfo['orderNum'] = 2;
                } else if ($option == ("02")) {
                    $betInfo['orderNum'] = 3;
                } else if ($option == ("03")) {
                    $betInfo['orderNum'] = 4;
                } else if ($option == ("10")) {
                    $betInfo['orderNum'] = 5;
                } else if ($option == ("11")) {
                    $betInfo['orderNum'] = 6;
                } else if ($option == ("12")) {
                    $betInfo['orderNum'] = 7;
                } else if ($option == ("13")) {
                    $betInfo['orderNum'] = 8;
                } else if ($option == ("20")) {
                    $betInfo['orderNum'] = 9;
                } else if ($option == ("21")) {
                    $betInfo['orderNum'] = 10;
                } else if ($option == ("22")) {
                    $betInfo['orderNum'] = 11;
                } else if ($option == ("23")) {
                    $betInfo['orderNum'] = 12;
                } else if ($option == ("30")) {
                    $betInfo['orderNum'] = 13;
                } else if ($option == ("31")) {
                    $betInfo['orderNum'] = 14;
                } else if ($option == ("32")) {
                    $betInfo['orderNum'] = 15;
                } else if ($option == ("33")) {
                    $betInfo['orderNum'] = 16;
                }
                $reverseBetList[] = $betInfo;
            }
        }

        // 主队胜赢4球以上
        $r1HomeWinFour = random_int(0, 103 - 95) + 95;
        $r2HomeWinFour = random_int(0, 20 + 30) - 30;
        $betHomeWinFour = [
            'game_id' => $gameId,
            'type' => 'end',
            'reverse' => true,
            'option' => 'homeWin4',
//            'odds' => sprintf("%.2f", $odds),
            'orderNum' => 17,
        ];
        $dOddsHomeWinFour = $oddsHomeWinFour * 0.9 * $r1HomeWinFour + $r2HomeWinFour / 100;
        if ($dOddsHomeWinFour >= 5) {
            $dOddsHomeWinFour = $dOddsHomeWinFour / 4;
        }
        $betHomeWinFour['odds'] = sprintf("%.2f", $dOddsHomeWinFour);

        $r1AwayWinFour = random_int(0, 103 - 95) + 95;
        $r2AwayWinFour = random_int(0, 20 + 30) - 30;
        $betAwayWinFour = [
            'game_id' => $gameId,
            'type' => 'end',
            'reverse' => true,
            'option' => 'awayWin4',
//            'odds' => sprintf("%.2f", $odds),
            'orderNum' => 18,
        ];
        $dOddsAwayWinFour = $oddsAwayWinFour * 0.9 * $r1AwayWinFour + $r2AwayWinFour / 100;
        if ($dOddsAwayWinFour >= 5) {
            $dOddsAwayWinFour = $dOddsAwayWinFour / 4;
        }
        $betAwayWinFour['odds'] = sprintf("%.2f", $dOddsAwayWinFour);

        $reverseBetList[] = $betHomeWinFour;
        $reverseBetList[] = $betAwayWinFour;

        return $reverseBetList;
    }

    /**
     * 生成10个半场反波胆
     * 17个半场正波胆中的0-3、1-3、2-3、3-0、3-1、3-2、3-3这7个不生成对应的反波胆(赔率计入other)但要参与归一计算
     * @param int $gameId
     * @param array halfBets   半场正波胆
     * @return array
     */
    public static function generateHalfReverseBet($gameId, $halfBets) {
        if (!isArrayAndNotEmpty($halfBets)) {
            return null;
        }
        // 第1步：计算每个正波胆赔率的倒数之和sum
        $sum = 0;
        $originalOddsMap = [];
        foreach ($halfBets as $bet) {
            $originalOdds = $bet['odds'];    //原始正波胆赔率
            if ($originalOdds != 0) {
                $sum += 1 / $originalOdds;
            }
            $originalOddsMap[$bet['option']] = $originalOdds;
        }

        // 第2步：计算反波胆：每个正波胆倒数除以所有正波胆倒数之和，乘以全局赔率0.9，乘以随机0.95-1.03，然后随机加减(+0.20%,-0.30%)
        $reverseBetList = [];
        $oddsOther = 0;
        $i = 0;
        foreach ($originalOddsMap as $option => $odds) {
            if ($odds > 0) {
                $r1 = random_int(0, 103 - 95) + 95;
                $r2 = random_int(0, 20 + 30) - 30;
                if (strexists($option, "3") || $option == "other") { //3以上即其他，全部合计为其他
                    if ($odds > 0) {
                        $oddsOther += 1 / $odds / $sum;
                    }
                } else {    //比分不带3的波胆，直接使用
                    $i++;
                    if ($odds > 0) {
                        $odds = (1 / $odds / $sum * 0.9 * $r1) + $r2 / 100;
                    } else {
                        $odds = 0;
                    }
                    $betInfo = [
                        'game_id' => $gameId,
                        'type' => 'half',
                        'reverse' => true,
                        'option' => strval($option),
                        'odds' => sprintf("%.2f", $odds),
                        'orderNum' => $i
                    ];
                    // 重新设置显示排序，反波与正波不同
                    if ($option == ("00")) {
                        $betInfo['orderNum'] = 1;
                    } else if ($option == ("01")) {
                        $betInfo['orderNum'] = 2;
                    } else if ($option == ("02")) {
                        $betInfo['orderNum'] = 3;
                    } else if ($option == ("10")) {
                        $betInfo['orderNum'] = 4;
                    } else if ($option == ("11")) {
                        $betInfo['orderNum'] = 5;
                    } else if ($option == ("12")) {
                        $betInfo['orderNum'] = 6;
                    } else if ($option == ("20")) {
                        $betInfo['orderNum'] = 7;
                    } else if ($option == ("21")) {
                        $betInfo['orderNum'] = 8;
                    } else if ($option == ("22")) {
                        $betInfo['orderNum'] = 9;
                    }
                    $reverseBetList[] = $betInfo;
                }
            }
        }
        // 其他

        $r1Other = random_int(0, 103 - 95) + 95;
        $r2Other = random_int(0, 20 + 30) - 30;
        $betOther = [
            'game_id' => $gameId,
            'type' => 'half',
            'reverse' => true,
            'option' => 'other',
//            'odds' => sprintf("%.2f", $odds),
            'orderNum' => 18,
        ];
        // 正常算出来是9%左右，参考TG都是2%多，故强制除以4
        $betOther['odds'] = sprintf("%.2f", ($oddsOther * 0.9 * $r1Other + $r2Other / 100) / 4);

        $reverseBetList[] = $betOther;

        return $reverseBetList;
    }

}
