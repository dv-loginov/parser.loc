<?php

ini_set("memory_limit", "512M");

//DB Credentials
define('DB_HOST', '127.0.0.1:3306');
define('DB_USER','mysql');
define('DB_PASSWORD','mysql');
define('DB_NAME','testparser');
//row per block
define('PER_BLOCK','10');


//Lib for parsing
require_once "lib/simple_html_dom.php";
require_once "db.class.php";
//URL for parsing
$url='http://ananaska.com/vse-novosti/';

//Conection to DB
$db=new DB(DB_HOST, DB_USER, DB_PASSWORD,DB_NAME);

// Get param from CLI
if(isset($argv[1])){
    $action=$argv[1];
} else{
    echo 'No action';
    exit;
}

//Jast get links to articles
if ($action=='catalog'){
    getArticlesLinksFromCatalog($url);
}elseif ($action=='articles'){
    while(true){
        //get random hash
        $tmp_uniq=md5(uniqid().time());
        $db->query("update articles set tmp_uniq='{$tmp_uniq}' where tmp_uniq is null limit ".PER_BLOCK);
        //get marked articles
        $articles=$db->query("select url from articles where tmp_uniq='{$tmp_uniq}'");
        if(!$articles){
            echo "All done.";
            exit;
        }
        //Process each of marked articles
        foreach ($articles as $article) {
            getArticleData($article['url']);
        }
    }
}


function getArticleData($url){
    global $db;
    $article=file_get_html($url);
    $h1=$db->escape($article->find('h1',0)->innertext);
    $content=$db->escape($article->find('article',0)->innertext);
    $data=compact('h1','content');
    $sql="
        update articles
            set h1='{$h1}',
                content='{$content}',
                data_parsed =NOW()
            where url='{$url}'
        ";
    $db->query($sql);
    return $data;
}

/**
 * @param $url
 */
function getArticlesLinksFromCatalog($url)
{
    global $db;
    echo PHP_EOL.$url.PHP_EOL.PHP_EOL;
    //Get page
    $html=file_get_html($url);
    //Get each article link
    foreach ($html->find('a.read-more-link') as $link_to_article) {

        // Each article link - Add to DB
        $article_url=$db->escape($link_to_article->href);
        $sql="
        insert ignore into articles
          set url='{$article_url}'
        ";
        $db->query($sql);

        //echo $link_to_article->href . PHP_EOL;

        //print_r(getArticleData($link_to_article->href));
    }
    //Recursion to next page
    if ($next_link=$html->find('a.next',0)){
        getArticlesLinksFromCatalog($next_link->href);
    }
}

