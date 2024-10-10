<?php

const SIZE = 20;
const NB_COUL = 14;

function usage()
{
    global $argv;
    
    echo "{$argv[0]} <options> filename.jpeg\n";
    echo "-0: without zeros dices\n";
    echo "-w: only white dice will be used\n";
    echo "-b: only black dice will be used\n";
    echo "-v: show debugging info\n";
    echo "-s <width,height> Specifies the width and height of a point respectively\n";
    echo "the -b and -n options cannot be used together.\n";
    die();
}

function showMapItem($nb, $id, $s) {
    $c = "W";
    if($id >= 7) {
        $id = 13 - $id;
        $c="B";
    }
    
    printf("%s%2d %d%s",$s, $nb, $id, $c);
}

$argc = count($argv);
if($argc < 2) {
    usage();
}

$nbCoul = NB_COUL;
$offset = 0;
$sans_zero = $only_blanc = $only_noir = $debug = $size_spe = false;
$width = $height = SIZE;
$sizes = "";
for($i=1;$i<$argc-1;$i++) {
    if($argv[$i] == -0) {
        $sans_zero = true;
    }

    if($argv[$i] == "-w") {
        $only_blanc = true;
    }

    if($argv[$i] == "-b") {
        $only_noir = true;
    }

    if($argv[$i] == "-v") {
        $debug = true;
    }
    
    if($argv[$i] == "-s") {
        $size_spe = true;
        $sizes = $argv[++$i];
    }
}

if($only_blanc && $only_noir) {
    usage();
}

if($only_blanc || $only_noir)
{
    $nbCoul = $sans_zero ? 6 : 7;

    $offset = $only_noir ? 7 : ($sans_zero ? 1 : 0);
} elseif ($sans_zero) {
    $nbCoul = 12;
    $offset = 1;
}

if($size_spe && !preg_match('/([0-9]+),([0-9]+)/', $sizes, $matches)) {
    usage();
} else {
    $width = (int)$matches[1];
    $height = (int)$matches[2];
}

if($debug) echo "nbCool: {$nbCoul}, offset: {$offset}\n";

$source = $argv[$argc - 1];
$filename = pathinfo($source, PATHINFO_FILENAME); 
$size = getimagesize($source);
$im = imagecreatefromjpeg($source);
$imDes = imagecreatefrompng("des.png");
$imd = imagecreatetruecolor($size[0]/$width*SIZE, $size[1]/$height*SIZE);
$imNB = imagecreatetruecolor($size[0], $size[1]);

$desCoords = [
    [0, 0], //0
    [20, 0],//1
    [40, 0],//2
    [60, 0],//3
    [80, 0],//4
    [100, 0],//5
    [120, 0],//6
    [120, 20],//7
    [100, 20],//8
    [80, 20],//9
    [60, 20],//10
    [40, 20],//11
    [20, 20],//12
    [0, 20],//13
];

$stats = [];
$max = 0;
$max_key = "";

$color_map = [];

for($y=0;$y<$size[1];$y+=$height) {
    for($x=0;$x<$size[0];$x+=$width) {
        $sr = $sv = $sb = 0;
        for($j=0;$j<$height;$j++) {
            for($i=0;$i<$width;$i++) {
                $color = imagecolorat($im, $x+$i, $y+$j);

                $sr += ($color >> 16) & 0xFF;
                $sv += ($color >> 8) & 0xFF;
                $sb += ($color >> 0) & 0xFF;
            }
        }

        $sr /= ($width*$height);
        $sv /= ($width*$height);
        $sb /= ($width*$height);

        $sr = (int)(($sr + $sv + $sb) / 3);

        $key = dechex($sr);
        $stats[$key] = $stats[$key] ?? 0;
        $stats[$key]++;

        $color_map[$y][$x] = $key;

        $color = imagecolorallocate($imNB, $sr, $sr, $sr);
        imagefilledrectangle($imNB, $x, $y, $x+$width, $y+$height, $color);
    }
}

if($debug) echo "Nb Composantes : ".count($stats)."\n";
$seuil = ceil(count($stats)/$nbCoul);
ksort($stats, SORT_STRING);
$result = [];

$i = 1;
$j = $nbCoul - 1 + $offset;
if($debug) echo "j: $j\n";
foreach($stats as $k => $nb) {
    if(($i % $seuil) == 0) {
        if($debug) echo "{$i} => couleur {$j}\n";
        $nbAffect = 0;
        foreach($stats as $k2 => $nb2) {
            if(!key_exists($k2, $result) && $nbAffect < $seuil) {
                $result[$k2] = $j;
                $nbAffect++;
            }
        }
        $j--;
    }

    $i++;
}

if($debug) echo "{$i} => couleur {$j}\n";
foreach($stats as $k2 => $nb2) {
    if(!key_exists($k2, $result)) {
        $result[$k2] = $j;
    }
}

if($debug) echo "j: $j\n";


$oldId = null;
$nbId = 0;
$nbBlanc = $nbNoir = 0;
foreach($color_map as $y => $mapx) {
    $s = "";
    foreach($mapx as $x => $key) {
        $id = $result[$key];

        $blanc = $id < 7;
        $noir = $id >= $nbCoul / 2;

        if($id != $oldId) {
            if($oldId !== null) {
                showMapItem($nbId, $oldId, $s);
                $s = ", ";
            }
            $nbId = 0;
        }
        $nbId++;

        $nbBlanc = $nbBlanc + ($blanc ? 1 : 0);
        $nbNoir = $nbNoir + ($noir ? 1 : 0);

        imagecopy($imd, $imDes, $x/$width*SIZE, $y/$height*SIZE, $desCoords[$id][0], $desCoords[$id][1], SIZE, SIZE);

        $oldId = $id;
    }
    showMapItem($nbId, $oldId, ", ");
    echo "\n";
    $s = "";
    $oldId = null;
    $nbId = 0;
}

echo "\nDices count:\n";
echo "White: {$nbBlanc}, Black : {$nbNoir}\n";

imagepng($imd, "{$filename}_result.png");
imagepng($imNB, "{$filename}_NB.png");
