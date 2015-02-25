<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class SiteLinks
{
private $temporary = [];
private $cu = "http://localhost/";
private $result = [];
private $c = "";
private $level = 0;
private $maxLevel = 20;

    function __construct($url)
    {
        if (isset($url))
        {
            $this->cu = $url;
        }
    }

    private function readUrl($url)
    {
        echo "url: $url\n";
        $this->ch = curl_init($url);

        // Параметры курла
        curl_setopt($this->ch, CURLOPT_USERAGENT, 'IE20');
        curl_setopt($this->ch, CURLOPT_HEADER, 0);
        // Следующая опция необходима для того, чтобы функция curl_exec() возвращала значение а не выводила содержимое переменной на экран
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, '1');

        // Получаем html
        $text = curl_exec($this->ch);
        /* Check for 404 (file not found). */
        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        if($httpCode == 404) {
            return false;
        }
        // Отключаемся
        curl_close($this->ch);
//        var_dump($text);
        $dom = str_get_html($text);
//        var_dump($dom);
        return $dom;
    }

    private function checkUrl ($str)
    {
        $masks = [
            '.jpg',
            '.png',
            '.gif',
            '.jpeg',
            '.zip',
            '.7z',
            '.rar',
            '.tar',
            '.tgz',
            '#',
            '.html/',
            '/tag/',
    //        '?',
            'wp-admin',
            '/component/',
            ];
        foreach ($masks as $mask)
        {
            if (strpos($str,$mask) !== false)
            {
                return false;
            }
        }
        return true;
    }

    private function getUrls()
    {
    //    echo "get new link: " . $this->c . "\n";
    //    $html = file_get_html($this->c);
        $html = $this->readUrl($this->c);
        if ($html == false || $html == null)
        {
            return false;
        }
        $as = $html->find('a');
        echo "($this->level) link: " . $this->c . " internal links count: " . count($as) . "\n";
    //    if ($this->level === 1)
    //    {
    //        var_dump($html->innertext);
    //    }
        if (count($as))
        {   
            $this->level2 = 0;
            $this->level3 = 0;
            foreach ($as as $a)
            {
                $href = rtrim($a->href," \t\n\r\0\x0B");
                $href = ltrim($href,";' \t\n\r\0\x0B");
                if (substr($href,0,1)=="/")
                {
                    $href = rtrim($this->cu,"/") . $href;
                }
                if (strpos($href,$this->cu) !== false && strpos($href,$this->cu) < 2 && $this->checkUrl($href))
                {
                    if (in_array($href,["/"]) == false && in_array($href,$this->result) == false && in_array($href,$this->temporary) == false && strcmp($href,$this->c) != 0)
                    {
                        array_push($this->temporary[$this->level], $href);
                        $this->level3++;
                    } else
                    {
                        if (in_array($href,["/"]) != false)
                        {
                            echo "a href is only '/': $href\n";
                        }
    //                    if (in_array($href,$this->ru) != false)
    //                    {
    //                        echo "already in \$this->ru(" . count($this->ru) . "): $href\n";
    //                    }
                        if (in_array($href,$this->temporary) != false)
                        {
                            echo "already in \$this->temporary: $href\n";
                        }
                    }

                } else
                {
                    echo "don't pass url checks: $href\n";
                }
                $this->level2++;
            }
            echo "\$this->temporary at level ($this->level): \n";
    //        var_dump($this->temporary);
            while (count($this->temporary[$this->level]))
            {
                $t = array_pop($this->temporary[$this->level]);
                echo "level $this->level:$this->level2:$this->level3, tmp urls: " . count($this->temporary[$this->level]) . "\n";
                if (in_array($t,$this->result) == FALSE)
                {
                    if (++$this->level < $this->maxLevel)
                    {
                        echo "total urls: " . count($this->result) . " try to get next: ". $t . "\n";
                        array_push($this->result,$t);                    
                        $this->c = $t; 
                        echo "level $this->level begin\n";
                        $this->getUrls();  
                        echo "level $this->level end\n";
                    }
                    $this->level--;
                } 
            }

        }
    }

    public function getDonorUrls()
    {
        $startTime = time();

        for ($i=0;$i<$this->maxLevel;$i++)
        {
            array_push($this->temporary,[]);
        }
        $this->c = $this->cu;
        $this->getUrls($this->c);
        reset($this->result);
        date_default_timezone_set("Europe/Moscow");
        $fileName = date("Y-m-d-H-i-s") . "-tmp.list";
        file_put_contents($fileName,implode("\n", $this->result));
        $filters = [
            '/tag/',
            '/category/',
            '/page/',    
        ];

        foreach ($filters as $filter)
        {
            foreach (array_keys($this->result) as $key)
            {
                if (strpos($this->result[$key],$filter) !== false)
                {
                    unset($this->result[$key]);
                }
            }
        }

        //foreach (array_keys($this->ru) as $key)
        //{
        //    if (strpos($this->ru[$key],'.html') === false)
        //    {
        //        unset($this->ru[$key]);
        //    }
        //}
        sort($this->result);

        //var_dump($this->ru);
        foreach (array_keys($this->result) as $key)
        {
            echo "'" . $this->result[$key] . "'\n";
        }

        date_default_timezone_set("Europe/Moscow");
        $fileName = date("Y-m-d-H-i-s") . "-res.list";
        file_put_contents($fileName,implode("\n", $this->result));

        //$this->ru = array_unique($this->ru);

        $timeUsed = time() - $startTime;
        $memDivider = 1024*1024;

        echo "max level: $this->maxLevel\n";
        echo "total urls find: " . count($this->result) . "\n";
        echo "memory used: " . round(memory_get_usage()/($memDivider),0) . "Mb (" . round(memory_get_usage(true)/($memDivider),0) ."Mb)\n";
        echo "time used: " . $timeUsed . " sec.\n";
    }
    
    function checkLink($url)
    {
        $response = get_headers($url, 1)[0];
        $code = substr($response,9,3);
        return [$code,$response];
    }
}