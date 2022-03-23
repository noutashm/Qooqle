<?php
include "config.php";
include "classes/DomDocumentParser.php";

$alreadyCrawled = array();
$crawling = array();
$alreadyFoundImages = array();

function insertLink($url, $title, $description, $keyword) {
    global $con;

    $query = $con->prepare("INSERT INTO sites(url, title, description, keyword) VALUES (:url, :title, :description, :keyword)");
    $query->bindParam(":url", $url);
    $query->bindParam(":title", $title);
    $query->bindParam(":description", $description);
    $query->bindParam(":keyword", $keyword);

    return $query->execute();
}

function insertImage($url, $src, $alt, $title) {
    global $con;

    $query = $con->prepare("INSERT INTO images(siteUrl, imageUrl, alt, title) VALUES (:siteUrl, :imageUrl, :alt, :title)");
    $query->bindParam(":siteUrl", $url);
    $query->bindParam(":imageUrl", $src);
    $query->bindParam(":alt", $alt);
    $query->bindParam(":title", $title);

    $query->execute();
}

function linkExists($url) {
    global $con;

    $query = $con->prepare("SELECT * FROM sites WHERE url = :url");
    $query->bindParam(":url", $url);
    $query->execute();

    return $query->rowCount() != 0;
}

function createLink($src, $url) {

    $scheme = parse_url($url)["scheme"]; //http
    $host = parse_url($url)["host"]; //www.bbc.com

    if (substr($src, 0, 2) == "//") {
        $src = $scheme . ":" . $src;
    } elseif (substr($src, 0, 1) == "/") {
        $src = $scheme . "://" . $host . $src;
    } elseif (substr($src, 0, 2) == "./") {
        $src = $scheme . "://" . $host . dirname(parse_url($url)["path"]) . substr($src, 1);
    } elseif (substr($src, 0, 3) == "../") {
        $src = $scheme . "://" . $host . "/" . $src;
    } elseif (substr($src, 0, 5) != "https" && substr($src, 0, 4) != "http") {
        $src = $scheme . "://" . $host . "/" . $src;
    }

    return $src;
}

function getDetails($url) {
    global $alreadyFoundImages;
    $parser = new DomDocumentParser($url);
    $titleArray = $parser->getTitleTag();

    if (sizeof($titleArray) == 0 || $titleArray->item(0) == NULL) {
        return;
    }

    $title = $titleArray->item(0)->nodeValue;
    $title = str_replace("\n", "" , $title);

    if (empty($title)) {
        return;
    }

    $description = "";
    $keyword = "";

    $metasArray = $parser->getMetaTag();

    foreach ($metasArray as $meta) {
        if ($meta->getAttribute("name") == "description") {
            $description = $meta->getAttribute("content");
        }

        if ($meta->getAttribute("name") == "keyword") {
            $keyword = $meta->getAttribute("content");
        }
    }

    $description = str_replace("\n", "" , $description);
    $keyword = str_replace("\n", "" , $keyword);

    if (linkExists($url)) {
        echo "$url already exists<br>";
    } elseif (insertLink($url, $title, $description, $keyword)) {
        echo "SUCCESS: $url<br>";
    } else {
        echo "ERROR: Failed to insert $url<br>";
    }

    $imageArray = $parser->getImages();
    foreach ($imageArray as $image) {
        $src = $image->getAttribute("src");
        $alt = $image->getAttribute("alt");
        $title = $image->getAttribute("title");

        if (!$title && !$alt) {
            continue;
        }

        $src = createLink($src, $url);

        if (!in_array($src, $alreadyFoundImages)) {
            $alreadyFoundImages[] = $src;

            insertImage($url, $src, $alt, $title);
        }

    }


}

function followLinks ($url) {

    global $alreadyCrawled;
    global $crawling;
    $parser = new DomDocumentParser($url);
    $linkList = $parser->getLinks();

    foreach ($linkList as $link) {
        $href = $link->getAttribute("href");

        if (strpos($href, "#") !== false) {
            continue;
        }elseif (substr($href, 0, 11) == "javascript:") {
            continue;
        }

        $href = createLink($href, $url);

        if (!in_array($href, $alreadyCrawled)) {
            $alreadyCrawled[] = $href;
            $crawling[] = $href;

            getDetails($href);
        }
    }
    array_shift($crawling);

    foreach ($crawling as $site) {
        followLinks($site);
    }
}

$startUrl = "https://www.pet.co.nz/";
followLinks($startUrl);
?>