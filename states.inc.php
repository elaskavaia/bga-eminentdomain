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
 * states.inc.php
 *
 * eminentdomain game states description
 *
 */

/*
   Game state machine is a tool used to facilitate game developpement by doing common stuff that can be set up
   in a very easy way from this configuration file.

   Please check the BGA Studio presentation about game state to understand this, and associated documentation.

   Summary:

   States types:
   _ activeplayer: in this type of state, we expect some action from the active player.
   _ multipleactiveplayer: in this type of state, we expect some action from multiple players (the active players)
   _ game: this is an intermediary state where we don't expect any actions from players. Your game logic must decide what is the next game state.
   _ manager: special type for initial and final state

   Arguments of game states:
   _ name: the name of the GameState, in order you can recognize it on your own code.
   _ description: the description of the current game state is always displayed in the action status bar on
                  the top of the game. Most of the time this is useless for game state with "game" type.
   _ descriptionmyturn: the description of the current game state when it's your turn.
   _ type: defines the type of game states (activeplayer / multipleactiveplayer / game / manager)
   _ action: name of the method to call when this game state become the current game state. Usually, the
             action method is prefixed by "st" (ex: "stMyGameStateName").
   _ possibleactions: array that specify possible player actions on this step. It allows you to use "checkAction"
                      method on both client side (Javacript: this.checkAction) and server side (PHP: self::checkAction).
   _ transitions: the transitions are the possible paths to go from a game state to another. You must name
                  transitions in order to use transition names in "nextState" PHP method, and use IDs to
                  specify the next game state for each transition.
   _ args: name of the method to call to retrieve arguments for this gamestate. Arguments are sent to the
           client side to be used on "onEnteringState" or to set arguments in the gamestate description.
   _ updateGameProgression: when specified, the game progression is updated (=> call to your getGameProgression
                            method).
*/

//    !! It is not a good idea to modify this file when a game is running !!

if (!defined('STATE_END_GAME')) { // guard since this included multiple times
        define("STATE_PLAYER_TURN_ACTION", 2);
        define("STATE_GAME_TURN_NEXT_PLAYER_FOLLOW", 3);
        define("STATE_GAME_TURN_NEXT_PLAYER", 4);
        define("STATE_GAME_TURN_UPKEEP", 5);
        define("STATE_PLAYER_TURN_ROLE", 6);
        define("STATE_PLAYER_TURN_DISCARD", 7);
        define("STATE_PLAYER_TURN_PRE_DISCARD", 12);
        define("STATE_PLAYER_TURN_SURVEY", 8);
        define("STATE_PLAYER_TURN_ROLE_EXTRA", 26); // // extra data collection for leader role
        define("STATE_PLAYER_GAME_END", 9);
        define("STATE_PLAYER_TURN_FOLLOW", 10);
        define("STATE_PLAYER_TURN_SURVEY_FOLLOW", 11);
        define("STATE_PLAYER_TURN_FOLLOW_EXTRA", 21); // extra data collection for follow role
        define("STATE_PLAYER_TURN_ACTION_EXTRA", 22); // extra data collection for action 
        define("STATE_SCENARIO_SELECTION", 23);
        define("STATE_SCENARIO_SELECTION_NEXT_PLAYER", 24);
        define("STATE_GAME_PLAYER_SETUP", 25);
        define("STATE_END_GAME", 99);
}


$machinestates = array(

        // The initial state. Please do not modify.
        1 => array(
                "name" => "gameSetup",
                "description" => "",
                "type" => "manager",
                "action" => "stGameSetup",
                "transitions" => array("" => STATE_SCENARIO_SELECTION)
        ),
        STATE_SCENARIO_SELECTION => array(
                "name" => "scenarioSelection",
                "action" => "st_selectScenario",
                "type" => "activeplayer",
                "description" => clienttranslate('${actplayer} must choose a scenario'),
                "descriptionmyturn" => clienttranslate('${you} must choose to a scenario'),
                "possibleactions" => array("selectScenario"),
                "transitions" => array(
                        "" => STATE_SCENARIO_SELECTION_NEXT_PLAYER
                )
        ),
        STATE_SCENARIO_SELECTION_NEXT_PLAYER => array(
                "name" => "nextPlayer",
                "description" => "",
                "type" => "game",
                "action" => "st_scenarioNextPlayer",
                "transitions" => array(
                        "nextPlayer" => STATE_SCENARIO_SELECTION,
                        "last" => STATE_GAME_PLAYER_SETUP
                )
        ),
        STATE_GAME_PLAYER_SETUP => array(
                "name" => "gamePlayerSetup",
                "description" => '',
                "type" => "game",
                "action" => "st_gamePlayerSetup",
                "updateGameProgression" => false,
                "transitions" => array("" => STATE_PLAYER_TURN_ACTION)
        ),

        // Note: ID=2 => your first state

        STATE_PLAYER_TURN_ACTION => array(
                "name" => "playerTurnAction",
                "description" => clienttranslate('${actplayer} may play an action'),
                "descriptionmyturn" => clienttranslate('${you} may play an action'),
                "type" => "activeplayer",
                "args" => "arg_playerTurnAction",
                "possibleactions" => array("playAction", "skipAction",  "playActivatePermanent", "playExtra"),
                "transitions" => array(
                        "next" => STATE_PLAYER_TURN_ROLE,
                        "loopback" => STATE_PLAYER_TURN_ACTION,
                        "last" => STATE_GAME_TURN_UPKEEP
                )
        ),
        STATE_PLAYER_TURN_ROLE => array(
                "name" => "playerTurnRole",
                "description" => clienttranslate('${actplayer} must play a role'),
                "descriptionmyturn" => clienttranslate('${you} must play a role'),
                "type" => "activeplayer",
                "args" => "arg_playerTurnRole",
                "possibleactions" => array("playRole", "playActivatePermanent"),
                "transitions" => array(
                        "discard" => STATE_PLAYER_TURN_PRE_DISCARD,
                        "next" => STATE_GAME_TURN_NEXT_PLAYER_FOLLOW,
                        "loopback" => STATE_PLAYER_TURN_ROLE,
                        "extra" => STATE_PLAYER_TURN_ROLE_EXTRA
                )
        ),
        STATE_PLAYER_TURN_PRE_DISCARD => array(
                "name" => "playerTurnPreDiscard",
                "description" => clienttranslate('${actplayer} may discard cards'),
                "descriptionmyturn" => clienttranslate('${you} may discard some cards from your hand, if you do your turn will end'),
                "type" => "activeplayer",
                "args" => "arg_playerTurnRole",
                "possibleactions" => array("playDiscard", "playActivatePermanent", "playWait"),
                "transitions" => array(
                        "next" => STATE_GAME_TURN_NEXT_PLAYER_FOLLOW,
                        "loopback" => STATE_PLAYER_TURN_PRE_DISCARD,
                        "discard" => STATE_PLAYER_TURN_PRE_DISCARD,
                        "extra" => STATE_PLAYER_TURN_ROLE_EXTRA
                )
        ),
        STATE_PLAYER_TURN_DISCARD => array(
                "name" => "playerTurnDiscard",
                "description" => clienttranslate('${actplayer} may discard cards'),
                "descriptionmyturn" => clienttranslate('${you} may discard some cards from your hand'),
                "type" => "activeplayer",
                "args" => "arg_playerTurnRole",
                "possibleactions" => array("playDiscard", "playActivatePermanent"),
                "transitions" => array(
                        "next" => STATE_GAME_TURN_UPKEEP,
                        "loopback" => STATE_PLAYER_TURN_DISCARD,
                        "extra" => STATE_PLAYER_TURN_ROLE_EXTRA
                )
        ),
        STATE_PLAYER_TURN_SURVEY => array( // not used anymore, keep for backward compat
                "name" => "playerTurnSurvey",
                "description" => clienttranslate('${actplayer} must choose a planet to keep'),
                "descriptionmyturn" => clienttranslate('${you} must choose a planet to keep'),
                "type" => "activeplayer",
                "args" => "arg_playerTurnRole",
                "possibleactions" => array("playPick"),
                "transitions" => array(
                        "next" => STATE_GAME_TURN_NEXT_PLAYER_FOLLOW,
                        "role" => STATE_PLAYER_TURN_ROLE,
                        "extra" => STATE_PLAYER_TURN_ROLE_EXTRA
                )
        ),

        STATE_PLAYER_TURN_ROLE_EXTRA => array(
                "name" => "playerTurnRoleExtra",
                "description" => clienttranslate('${actplayer} ${state_prompt}'),
                "descriptionmyturn" => clienttranslate('${you} ${state_prompt}'),
                "type" => "activeplayer",
                "args" => "arg_playerTurnRoleExtra",
                "possibleactions" => array("playPick", "playActivatePermanent", "playExtra", "playDiscard"),
                "transitions" => array(
                        "next" => STATE_GAME_TURN_NEXT_PLAYER_FOLLOW,
                        "survey" => STATE_PLAYER_TURN_SURVEY,
                        "loopback" => STATE_PLAYER_TURN_ROLE_EXTRA,
                        "discard" => STATE_PLAYER_TURN_PRE_DISCARD,
                        "extra" => STATE_PLAYER_TURN_ROLE_EXTRA
                )
        ),

        STATE_GAME_TURN_UPKEEP => array(
                "name" => "gameTurnUpkeep",
                "description" => clienttranslate('Cleanup...'),
                "type" => "game",
                "action" => "st_gameTurnUpkeep",
                "updateGameProgression" => true,
                "transitions" => array(
                        "discard" => STATE_PLAYER_TURN_DISCARD,
                        "action" => STATE_PLAYER_TURN_ACTION,
                        "role" => STATE_PLAYER_TURN_ROLE,
                        "next" =>  STATE_GAME_TURN_NEXT_PLAYER,
                        "extra" => STATE_PLAYER_TURN_ROLE_EXTRA
                ),
        ),

        STATE_PLAYER_TURN_SURVEY_FOLLOW => array( // not used anymore, keep for backward compat
                "name" => "playerTurnSurveyFollow",
                "description" => clienttranslate('${actplayer} must choose a planet to keep'),
                "descriptionmyturn" => clienttranslate('${you} must choose a planet to keep'),
                "type" => "activeplayer",
                "args" => "arg_playerTurnRole",
                "possibleactions" => array("playPick"),
                "transitions" => array(
                        "next" => STATE_GAME_TURN_NEXT_PLAYER_FOLLOW,
                        "extra" => STATE_PLAYER_TURN_ROLE_EXTRA
                )
        ),
        STATE_GAME_TURN_NEXT_PLAYER => array(
                "name" => "gameTurnNextPlayer",
                "description" => clienttranslate('Replenish...'),
                "type" => "game",
                "action" => "st_gameTurnNextPlayer",
                "updateGameProgression" => true,
                "transitions" => array(
                        "next" => STATE_PLAYER_TURN_ACTION,
                        "loopback" =>  STATE_GAME_TURN_NEXT_PLAYER,
                        "last" => STATE_END_GAME
                ), // to test change to STATE_PLAYER_GAME_END
        ),
        STATE_GAME_TURN_NEXT_PLAYER_FOLLOW => array(
                "name" => "gameTurnNextPlayerFollow",
                "description" => '',
                "type" => "game",
                "action" => "st_gameTurnNextPlayerFollow",
                "updateGameProgression" => true,
                "transitions" => array(
                        "next" => STATE_PLAYER_TURN_FOLLOW,
                        "loopback" =>  STATE_GAME_TURN_NEXT_PLAYER_FOLLOW,
                        "last" => STATE_GAME_TURN_UPKEEP,

                ),
        ),
        STATE_PLAYER_TURN_FOLLOW => array(
                "name" => "playerTurnFollow",
                "type" => "multipleactiveplayer",
                "description" => clienttranslate('Other players must choose to Follow ${otherplayer}\'s "${role_name}" role or Dissent'),
                "descriptionmyturn" => clienttranslate('${you} must choose to Follow ${otherplayer}\'s "${role_name}" role or Dissent'),
                "possibleactions" => array("playFollow", "playDissent", "playWait"),
                "transitions" => array(
                        "next" => STATE_GAME_TURN_NEXT_PLAYER_FOLLOW,
                        "loopback" => STATE_PLAYER_TURN_FOLLOW,
                        "extra" => STATE_PLAYER_TURN_ROLE_EXTRA
                ),
                "args" => "arg_playerTurnFollow"
        ),

        STATE_PLAYER_GAME_END => array(
                "name" => "playerTurnGameEnd",
                "description" => clienttranslate('${actplayer} must end the game (development state, remove before production)'),
                "descriptionmyturn" => clienttranslate('${you} must end the game (development state, remove before production)'),
                "type" => "activeplayer",
                "args" => "arg_playerTurnRole",
                "possibleactions" => array("skipAction"),
                "transitions" => array(
                        "next" => STATE_END_GAME,
                        "last" => STATE_END_GAME
                )
        ),

        // Final state.
        // Please do not modify.
        STATE_END_GAME => array(
                "name" => "gameEnd",
                "description" => clienttranslate("End of game"),
                "type" => "manager",
                "action" => "stGameEnd",
                "args" => "argGameEnd"
        )

);
