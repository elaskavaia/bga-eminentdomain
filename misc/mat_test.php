<?php
define("APP_GAMEMODULE_PATH", "misc/"); // include path to mocks, this defined "Table" and other classes
require_once ('eminentdomain.game.php');

// include real game class, it has to be on php inlude path
// this can test if material file has not errors, run
// php7 misc/mat_test.php
class EminentDomainXmakinaTest1 extends EminentDomainXmakina {
    function __construct() {
        parent::__construct();
        include '../material.inc.php';
    }

    // override/stub methods here that access db and stuff
    function getGameStateValue($var) {
        if ($var == 'round')
            return 3;
        return 0;
    }
    function testSanity(){
        // tech cards which are permanent have flip side
        foreach ($this->token_types as $id => $info){
            $rowtype = $info ['type'];
            if (startsWith($rowtype, 'tech')) {
                $perm = $info['side']!=0;
                if ($perm) {
                    $flip=$info['flip'];
                    $flipname=$this->token_types[$flip]['name'];
                    print "$id (".$info['name'].") flip $flip ($flipname)\n";
                }
            }
        }
        $data = $this->mtTriggering("enter");
        var_dump($data);
    }
}
$x = new EminentDomainXmakinaTest1();
$x->testSanity();



