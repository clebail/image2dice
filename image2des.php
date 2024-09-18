<?php

const SIZE = 20;
const NB_COUL = 14;

if(count($argv) != 2) {
    echo "{$argv[0]} filename.jpeg\n";
    die;
}

$source = $argv[1];
$filename = pathinfo($source, PATHINFO_FILENAME); 
$size = getimagesize($source);
$im = imagecreatefromjpeg($source);
$imDes = imagecreatefrompng("des.png");
$imd = imagecreatetruecolor($size[0], $size[1]);

$desCoords = [
    [0, 0],
    [20, 0],
    [40, 0],
    [60, 0],
    [80, 0],
    [100, 0],
    [120, 0],
    [120, 20],
    [100, 20],
    [80, 20],
    [60, 20],
    [40, 20],
    [20, 20],
    [0, 20],
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

        $sr = ($sr + $sv + $sb) / 3;

        $key = dechex($sr);
        $stats[$key] = $stats[$key] ?? 0;
        $stats[$key]++;

        $color_map[$y][$x] = $key;
    }
}

$seuil = ceil(count($stats)/NB_COUL);
ksort($stats);
$result = [];

$i = 1;
$j = NB_COUL - 1;
foreach($stats as $k => $nb) {
    if(($i % $seuil) == 0) {
        foreach($stats as $k2 => $nb2) {
            if($k2 <= $k && !key_exists($k2, $result)) {
                $result[$k2] = $j;
            }
        }
        $j--;
    }

    $i++;
}

foreach($stats as $k2 => $nb2) {
    if($k2 <= $k && !key_exists($k2, $result)) {
        $result[$k2] = $j;
    }
}

$nbBlanc = $nbNoir = 0;
foreach($color_map as $y => $mapx) {
    foreach($mapx as $x => $key) {
        $id = $result[$key];

        $nbBlanc = $nbBlanc + ($id < NB_COUL / 2 ? 1 : 0);
        $nbNoir = $nbNoir + ($id >= NB_COUL / 2 ? 1 : 0);

        imagecopy($imd, $imDes, $x, $y, $desCoords[$id][0], $desCoords[$id][1], SIZE, SIZE);
    }
}

echo "Blanc: {$nbBlanc}, Noir : {$nbNoir}\n";

imagepng($imd, "{$filename}_result.png");
