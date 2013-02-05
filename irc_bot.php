<?php
/**
 * irc bot.
 *
 */
@require_once('Net/SmartIRC.php');
@require_once('XML/RSS.php');

$bot = new IRC_Bot();
$irc = new Net_SmartIRC();

define('JOIN_CHANNEL' , '#channel_name');

define('VAR_DIR' , dirname(__FILE__) . '/var');

$url = 'http://trac.hostname.jp/';
$bot->setTracURL($url);

define('TRAC_LOG' , 'trac.log');
$url = 'http://trac.hostname.jp/timeline?max=30&daysback=1&format=rss';
$bot->setTracRssURL($url);

define('TWITTER_LOG' , 'twitter.log');
$url = 'http://search.twitter.com/search.atom?q=twitter';
$bot->setTwitterURL($url);

$url = 'http://www.fujitv.co.jp/meza/uranai/';
$bot->setUranaiURL($url);
$xml = 'http://www.fujitv.co.jp/meza/uranai/uranai.xml';
$bot->setUranaiXML($xml);

$irc->connect('irc.hostname.jp', 6667);
$irc->login('irc_bot', 'irc_bot');
$irc->join(array(JOIN_CHANNEL));

// $irc->registerTimehandler(60*1000, $bot, 'sendTime');
$irc->registerTimehandler(10*1000, $bot, 'sendTracRss');
// $irc->registerTimehandler(300*1000, $bot, 'sendTwitterRss');
$irc->registerTimehandler(60*1000, $bot, 'sendUranai');
// $irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '#([0-9]+)', $bot, 'sendTracTicket');
$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '/^(hello)$/i', $bot, 'sendHello');
$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '/^(quit)$/i', $bot, 'sendQuit');
$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, 'https?:\/\/([-_.!~*\'()a-zA-Z0-9;\/?:\@&=+\$,%#]+)$', $bot, 'sendUrl');

$irc->listen();

/**
 * class area
 */
class IRC_Bot
{
    function sendTime(&$irc)
    {
        $irc->message(SMARTIRC_TYPE_NOTICE, JOIN_CHANNEL, date('Y-m-d H:i:s'));
    }

    function sendHello(&$irc, &$data)
    {
        $irc->message(SMARTIRC_TYPE_CHANNEL, $data->channel, $data->nick.': hello.');
    }

    function sendQuit(&$irc, &$data)
    {
        $irc->quit('bye.');
    }

    function sendUrl(&$irc, &$data)
    {
        preg_match('/https?:\/\/([-_.!~*\'()a-zA-Z0-9;\/?:\@&=+\$,%#]+)/', $data->message, $urls);
        $urldata = file_get_contents($urls[0], NULL, NULL, 0, 4096);
        $urldata = str_replace(array("\r\n", "\r", "\n", "\t"), '', $urldata);
        $urldata = htmlspecialchars_decode($urldata);
        if (preg_match('/<title(.*?)>(.*?)<\/title>/i', $urldata, $matches)) {
            $title = $matches[2];
            if (mb_detect_encoding($title) != 'UTF-8') {
                $title = mb_convert_encoding($title, 'UTF-8', 'SJIS, Shift_JIS, EUC-JP, ASCII, JIS');
            }
            $irc->message(SMARTIRC_TYPE_NOTICE, $data->channel, $title);
        }
    }

    function sendTracTicket(&$irc, &$data)
    {
        $id = $data->message;
        preg_match_all('/#([0-9]+)/i', $id, $ids);
        foreach ($ids[1] as $no) {
            $url = $this->getTracURL();
            $link = $url . 'ticket/' . $no;
            $urldata = file_get_contents($link, NULL, NULL, 0, 4096);
            $urldata = str_replace(array("\r\n", "\r", "\n", "\t"), '', $urldata);
            $urldata = htmlspecialchars_decode($urldata);
            if (preg_match('/<title(.*?)>(.*?)<\/title>/i', $urldata, $matches)) {
                $title = $matches[2];
                if (mb_detect_encoding($title) != 'UTF-8') {
                    $title = mb_convert_encoding($title, 'UTF-8', 'SJIS, Shift_JIS, EUC-JP, ASCII, JIS');
                }
                $title = str_replace('#' . $no, chr(3) . '4' . '#' . $no .  chr(3), $title);
                $irc->message(SMARTIRC_TYPE_NOTICE, JOIN_CHANNEL, $title . ' - ' . $link);
            }
        }
    }

    function sendTracRss(&$irc)
    {
        $lists = array();
        $file  = VAR_DIR . '/' . TRAC_LOG;
        $log   = trim(file_get_contents($file));
        $url   = $this->getTracRssURL();

        if ($log == '') {
            return;
        }

        list($link, $time) = explode('<>', $log);
        $link = trim($link);
        $time = intval($time);

        $rss = new XML_RSS($url);
        $rss->parse();
        foreach ($rss->getItems() as $item) {
            if ($item['link'] == $link || strtotime($item['pubdate']) < $time) {
                break;
            }
            if (preg_match('/^(\[[a-zA-Z0-9-_.\@]+\]) (.+)$/', $item['title'], $matches)) {
                $name  = $matches[1];
                $text  = strip_tags($matches[2]);
                $title = chr(3) . '7' . $name . chr(3) . ' ' .$text;
            } else {
                $title = $item['title'];
            }
            $lists[] = array($title, $item['link'], strtotime($item['pubdate']));
        }
        unset($rss);

        if (isset($lists[0][1])) {
            $fp = fopen($file , "w");
            flock($fp, LOCK_EX);
            fwrite($fp, $lists[0][1] . '<>' . $lists[0][2]);
            flock($fp, LOCK_UN);
            fclose($fp);
        }
        while (($list = array_pop($lists)) != null) {
            list($title, $link, $time) = $list;
            $irc->message(SMARTIRC_TYPE_NOTICE, JOIN_CHANNEL, $title . ' - ' . $link);
        }
    }

    function sendTwitterRss(&$irc)
    {
        $lists = array();
        $file  = VAR_DIR . '/' . TWITTER_LOG;
        $log   = trim(file_get_contents($file));
        $url   = $this->getTwitterURL();

        if (($rss = simplexml_load_file($url)) === FALSE) {
            return;
        }

        $channel = $rss->channel;
        foreach ($channel as $item) {
            $title = strip_tags($item->title);
            $name  = explode(' ', $item->author->name, 2);
            $id    = $name[0];
            $msg   = chr(3) . '10' . '[' . $id . ']' . chr(3) . ' ' . $title;
            $link  = $item->link;
            foreach ($link as $value) {
                $href = (string)$value->attributes()->href;
                $rel  = (string)$value->attributes()->rel;
                if ($rel == 'alternate') {
                    if ($href == $log) {
                        break 2;
                    }
                    $lists[] = array($msg, $href);
                }
            }
        }
        unset($rss);

        if (isset($lists[0][1])) {
            $fp = fopen($file , "w");
            flock($fp, LOCK_EX);
            fwrite($fp, $lists[0][1]);
            flock($fp, LOCK_UN);
            fclose($fp);
        }
        while (($list = array_pop($lists)) != null) {
            list($title, $link) = $list;
            $irc->message(SMARTIRC_TYPE_NOTICE, JOIN_CHANNEL, $title . ' - ' . $link);
        }
    }

    function sendUranai(&$irc)
    {

        if (date('Hi') != '1000') {
            return;
        }

        $title = '今日の占いCountDown';

        $astrology = array();
        $astrology[1]  = 'おひつじ座';
        $astrology[2]  = 'おうし座';
        $astrology[3]  = 'ふたご座';
        $astrology[4]  = 'かに座';
        $astrology[5]  = 'しし座';
        $astrology[6]  = 'おとめ座';
        $astrology[7]  = 'てんびん座';
        $astrology[8]  = 'さそり座';
        $astrology[9]  = 'いて座';
        $astrology[10] = 'やぎ座';
        $astrology[11] = 'みずがめ座';
        $astrology[12] = 'うお座';

        $url = $this->getUranaiURL();
        $xml = $this->getUranaiXML();

        $xml = file_get_contents($xml);
        if (mb_detect_encoding($xml) != 'UTF-8') {
            $xml = mb_convert_encoding($xml, 'UTF-8', 'SJIS, Shift_JIS, EUC-JP, ASCII, JIS');
        }

        $xml = simplexml_load_string($xml);

        if ($xml->date != date('n月j日')) {
            return;
        }

        $irc->message(SMARTIRC_TYPE_NOTICE, JOIN_CHANNEL, chr(3) . '0,4' . '【' . $title . '】' . chr(3));
        sleep(1);
        $irc->message(SMARTIRC_TYPE_NOTICE, JOIN_CHANNEL, chr(3) . '15' . 'source: ' . $url . chr(3));
        sleep(1);
        $irc->message(SMARTIRC_TYPE_NOTICE, JOIN_CHANNEL, chr(2) . chr(3) . '2' . '[' . $xml->date . ']' . chr(3) . chr(2));
        sleep(1);

        foreach ($xml as $object) {
            foreach ($object as $element) {
                sleep(1);

                if ($element->rank == '5位') {
                    $irc->message(SMARTIRC_TYPE_NOTICE, JOIN_CHANNEL, chr(3) . '5' . '--------------- ▲ ココまで上位 ▲ ---------------' . chr(3));
                    sleep(1);
                }
                if ($element->rank == '9位') {
                    $irc->message(SMARTIRC_TYPE_NOTICE, JOIN_CHANNEL, chr(3) . '5' . '--------------- ▼ ココから下位 ▼ ---------------' . chr(3));
                    sleep(1);
                }

                $irc->message(SMARTIRC_TYPE_NOTICE, JOIN_CHANNEL, chr(2) . chr(3) . '4' . '≪' . $element->rank . '≫ ' . chr(3) . $astrology[(int)$element->id] . chr(2));
                $irc->message(SMARTIRC_TYPE_NOTICE, JOIN_CHANNEL, str_replace(',', '', $element->text));
                if ($element->advice != '') {
                    $msg = ($element->rank == '1位') ? 'アドバイス:' : 'おまじない:';
                    $irc->message(SMARTIRC_TYPE_NOTICE, JOIN_CHANNEL, chr(3) . '6' . $msg . chr(3) . ' ' . $element->advice);
                }
                $irc->message(SMARTIRC_TYPE_NOTICE, JOIN_CHANNEL, chr(3) . '3' . 'ラッキーポイント:' . chr(3) . ' ' . $element->point);
            }
        }
    }

    public function getTracURL()
    {
        return $this->trac_url;
    }
    public function setTracURL($url)
    {
        $this->trac_url = $url;
    }

    public function getTracRssURL()
    {
        return $this->trac_rss_url;
    }
    public function setTracRssURL($url)
    {
        $this->trac_rss_url = $url;
    }

    public function getTwitterURL()
    {
        return $this->twitter_url;
    }
    public function setTwitterURL($url)
    {
        $this->twitter_url = $url;
    }

    public function getUranaiURL()
    {
        return $this->uranai_url;
    }
    public function setUranaiURL($url)
    {
        $this->uranai_url = $url;
    }
    public function getUranaiXML()
    {
        return $this->uranai_xml;
    }
    public function setUranaiXML($xml)
    {
        $this->uranai_xml = $xml;
    }
}