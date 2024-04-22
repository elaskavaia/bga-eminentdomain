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
 * stats.inc.php
 *
 * eminentdomain game statistics description
 *
 */

/*
    In this file, you are describing game statistics, that will be displayed at the end of the
    game.
    
    !! After modifying this file, you must use "Reload  statistics configuration" in BGA Studio backoffice
    ("Control Panel" / "Manage Game" / "Your Game")
    
    There are 2 types of statistics:
    _ table statistics, that are not associated to a specific player (ie: 1 value for each game).
    _ player statistics, that are associated to each players (ie: 1 value for each player in the game).

    Statistics types can be "int" for integer, "float" for floating point values, and "bool" for boolean
    
    Once you defined your statistics there, you can start using "initStat", "setStat" and "incStat" method
    in your game logic, using statistics names defined below.
    
    !! It is not a good idea to modify this file when a game is running !!

    If your game is already public on BGA, please read the following before any change:
    http://en.doc.boardgamearena.com/Post-release_phase#Changes_that_breaks_the_games_in_progress
    
    Notes:
    * Statistic index is the reference used in setStat/incStat/initStat PHP method
    * Statistic index must contains alphanumerical characters and no space. Example: 'turn_played'
    * Statistics IDs must be >=10
    * Two table statistics can't share the same ID, two player statistics can't share the same ID
    * A table statistic can have the same ID than a player statistics
    * Statistics ID is the reference used by BGA website. If you change the ID, you lost all historical statistic data. Do NOT re-use an ID of a deleted statistic
    * Statistic name is the English description of the statistic as shown to players
    
*/

$stats_type = array(

    // Statistics global to table
    "table" => array(

        "turns_number" => array("id"=> 10,
                    "name" => totranslate("Number of turns"),
                    "type" => "int" ),
    ),
    
    // Statistics existing for each player
    "player" => array(

        "turns_number" => array("id"=> 10,
                    "name" => totranslate("Number of turns"),
                    "type" => "int" ),
        "game_vptrade" => array("id"=> 11,
                    "name" => totranslate("Influence from trading resources"),
                    "type" => "int" ),  
        "game_planets" => array("id"=> 12,
                    "name" => totranslate("Influence from settled planets"),
                    "type" => "int" ),  
        "game_cards" => array("id"=> 13,
                    "name" => totranslate("Influence from technology cards"),
                    "type" => "int" ),
      "game_role_W" => array ("id" => 14,"name" => totranslate("Warfare role played"),"type" => "int" ),
      "game_role_S" => array ("id" => 15,"name" => totranslate("Survey role played"),"type" => "int" ),
      "game_role_R" => array ("id" => 16,"name" => totranslate("Research role played"),"type" => "int" ),
      "game_role_C" => array ("id" => 17,"name" => totranslate("Colonize role played"),"type" => "int" ),
      "game_role_P" => array ("id" => 18,"name" => totranslate("Produce role played"),"type" => "int" ),
      "game_role_T" => array ("id" => 19,"name" => totranslate("Trade role played"),"type" => "int" ),
      "game_follow" => array ("id" => 20,"name" => totranslate("Played Follow"),"type" => "int" ),
      "game_dissent" => array ("id" => 21,"name" => totranslate("Played Dissent"),"type" => "int" ),  
            "game_reparations" => array("id"=> 22,
                    "name" => totranslate("Influence from reparations"),
                    "type" => "int" ),
            "game_vp" => array("id"=> 30,
                    "name" => totranslate("Influence total"),
                    "type" => "int" ),

    )

);
