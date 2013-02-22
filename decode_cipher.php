<?php

$str = 'm7752902780q5670754954w2654637406q5286271066m8125522416a1926172574x504148223l9676431138g5289793839l5799859691n5135660909g5241613386k4148674163p2895427859i4115643171d6373795065';
// $str = 'a0c2e4g6'; // aaaa 
// $str = 'a0z1c2x3'; // aaaa 
// $str = 'p65e68t2o51d27n48u38n13'; // corneria 
// $str = 'x6136494262r7484725313x185670378k2531105274x7114948063'; // venom

// answer
echo implode('', decodeCipher($str)). PHP_EOL;

/**
 *
 */
function decodeCipher($str) {

    preg_match_all('/\w\d+/', $str, $matches);
    $matches = array_shift($matches);

    $answer = array();
    foreach ($matches as $match) {
        preg_match('/(\w)(\d+)/', $match, $_matches);
        $prefix = $_matches[1];
        $number = $_matches[2];
        $number = $number % 26;
        // $number = bcmod($number, 26);

        if($number % 2 == 0) {
            // 偶数: 戻る
            $answer[] = getCaesarCipherBack($prefix, $number);
        } else {
            // 奇数: 進む
            $answer[] = getCaesarCipherForward($prefix, $number);
        }
    }

    return $answer;
}

/**
 *
 */
function getCaesarCipherForward($str, $num){

    $hints = str_split('abcdefghijklmnopqrstuvwxyz');

    $num = array_shift(array_keys($hints, $str)) + $num;
    if ($num > 25) {
        $num = $num - 26;
    }

    return $hints[$num];
}

/**
 *
 */
function getCaesarCipherBack($str, $num){

    $hints = str_split('abcdefghijklmnopqrstuvwxyz');

    $num = + array_shift(array_keys($hints, $str)) - $num;
    if ($num < 0) {
        $num = $num + 26;
    }

    return $hints[$num];
}
