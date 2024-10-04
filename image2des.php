<?php

const SIZE = 20;
const NB_COUL = 14;

function usage()
{
    echo "{$argv[0]} <options> filename.jpeg\n";
    echo "-0: sans les zéros\n";
    echo "-b: que les dés blancs seront utilisés\n";
    echo "-n: que les dés noirs seront utilisés\n";
    echo "-v: affiche les infos de debogage\n";
    echo "les options -b et -n ne peuvent pas être utiluisés conjointement.\n";
    die();
}

function showMapItem($nb, $id, $s) {
    $c = "B";
    if($id >= 7) {
        $id = 13 - $id;
        $c="N";
    }
    
    printf("%s%2d %d%s",$s, $nb, $id, $c);
}

$argc = count($argv);
if($argc < 2) {
    usage();
}

$nbCoul = NB_COUL;
$offset = 0;
$sans_zero = $only_blanc = $only_noir = $debug = false;
for($i=1;$i<$argc-1;$i++) {
    if($argv[$i] == -0) {
        $sans_zero = true;
    }

    if($argv[$i] == "-b") {
        $only_blanc = true;
    }

    if($argv[$i] == "-n") {
        $only_noir = true;
    }

    if($argv[$i] == "-v") {
        $debug = true;
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

if($debug) echo "nbCool: {$nbCoul}, offset: {$offset}\n";

$source = $argv[$argc - 1];
$filename = pathinfo($source, PATHINFO_FILENAME); 
$size = getimagesize($source);
$im = imagecreatefromjpeg($source);
$imDes = imagecreatefrompng("des.png");
$imd = imagecreatetruecolor($size[0], $size[1]);
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

for($y=0;$y<$size[1];$y+=SIZE) {
    for($x=0;$x<$size[0];$x+=SIZE) {
        $sr = $sv = $sb = 0;
        for($j=0;$j<SIZE;$j++) {
            for($i=0;$i<SIZE;$i++) {
                $color = imagecolorat($im, $x+$i, $y+$j);

                $sr += ($color >> 16) & 0xFF;
                $sv += ($color >> 8) & 0xFF;
                $sb += ($color >> 0) & 0xFF;
            }
        }

        $sr /= (SIZE*SIZE);
        $sv /= (SIZE*SIZE);
        $sb /= (SIZE*SIZE);

        $sr = (int)(($sr + $sv + $sb) / 3);

        $key = dechex($sr);
        $stats[$key] = $stats[$key] ?? 0;
        $stats[$key]++;

        $color_map[$y][$x] = $key;

        $color = imagecolorallocate($imNB, $sr, $sr, $sr);
        imagefilledrectangle($imNB, $x, $y, $x+SIZE, $y+SIZE, $color);
    }
}

if($debug) echo "Nb Composantes : ".count($stats)."\n";
$seuil = ceil(count($stats)/$nbCoul);
ksort($stats);
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

        imagecopy($imd, $imDes, $x, $y, $desCoords[$id][0], $desCoords[$id][1], SIZE, SIZE);

        $oldId = $id;
    }
    showMapItem($nbId, $oldId, ", ");
    echo "\n";
    $s = "";
    $oldId = null;
    $nbId = 0;
}

echo "Blanc: {$nbBlanc}, Noir : {$nbNoir}\n";

imagepng($imd, "{$filename}_result.png");
imagepng($imNB, "{$filename}_NB.png");
