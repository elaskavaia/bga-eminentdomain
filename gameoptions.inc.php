<?php

/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * eminentdomain implementation : © Alena Laskavaia <laskava@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * gameoptions.inc.php
 *
 * eminentdomain game options description
 * 
 * In this file, you can define your game options (= game variants).
 *   
 * Note: If your game has no variant, you don't have to modify this file.
 *
 * Note²: All options defined in this file should have a corresponding "game state labels"
 *        with the same ID (see "initGameStateLabels" in eminentdomain.game.php)
 *
 * !! It is not a good idea to modify this file when a game is running !!
 *
 */

$game_options = array(

        100 => array(
                'name' => totranslate('Learning Game (No Research)'),
                'values' => array(

                        1 => array('name' => totranslate('Off')),
                        2 => array('name' => totranslate('On'), 'tmdisplay' => totranslate('Learning Game')),

                ),
                'displaycondition' => array(
                        // Note: do not display this option unless these conditions are met
                        array(
                                'type' => 'otheroption',
                                'id' => 201, // ELO OFF hardcoded framework option
                                'value' => 1, // 1 if OFF

                        )
                ),
                'notdisplayedmessage' => totranslate('Learning variant available only with ELO off')
        ),

        101 => array(
                'name' => totranslate('Extended 3-Player Game'),
                'values' => array(
                        1 => array(
                                'name' => totranslate('Off'),
                                'nobeginner' => false
                        ),
                        2 => array(
                                'name' => totranslate('On'), 'tmdisplay' => totranslate('Extended'),
                                'nobeginner' => true
                        ),

                ),
        ),
        102 => array(
                'name' => totranslate('Scenarios'),
                'values' => array(
                        1 => array(
                                'name' => totranslate('Off'),
                                'nobeginner' => false
                        ),
                        2 => array(
                                'name' => totranslate('On'), 'tmdisplay' => totranslate('Scenarios'),
                                'nobeginner' => true
                        ),

                ),
                'displaycondition' => array(
                        // Note: do not display this option unless these conditions are met
                        array(
                                'type' => 'otheroptionisnot',
                                'id' => 100, // learning variant
                                'value' => 2, // 1 if OFF,2 is ON

                        )
                ),
                'notdisplayedmessage' => totranslate('Scenarios variant is not available if Learning variant is chosen')
        ),
        105 => array(
                'name' => totranslate('Scenario Selection'),
                'values' => array(
                        1 => array(
                                'name' => totranslate('Off'),
                                'nobeginner' => false
                        ),
                        2 => array(
                                'name' => totranslate('On'), 'tmdisplay' => totranslate('Scenario Selection'),
                                'nobeginner' => true
                        ),

                ),
                'displaycondition' => array(
                        array(
                                'type' => 'otheroptionisnot',
                                'id' => 100, // learning variant
                                'value' => 2, // 1 if OFF,2 is ON

                        ),
                        array(
                                'type' => 'otheroption',
                                'id' => 102, // scenario variant
                                'value' => 2, // 1 if OFF,2 is ON

                        )
                ),
                'default' => 1,
                'notdisplayedmessage' => totranslate('This variant is not available without Scenarios')
        ),
        103 => array(
                'name' => totranslate('Extra Turn after Game End Triggered'),
                'values' => array(
                        1 => array(
                                'name' => totranslate('Off'),
                                'nobeginner' => false
                        ),
                        2 => array(
                                'name' => totranslate('On'), 'tmdisplay' => totranslate('Extra Turn'),
                                'nobeginner' => false
                        ),

                ),
                'default' => 2
        ),
        104 => array(
                'name' => totranslate('Escalation Expansion'),
                'values' => array(
                        1 => array(
                                'name' => totranslate('Off'),
                                'nobeginner' => false
                        ),
                        2 => array(
                                'name' => totranslate('On'), 'tmdisplay' => totranslate('Escalation'),
                                'nobeginner' => true
                        ),

                ),
                'displaycondition' => array(
                        array(
                                'type' => 'otheroptionisnot',
                                'id' => 100, // learning variant
                                'value' => 2, // 1 if OFF,2 is ON

                        )
                ),
                'default' => 1,
                'notdisplayedmessage' => totranslate('This variant is not available if Learning variant is chosen')
        ),

);

if (!defined('PREF_AUTO_DISSENT')) { // guard since this included multiple times
        define("PREF_AUTO_DISSENT", 150);
        define("PREFVALUE_AUTO_DISSENT_ON", 1);
        define("PREFVALUE_AUTO_DISSENT_OFF", 0);
        define("PREFVALUE_AUTO_DISSENT_TIMER", 2);
}


$game_preferences = array(
        PREF_AUTO_DISSENT => array(
                'name' => totranslate('Auto Dissent'),
                //'needReload' => true, // after user changes this preference game interface would auto-reload
                'values' => array(
                        PREFVALUE_AUTO_DISSENT_OFF => array('name' => totranslate('No Auto Dissent'), 'cssPref' => 'dissent_manual'),
                        PREFVALUE_AUTO_DISSENT_ON => array('name' => totranslate('Auto Dissent'), 'cssPref' => 'dissent_auto'),
                        PREFVALUE_AUTO_DISSENT_TIMER => array('name' => totranslate('Auto Dissent with Timer'), 'cssPref' => 'dissent_timer'),
                ),
                'default' => PREFVALUE_AUTO_DISSENT_ON,
                'server_sync' => true
        )
);
