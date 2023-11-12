<?php
// this simple script generates sprite css
// it has no params - fix inline to change what it generates

$from=1;
$to=16;
$maxcol=4;
$scol=$maxcol-1;
$srow=((int)(($to-$from)/$maxcol));
for ($num=$from;$num<=$to;$num++) {
    $index=$num-$from;
    $row=(int)($index/$maxcol);
    $col=$index%$maxcol;
    $xnum=$num;
    $other='';
    if ($num>$to/2) {$xnum=$num-8;$other=".state_0";};
    echo "$other.sprite_tilesE_$xnum { background-position: calc(100% / $scol * $col) calc(100% / $srow * $row);}\n";
}
?>