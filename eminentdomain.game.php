<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * EminentDomain implementation : © Alena Laskavaia <laskava@gmail.com>
 * 
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 * 
 * eminentdomain.game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 *
 */

use EminentDomain\Setup;

require_once(APP_GAMEMODULE_PATH . 'module/table/table.game.php');
require_once('modules/EuroGame.php');
require_once('modules/php/EminentDomain/index.php');
define("CARD_STATE_PERMANENT_TAPPED", 0);
define("CARD_STATE_PERMANENT", 1);

define("CARD_STATE_PARTIAL_ACTION", 11);
define("CARD_STATE_TURN_LASTING", 10);
define("CARD_STATE_USED_CARD", 12);
define("CARD_STATE_PLANET_USED_AS_REQ", 13);
define("RESOURCE_STATE_COLONIZE_BOOST", 14);
define("RESOURCE_STATE_BLOCKER", 1);
define("RESOURCE_STATE_PRODUCE", 2);
define("PLANET_SETTLED", 1);
define("PLANET_UNSETTLED", 0);
define("CARD_STATE_FACEUP", 1);
define("CARD_STATE_FACEDOWN", 0);
define("RESOURCE_STATE_MARKER", 10);
define("CARD_RULE_TRIGGERED", 0);
define("CARD_RULE_ACTIVATABLE", 100);
define("CARD_RULE_ACTION", 200);
define("CARD_RULE_ACTION_PART2", 210);
define("CARD_RULE_ROLE", 300);
define("CARD_RULE_ROLE_LEADER", 310);
define("CARD_RULE_SPECIAL", 400);

class eminentdomain extends EuroGame
{

    function __construct()
    {
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();
        self::initGameStateLabels(array(
            // game global vars starts at 10
            "action_played" => 11, // set to 1 if activate player played an action
            "leader" => 12, // set to the leader id
            "followers" => 13, // mask of which of players executed follow/descent
            "followers_waiting" => 14, // mask of which of players are waiting
            "active_follower" => 15, // follower who must follow/descent
            "role" => 20, // current role number (index of role_icons array in material), -1 if not played
            "end_of_game" => 21, // end of game has been triggered
            "progression" => 22, // progression
            "logistics_at_end" => 23, // logistics extra turn played
            "extra_turn_at_end" => 24, //
            "boost_rem" => 26, // how much of boost remains after role (used specific cards such as Scientific Method)
            "actions_allowed" => 27, //
            "role_phases_allowed" => 28, //
            "role_phases_played" => 29, //
            "discard_played" => 30, //
            "follow_played" => 31, //
            "scenarios" => 32, //
            // variants
            "learning_variant" => 100, //
            "extended_variant" => 101, //
            "scenarios_variant" => 102, //
            "extra_turn_variant" => 103, //
            "escalation_variant" => 104, // 
            "scenario_selection" => 105, // 
        ));
        $this->tokens->autoreshuffle_custom = array('supply_planets' => 'discard_planets');
        $this->tokens->autoreshuffle = true;
        $gameinfos = self::getGameinfos();
        $default_colors = $gameinfos['player_colors'];
        foreach ($default_colors as $color) {
            $this->tokens->autoreshuffle_custom["deck_$color"] = "discard_$color";
        }
    }

    protected function getGameName()
    {
        // Used for translations and stuff. Please do not modify.
        return "eminentdomain";
    }

    function isEscalationVariant()
    {
        return $this->getGameStateValue('escalation_variant') == 2;
    }

    function isScenarioVariant()
    {
        return $this->getGameStateValue('scenarios_variant') == 2;
    }

    function isScenarioSelection()
    {
        return $this->getGameStateValue('scenario_selection') == 2;
    }

    /*
     * setupNewGame:
     *
     * This method is called only once, when a new game is launched.
     * In this method, you must setup the game according to the game rules, so that
     * the game is ready to be played.
     */
    protected function setupNewGame($players, $options = array())
    {
        /**
         * ********** Start the game initialization ****
         */
        try {
            $this->initPlayers($players);
            $this->activeNextPlayer(); // just in case so its not 0
            $this->initStats();
            // Setup the initial game situation here
            $this->initTables();
            $this->setGameStateValue('end_of_game', 0);
            $this->setGameStateValue('logistics_at_end', 0);
            $this->setGameStateValue('extra_turn_at_end', 0);
        } catch (Exception $e) {
            // logging does not actually work in game init :(
            $this->error("Fatal error while creating game");
            $this->dump('err', $e);
        }
    }

    function debug_initTables()
    {
        $this->tokens->deleteAll();
        $this->initTables();
        $newGameDatas = $this->getAllTableDatas();
        self::notifyPlayer($this->getActivePlayerId(), 'resetInterfaceWithAllDatas', '', $newGameDatas);
        $this->debugConsole("ok");
    }

    function debug_getTokens($type, $location = null, $state = null, $order_by = null)
    {
        return $this->tokens->getTokensOfTypeInLocation($type, $location, $state, $order_by);
    }

    function debug_a()
    {
        // random code that I need to execute
        for ($i = 13; $i <= 16; $i++) {
            $this->dbSetTokenLocation("card_role_colonize_$i", "cols");
        }
    }

    function initTables()
    {
        $num = $this->getPlayersNumber();
        $learning_variant = $this->getGameStateValue('learning_variant') == 2;
        $extended_variant = $this->getGameStateValue('extended_variant') == 2 && $num == 3;
        $escalation_variant = $this->isEscalationVariant();
        // setup from expansion rules
        $setupnums = Setup::BuildDecks($num, $extended_variant);
        $maxvp = Setup::GetMaxVP($num);
        $this->tokens->createTokensPack("vp_w_{INDEX}", "stock_vp", $maxvp);
        $this->tokens->createTokensPack("card_role_survey_{INDEX}", "supply_survey", $setupnums[0]);
        $this->tokens->createTokensPack("card_role_warfare_{INDEX}", "supply_warfare", $setupnums[1]);
        $this->tokens->createTokensPack("card_role_colonize_{INDEX}", "supply_colonize", $setupnums[2]);
        $this->tokens->createTokensPack("card_role_produce_{INDEX}", "supply_produce", $setupnums[3]);
        if (!$learning_variant) {
            $this->tokens->createTokensPack("card_role_research_{INDEX}", "supply_research", $setupnums[4]);
        }
        $this->tokens->createTokensPack("card_planet_0_{INDEX}", "starting_worlds", 6);
        $this->tokens->shuffle("starting_worlds");
        if ($escalation_variant) {
            $this->tokens->createTokensPack('card_fleet_b_{INDEX}', "dev_null", $num);
            $this->tokens->createTokensPack('card_fleet_i_{INDEX}', "supply_fleet", $num);
        }
        // TECH
        if (!$learning_variant) {
            foreach ($this->token_types as $key => $info) {
                if (startsWith($key, "card_tech")) {
                    $planettype = $info['p'][0];
                    $location = "supply_tech_$planettype";
                    $create = true;
                    if (isset($info['exp'])) {
                        $create = false;
                        if ($this->isEscalationVariant() && $info['exp'] == 'E')
                            $create = true;
                    }
                    if ($create)
                        $this->tokens->createToken($key, $location);
                }
            }
        }
        // FIGHERS AND RESOURCES
        $this->tokens->createTokensPack('fighter_F_{INDEX}', "stock_fighter", 20 + 10 + 5, 0);
        if ($escalation_variant) {
            $this->tokens->createTokensPack('fighter_D_{INDEX}', "stock_fighter", 3, 0);
            $this->tokens->createTokensPack('fighter_B_{INDEX}', "stock_fighter", 1, 0);
        }
        $this->tokens->createTokensPack('resource_w_{INDEX}', "stock_resource", 6, 0);
        $this->tokens->createTokensPack('resource_f_{INDEX}', "stock_resource", 6, 0);
        $this->tokens->createTokensPack('resource_i_{INDEX}', "stock_resource", 6, 0);
        $this->tokens->createTokensPack('resource_s_{INDEX}', "stock_resource", 6, 0);

        // Populate all game components
        $this->tokens->createTokensPack("card_planet_1_{INDEX}", "supply_planets", 9);
        $this->tokens->createTokensPack("card_planet_2_{INDEX}", "supply_planets", 9);
        $this->tokens->createTokensPack("card_planet_3_{INDEX}", "supply_planets", 9);
        if ($escalation_variant) {
            $this->tokens->createTokensPack("card_planet_E_{INDEX}", "supply_planets", 15, 1);
        }
        $this->tokens->shuffle("supply_planets");
        if ($learning_variant) {
            $this->tokens->moveToken("card_planet_1_1", "dev_null");
            $this->tokens->moveToken("card_planet_1_2", "dev_null");
            $this->tokens->moveToken("card_planet_1_3", "dev_null");
        }

        // SCENARIOS
        $this->scenarioProcess();
        $scenario_tokens = [];
        foreach ($this->token_types as $id => $info) {
            $rowtype = $info['type'];
            if (startsWith($rowtype, 'scenario')) {
                $requiredExpansion = $info['expR'] ?? 'B';
                if (
                    $requiredExpansion == 'B' || // basic 
                    ($requiredExpansion == 'E' && $escalation_variant)
                ) {
                    $scenario_tokens[] = ['key' => $id];
                }
            }
        }

        $this->tokens->createTokens($scenario_tokens, "scenarios", 0);
    }

    private function setupPlayers()
    {
        $players = $this->loadPlayersBasicInfos();
        $learning_variant = $this->getGameStateValue('learning_variant') == 2;
        $scenarios_variant = $this->isScenarioVariant();
        $escalation_variant = $this->isEscalationVariant();

        $standard_setup = [2, 1, 2, 2, 2, 1, '', ''];
        $poli = 1;
        $scenarios_setup = [];
        foreach ($players as $player_id => $player_info) {
            $color = $player_info['player_color'];
            $no = $player_info['player_no'];
            if ($escalation_variant) {
                $this->tokens->moveToken("card_fleet_b_$no", "tableau_$color", 1);
            }
            if ($scenarios_variant) {
                $sc = $this->tokens->getTokenOfTypeInLocation('scenario', "tableau_$color");
                $key = $sc['key'];
                $scenarios_setup[$player_id] = $this->token_types[$key]['setup'];
            } else {
                $scenarios_setup[$player_id] = $standard_setup;
            }
            $setup = $scenarios_setup[$player_id];
            $this->tokens->pickTokensForLocation($setup[0], "supply_survey", "deck_${color}", 0);
            $this->tokens->pickTokensForLocation($setup[1], "supply_warfare", "deck_${color}", 0);
            $this->tokens->pickTokensForLocation($setup[2], "supply_colonize", "deck_${color}", 0);
            $this->tokens->pickTokensForLocation($setup[3], "supply_produce", "deck_${color}", 0);
            if (!$learning_variant)
                $this->tokens->pickTokensForLocation($setup[4], "supply_research", "deck_${color}", 0);
            // politics
            $pol = $setup[5];
            for ($i = 0; $i < $pol; $i++) {
                $this->tokens->createToken("card_role_politics_${poli}", "deck_${color}", 0);
                $poli++;
            }
            // staring tech
            for ($i = 7; $i < count($setup); $i++) {
                $tech = $setup[$i];
                if ($tech) {
                    if ($tech == 'fighter_B') {
                        $info = $this->tokens->getTokenOfTypeInLocation('fighter_B', 'stock_fighter');
                        $this->tokens->moveToken($info['key'], "tableau_$color");
                        continue;
                    }
                    if ($tech == 'card_fleet_i') {
                        $this->tokens->moveToken("card_fleet_b_${no}", "dev_null", 0);
                        $this->tokens->moveToken("card_fleet_i_${no}", "tableau_${color}", 1);
                        continue;
                    }
                    if ($this->isPermanentTech($tech)) {
                        $this->tokens->moveToken($tech, "tableau_$color", 1);
                        $flip = $this->getRulesFor($tech, 'flip');
                        if ($flip)
                            $this->tokens->moveToken($flip, "dev_null", 0);
                        else
                            $this->error("No flip card for $tech " . ($this->getTokenName($tech)) . ".");
                    } else {
                        $this->tokens->moveToken($tech, "deck_${color}", 0);
                    }
                }
            }
            $this->tokens->shuffle("deck_${color}");
            $this->tokens->pickTokensForLocation(5, "deck_${color}", "hand_${color}");
            // starting planet
            $desi_planet = $setup[6];
            if ($desi_planet) { // if special planet 
                $info = $this->tokens->getTokenInfo($desi_planet);
                if ($info)
                    $this->tokens->moveToken($desi_planet, "tableau_${color}", 0);
                else
                    $this->tokens->createToken($desi_planet, "tableau_${color}", 0);
            }
        }
        // we have to do that after the special planet are assigned
        foreach ($players as $player_id => $player_info) {
            $color = $player_info['player_color'];
            $setup = $scenarios_setup[$player_id];
            $desi_planet = $setup[6];
            if (!$desi_planet) { // if not special planet 
                $this->tokens->pickTokensForLocation(1, "starting_worlds", "tableau_${color}", 0);
            }
        }
        // move to dev_null to remove from the game
        $this->tokens->moveAllTokensInLocation("starting_worlds", "dev_null");
        $this->tokens->moveAllTokensInLocation("scenarios", "dev_null");
    }

    /*
     * getAllDatas:
     *
     * Gather all informations about current game situation (visible by the current player).
     *
     * The method is called each time the game interface is displayed to a player, ie:
     * _ when the game starts
     * _ when a player refreshes the game page (F5)
     */
    protected function getAllDatas()
    {
        $result = parent::getAllDatas();
        $result['materials'] = $this->materials;
        $players = $this->loadPlayersBasicInfos();
        $learning_variant = $this->getGameStateValue('learning_variant') == 2;
        $extended_variant = $this->getGameStateValue('extended_variant') == 2 && count($players) == 3;
        $extra_turn = $this->getGameStateValue('extra_turn_variant') == 2;
        $result['variants'] = array(
            'learning_variant_on' => $learning_variant,
            'extended_variant_on' => $extended_variant, 'extra_turn_variant_on' => $extra_turn
        );
        $top_planet = $this->tokens->getTokenOnTop('supply_planets');
        if ($top_planet) {
            $top_planet['state'] = 0;
            $result['tokens'][$top_planet['key']] = $top_planet;
        }
        $current = $this->getCurrentPlayerId();
        $icons = $this->materials['role_icons'];
        foreach ($players as $player_id => $player_info) {
            $color = $player_info['player_color'];
            $ii = $this->arg_iconsInfo($color, $icons);
            foreach ($icons as $icon) {
                $value = $ii["perm_boost_num"][$icon];
                $this->setCounter($result['counters'], "iconperm_${icon}_${color}_counter", $value);
            }
            $score = count($this->tokens->getTokensOfTypeInLocation('vp', "tableau_$color"));
            $this->setCounter($result['counters'], "vp_${color}_counter", $score);
            if ($current == $player_id)
                $result['server_prefs'] = $this->dbGetAllServerPrefs($player_id);
        }
        $triggered = $this->getGameStateValue('end_of_game');
        $result['end_of_game'] = $triggered == 1;


        return $result;
    }

    /*
     * getGameProgression:
     *
     * Compute and return the current game progression.
     * The number returned must be an integer beween 0 (=the game just started) and
     * 100 (= the game is finished or almost finished).
     *
     * This method is called each time we are in a game state with the "updateGameProgression" property set to true
     * (see states.inc.php)
     */
    function getGameProgression()
    {
        $this->isEndOfGameTriggered(); // that computes it
        $prog = $this->getGameStateValue('progression');
        return $prog;
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Utility functions
    ////////////    
    // Material utilities
    function getRulesFor($token_id, $field = 'a')
    {
        $key = $token_id;
        while ($key) {
            //$this->warn("searching for $key|");
            if (array_key_exists($key, $this->token_types)) {
                if (array_key_exists($field, $this->token_types[$key])) {
                    return $this->token_types[$key][$field];
                }
                return '';
            }
            $key1 = getPartsPrefix($key, -1);
            if ($key1 == $key)
                break;
            $key = $key1;
        }
        $this->systemAssertTrue("bad card $token_id for rule $field", false);
        return '';
    }

    /**
     * Get id of the token by its 'hid' or name
     *
     * @param string $hid
     *            - human readable id or name of the token
     * @return string - id of the token found or null if not found
     */
    function mtGet($hid)
    {
        if (!$hid)
            return $hid;
        if (isset($this->token_types[$hid]))
            return $hid;
        foreach ($this->token_types as $id => $info) {
            if (isset($info['hid']) && $info['hid'] == $hid) {
                return $id;
            }
        }
        foreach ($this->token_types as $id => $info) {
            if (isset($info['name']) && strcasecmp($info['name'], $hid) == 0) {
                return $id;
            }
        }
        $this->warn("error: cannot find $hid key\n");
        return null;
    }

    /**
     * Find a token of type $typePrefix which has field $field set to value $value, except a specific token $exceptId
     * Return array of results of $all is set to true and first id otherwise
     *
     * @param string $typePrefix
     *            - type prefix (can be full id too)
     * @param string $field
     *            - field
     * @param string $value
     *            - exact match with ===
     * @param string|null $exceptId
     *            - except one specific element (usefull to exclude itself)
     * @param boolean $all
     *            - when true return fall results in array
     * @return string|string[] - return array of ids or single id
     */
    function mtFind($typePrefix, $field, $value, $exceptId = null, $all = true)
    {
        $res = [];
        foreach ($this->token_types as $id => $info) {
            if ($exceptId && $exceptId == $id)
                continue;
            $rowtype = $info['type'];
            if ($typePrefix && startsWith($rowtype, $typePrefix)) {
                if (isset($info[$field])) {
                    if (is_array($info[$field])) {
                        if (in_array($value, $info[$field], true)) {
                            if (!$all)
                                return $id;
                            $res[] = $id;
                        }
                    } else {
                        if ($value === $info[$field]) {
                            if (!$all)
                                return $id;
                            $res[] = $id;
                        }
                    }
                }
            }
        }
        return $res;
    }

    function mtCollectWithField($field, $callback)
    {
        $res = [];
        foreach ($this->token_types as $id => $info) {
            if (isset($info[$field])) {
                if (is_array($info[$field])) {
                    foreach ($info[$field] as $trig) {
                        if ($callback($trig, $id)) {
                            $res[] = $id;
                        }
                    }
                } else {
                    $trig = $info[$field];
                    if ($callback($trig, $id)) {
                        $res[] = $id;
                    }
                }
            }
        }
        return $res;
    }

    function mtTriggering($event)
    {
        $field = 'trig';
        $callback = function ($v) use ($event) {
            return $this->mtMatchEvent($v, $event);
        };
        return $this->mtCollectWithField($field, $callback);
    }

    /**
     * Triggered action syntax:
     *
     * <trigger_rule> ::= <trigger> ':' <outcome>
     * <trigger> ::= <event_type> '-' <role>
     * 
     * <event> ::= <trigger>
     *
     * @param string $declared
     *            - the rule, for example [fl]-R:X - means follow or lead R (research) it triggers X (whatever X means)
     * @param string $event-
     *            what actually happen, i.e. f-T - following trade
     * @param array $splits
     * @return boolean
     */
    function mtMatchEvent($trigger_rule, $event, &$splits = [])
    {
        if (!$event)
            $event = ".-.";
        $woot = explode(":", $trigger_rule, 2);
        $trig = $woot[0];
        $out = '';
        if (count($woot) > 1)
            $out = $woot[1];
        $event_split = explode("-", $event, 2);
        $trig_split = explode("-", $trig, 2);
        $trig_type = array_value_get($trig_split, 0, '.');
        $trig_role = array_value_get($trig_split, 1, '.');
        $event_type = array_value_get($event_split, 0, '.');
        $event_role = array_value_get($event_split, 1, 'x');
        $splits['rule_type'] = $trig_type;
        $splits['rule_role'] = $trig_role;
        $splits['event_type'] = $event_type;
        $splits['event_role'] = $event_role;
        $splits['out'] = $out;
        $type_match = ($event_type === '.' || $trig_type === $event_type || preg_match("/^${trig_type}$/", $event_type) === 1);
        $role_match = ($event_role === '.' || $trig_role === $event_role || preg_match("/^${trig_role}$/", $event_role) === 1);
        $splits['match_type'] = $type_match;
        $splits['match_role'] = $role_match;
        if ($type_match && $role_match)
            return true;

        //$this->debugConsole("res",$splits);
        return false;
    }

    function mtTriggerInfo($card, $event)
    {
        $triginfo = [];
        $info = $this->getRulesFor($card, 'trig');
        foreach ($info as $i => $trig) {
            $split = [];
            if ($this->mtMatchEvent($trig, $event, $split)) {
                $triginfo = ['card' => $card, 'num' => $i, 'event' => $event, 'rules' => $split['out']];
                break;
            }
        }
        return $triginfo;
    }

    function mtGetFightCost($planet, $field = 'w')
    {
        if ($field == 'wi') // attack with primary weapon
            $fight = "B";
        else
            $fight = trim($this->getRulesFor($planet, $field));
        if (strlen($fight) == 2) {
            $mcost = $fight[0];
            $ftype = $fight[1];
        } else {
            $mcost = $fight;
            $ftype = 'F'; // ship type
        }
        if ($mcost === 'D' || $mcost === 'B') {
            $ftype = $mcost;
            $mcost = 1;
        } else {
            $mcost = (int) $mcost; // military cost
        }
        $ship = $this->materials['icons'][$ftype]; // ship name
        return ['cost' => $mcost, 'ship_type' => $ftype, 'ship_name' => $ship, 'rule' => str_repeat($ftype, $mcost)];
    }

    function mtIconCount($card, $icon)
    {
        $cur_icons = $this->getRulesFor($card, 'i');
        $count = substr_count($cur_icons, $icon);
        return $count;
    }


    /**
     * massage material inc scenarios, it looks up token id by name/hid and add conflicting scenarious array
     * its should have been there but I cannot really define functions
     * in materil file.
     */
    function scenarioProcess()
    {
        foreach ($this->token_types as $id => &$info) {
            $rowtype = $info['type'];
            if (startsWith($rowtype, 'scenario')) {
                for ($i = 6; $i <= 9; $i++) { // Starting world + up to 2 starting techs (this is why we can't have Double Time scenario)
                    if (!isset($info['setup'][$i]))
                        continue;
                    $v = $info['setup'][$i];
                    if ($v == 'random') {
                        $info['setup'][$i] = ''; // a '' world is random
                    } else {
                        $v = $this->mtGet($v);
                        $info['setup'][$i] = $v; // get the ID from the world/tech name
                    }
                }
            }
        }
        foreach ($this->token_types as $id => &$info) {
            $rowtype = $info['type'];
            if (startsWith($rowtype, 'scenario')) {
                $info['conflict'] = [];
                for ($i = 6; $i <= 9; $i++) { // Starting world + up to 2 starting techs
                    if (!isset($info['setup'][$i]))
                        continue;
                    $v = $info['setup'][$i];
                    if (!$v || $v == 'random')
                        continue;
                    $foundarr = $this->mtFind('scenario', 'setup', $v, $id, true);
                    $info['conflict'] = array_unique(array_merge($info['conflict'], $foundarr));
                    if (isset($this->token_types[$v]['flip'])) {
                        $flip = $this->token_types[$v]['flip'];
                        $foundarr = $this->mtFind('scenario', 'setup', $flip, $id, true);
                        //echo "FLIP $id: $i $v $flip ;\n";
                        $info['conflict'] = array_unique(array_merge($info['conflict'], $foundarr));
                    }
                }
            }
        }
    }

    // End of Material utilities

    function iconReplacement(&$jokers, $icons, $event, $rules)
    {
        $split = [];
        if ($this->mtMatchEvent($rules, $event, $split)) {
            $rolematch = $split['rule_role'];
            foreach ($icons as $icon) {
                if (preg_match("/$rolematch/", $icon)) {
                    $jokers[$icon] .= $split['out'];
                }
            }
        }
        //$this->debugConsole("irep",[$jokers, $icons, $event, $rules]);
    }
    function isReadyToBeSettled($planet, $perm_boost)
    {
        $colonies = $this->getRulesFor($planet, 'c');
        $this->systemAssertTrue("bad planet $planet", $colonies);
        $tokens = $this->tokens->getTokensInLocation($planet);
        $icons = $this->getIconCount('C', $tokens, '*');
        if ($icons + $perm_boost >= $colonies)
            return true;
        return false;
    }

    function isPermanentTech($tech)
    {
        $side = $this->getRulesFor($tech, 'side');
        if ($side == '1' || $side == '2')
            return true;
        // old way
        $action = $this->getRulesFor($tech, 'a');
        if ($action === '*')
            return true;
        return false;
    }

    function isActionable($card)
    {
        $action = $this->getRulesFor($card, 'a');
        if (!$action || $action === '*')
            return false;
        return true;
    }

    function isPlanet($card)
    {
        return startsWith($card, 'card_planet');
    }

    function isEndOfGameTriggered()
    {
        $triggered = $this->getGameStateValue('end_of_game');
        if ($triggered)
            return true;
        $players = $this->loadPlayersBasicInfos();
        $num = $this->getPlayersNumber();
        $total = 0;
        foreach ($players as $player_id => $player_info) {
            $color = $this->getPlayerColor($player_id);
            $score = count($this->tokens->getTokensOfTypeInLocation('vp', "tableau_$color"));
            $total += $score;
        }
        $max = 24;
        if ($num == 5)
            $max = 32;
        if ($total >= $max) {
            $this->setGameStateValue('end_of_game', 1);
            $this->setGameStateValue('progression', 99);
            return true;
        }
        $prox1 = $total * 100 / ($max);
        $learning_variant = $this->getGameStateValue('learning_variant') == 2;
        $supplies = ['supply_survey', 'supply_warfare', 'supply_colonize', 'supply_produce'];
        if (!$learning_variant) {
            $supplies[] = 'supply_research';
        }
        $extended_variant = ($this->getGameStateValue('extended_variant') == 2 && $num == 3) || $num > 3;
        $empty_decks = 1;
        if ($extended_variant)
            $empty_decks = 2;
        $ctot = count($supplies) * 16;
        $rtot = 0;
        foreach ($supplies as $location) {
            $count = $this->tokens->countTokensInLocation($location);
            if ($count <= 0)
                $empty_decks--;
            if ($empty_decks <= 0) {
                $this->setGameStateValue('end_of_game', 1);
                $this->setGameStateValue('progression', 99);
                return true;
            }
            $rtot += $count;
        }
        $prox2 = ($ctot - $rtot) * 100 / $ctot;
        if ($prox2 > $prox1)
            $prox1 = $prox2;
        $this->setGameStateValue('progression', (int) $prox1);
        return false;
    }

    function checkTooManyCardsInHand($color, $bThrow = true)
    {
        $count = $this->tokens->countTokensInLocation("hand_$color");
        $iconsinfo = $this->arg_iconsInfo($color, ['h']);
        $max = $iconsinfo['hand_size'];
        if ($count > $max) {
            if ($bThrow)
                $this->userAssertTrue(self::_("You must discard down to $max cards"));
            else
                return $max;
        }
        return 0;
    }

    function isEndOfGame($next_player_id)
    {
        if ($this->getFirstPlayer() == $next_player_id) {
            if ($this->isEndOfGameTriggered()) {
                return true;
            }
        }
        return false;
    }

    function finalScoring()
    {
        $players = $this->loadPlayersBasicInfos();
        foreach ($players as $player_id => $player_info) {
            $res_count = 0;
            $color = $player_info['player_color'];
            $no = $player_info['player_no'];
            $tokens = $this->tokens->getTokensOfTypeInLocation("card_planet", "tableau_${color}");
            foreach ($tokens as $planet => $info) {
                $state = $info['state'];
                if ($state == 1) {
                    // colonized
                    $restokens = $this->tokens->getTokensOfTypeInLocation("resource", $planet);
                    $res_count += count($restokens);
                } else {
                    $ptokens = $this->tokens->getTokensInLocation($planet);
                    foreach ($ptokens as $key => $info) {
                        $this->dbSetTokenLocation($key, "tableau_$color", 0, '', ['nod' => true]);
                    }
                }
            }
            $this->tokens->moveAllTokensInLocation("hand_${color}", "deck_${color}");
            $this->tokens->moveAllTokensInLocation("discard_${color}", "deck_${color}");
            $tokens = $this->tokens->getTokensOfTypeInLocation("card_tech_2", "%_${color}");
            foreach ($tokens as $token_id => $info) {
                $this->dbSetTokenLocation($token_id, "tableau_${color}", 0, '');
            }
            $tokens = $this->tokens->getTokensOfTypeInLocation("card_tech_3", "%_${color}");
            foreach ($tokens as $token_id => $info) {
                $this->dbSetTokenLocation($token_id, "tableau_${color}", 0, '');
            }
            $this->notifyAnimate();
            // reveal the rest
            $tokens = $this->tokens->getTokensOfTypeInLocation("card", "deck_${color}");
            foreach ($tokens as $token_id => $info) {
                $this->dbSetTokenLocation($token_id, "tableau_${color}", 0, '', ['nod' => true]);
            }
            $tokens = $this->tokens->getTokensOfTypeInLocation('fighter', "tableau_$color");
            $fighter = 0;
            foreach ($tokens as $token_id => $info) {
                if (getPart($token_id, 1) === 'B') { // Battlecruiser 2 points
                    $this->dbIncScoreValueAndNotify($player_id, 2, clienttranslate('${player_name} scored 2 influence for Battlecruiser'));
                }
                if (getPart($token_id, 1) === 'F') {
                    $fighter++;
                }
            }
            $tokensF = $this->tokens->getTokensOfTypeInLocation('fighter_F', null, $player_id);

            $fighter += count($tokensF);
            $this->dbSetAuxScore($player_id, $fighter + $res_count);

            $this->setStat($this->dbGetScore($player_id), 'game_vp', $player_id);
        }
    }

    /**
     * This only should be use for dinamic markers
     */
    function dbMoveOrCreate($id, $location, $state)
    {
        $info = $this->tokens->getTokenInfo($id);
        if ($info) {
            $this->tokens->moveToken($id, $location, $state);
        } else {
            $this->tokens->createToken($id, $location, $state);
        }
    }


    function dbGetFreeResourceInfo($resource)
    {
        switch ($resource) {
            case 'f':
            case 'w':
            case 'i':
            case 's':
                break;
            default:
                $this->systemAssertTrue("Invalid resource type $resource");
                break;
        }
        $restype = "resource_$resource";
        $location = "stock_resource";
        return $this->dbGetAutoIncToken($restype, $location, 6, 1, true);
    }

    function dbSetMarkerLocation($key, $location, $state)
    {
        $info = $this->dbGetAutoIncToken($key, 'pending_stock', 1, 1, false);
        $this->tokens->moveToken($info['key'], $location, $state);
        //$this->debugConsole('marker created ${token_name} ${place_name}', ['token_name'=>$info['key'], 'place_name'=>$location]);
    }

    function dbGetOwnVpInfo($color)
    {
        $resa = $this->tokens->getTokensOfTypeInLocation('vp', "tableau_${color}");
        return $resa;
    }

    function dbGetOwnFighterInfo($color, $includeHand = true)
    {
        $rec = ['F' => [], 'D' => [], 'B' => []];
        $resa = $this->tokens->getTokensOfTypeInLocation('fighter', "tableau_${color}");
        foreach ($resa as $key => $info) {
            $type = getPart($key, 1);
            $rec[$type][] = $key;
        }
        $resa = $this->tokens->getTokensOfTypeInLocation('fighter', null, $this->getPlayerIdByColor($color));
        foreach ($resa as $key => $info) {
            $type = getPart($key, 1);
            $rec[$type][] = $key;
        }
        if ($includeHand) {
            $resa = $this->tokens->getTokensOfTypeInLocation('card', "hand_${color}");
            foreach ($resa as $key => $info) {
                $type = $this->getRulesFor($key, 'asf'); // card as ships
                if (!$type)
                    continue;
                $rec[$type][] = $key;
            }
        }
        return $rec;
    }

    function getAllActivePermanents($color)
    {
        $loc = "tableau_${color}";
        $tech = $this->tokens->getTokensOfTypeInLocation("card_tech", $loc, 1);
        $planets = $this->tokens->getTokensOfTypeInLocation("card_planet", $loc, 1);
        $res = array_merge($tech, $planets);
        //$this->warn(toJson($res));
        return $res;
    }

    function dbGetFreeFighterInfo($resource = 'F', $requested = 1, $array = false)
    {
        $max = $requested;
        switch ($resource) {
            case 'F':
                $max += 10;
                break;
            case 'D':
                $max += 5;
                break;
            case 'B':
                $max += 2;
                break;
            default:
                $this->systemAssertTrue("Invalid figher type $resource");
                break;
        }
        $restype = "fighter_$resource";
        $location = "stock_fighter";
        return $this->dbGetAutoIncToken($restype, $location, $max, $requested, true, $array);
    }

    function dbGetAutoIncToken($restype, $location, $max = -1, $requested = 1, $syncDb = true, $array = false)
    {
        if ($max <= 0 || $max < $requested + 1)
            $max = $requested + 1;
        $resa = $this->tokens->getTokensOfTypeInLocation($restype, $location);
        $count = count($resa);
        $num = $max - $count;
        if ($num > 0) {
            // re-stock
            $keys = [];
            for ($i = 0; $i < $num; $i++) {
                $suf = $this->tokens->createTokenAutoInc($restype, $location);
                $keys[] = $suf;
            }
            if ($syncDb)
                $this->dbSyncInfo($keys);
            $resa = $this->tokens->getTokensOfTypeInLocation($restype, $location);
        }
        if ($requested == 1 && $array == false)
            return end($resa);
        else
            return array_slice($resa, 0, $requested, true);
    }

    function dbGetFreeVpTokens($num = 1)
    {
        $resa = $this->tokens->getTokensInLocation("stock_vp");
        while (count($resa) < $num) {
            // create blue token
            $total = count($this->tokens->getTokensOfTypeInLocation('vp'));
            $total++;
            $this->tokens->createToken("vp_b_$total", "stock_vp");
            $resa = $this->tokens->getTokensInLocation("stock_vp");
        }
        return array_slice($resa, 0, $num, true);
    }

    function getCardOwnerColor($card_id)
    {
        $info = $this->tokens->getTokenInfo($card_id);
        $loc = $info['location'];
        $color = getPart($loc, 1, true);
        return $color;
    }

    function playerHasCard($card_id, $color, $ignore_used = false)
    {
        $info = $this->tokens->getTokenInfo($card_id);
        if (!$info) {
            $this->warn("Cannot find card $card_id");
            return false;
        }
        if ($this->isPlanet($card_id) || startsWith($card_id, 'scenario')) {
            if ($info['location'] !== "tableau_${color}")
                return false;
            if ($info['state'] == 0)
                return false;
            return $card_id;
        }
        if ($this->isPermanentTech($card_id)) {
            if ($info['location'] !== "tableau_${color}")
                return false;
            if ($info['state'] == 0 && !$ignore_used)
                return false;
        } else {
            if ($info['location'] !== "setaside_${color}")
                return false;
            if ($info['state'] != CARD_STATE_TURN_LASTING)
                return false;
        }
        return $card_id;
    }

    function getIconCount($icon, $tokens, $jokers = '')
    {
        $keys = $this->tokens->toTokenKeyList($tokens);
        $boost = 0;
        foreach ($keys as $card) {
            $count = $this->mtIconCount($card, $icon);
            if (!$count && $jokers === '*')
                $count = 1;
            if ($count == 0) {
                if ($this->getRulesFor($card, 'r')) { // ROLE CARD
                    $cur_icons = $this->getRulesFor($card, 'i');
                    if (strstr($jokers, $cur_icons) !== false) {
                        $count += 1;
                    }
                } else if (startsWith($card, 'fighter') || startsWith($card, 'resource')) {
                    $type = getPart($card, 1);
                    if (strstr($jokers, $type) !== false) {
                        $count += 1;
                    }
                } else {
                    $type = $this->getRulesFor($card, 'asr');
                    if ($type) {
                        if (strstr($jokers, $type) !== false) {
                            $count += 1;
                        }
                    }
                    $type = $this->getRulesFor($card, 'asf');
                    if ($type) {
                        if (strstr($jokers, $type) !== false) {
                            $count += 1;
                        }
                    }
                }
            }

            if ($count) {
                $boost += $count;
            }
        }
        return $boost;
    }

    function getSettleBoost($color)
    {
        $tokens_perm = $this->tokens->getTokensOfTypeInLocation("card", "tableau_${color}", 1);
        return $this->getIconCount('C', $tokens_perm);
    }

    function revealPlanetSupplyTop()
    {
        $place_id = "supply_planets";
        $top = $this->tokens->getTokenOnTop($place_id, false);
        if ($top != null) {
            $token_id = $top['key'];
            // reveal back of top card in the supply
            $this->notifyWithName("tokenMoved", '', array(
                'token_id' => $token_id, 'place_id' => $place_id,
                'token_name' => $token_id, 'place_name' => $place_id, 'new_state' => 0
            ));
        }
        $this->notifyCounter("supply_planets");
        $this->notifyCounter("discard_planets");
    }

    /**
     * Pushing triggered ability on stack
     * @param string $color acting player color
     * @param string $card_id that triggers the ability
     * @param string $other - target card id or other argument of ability
     * @param int $num - type of rule
     */
    function pushAbility($color, $card_id, $other, $num)
    {
        $player_id = $this->getPlayerIdByColor($color);

        $rules = $this->getRulesByNum($card_id, $num);
        $notif = clienttranslate('game activates triggered effects of ${token_name}');
        $this->notifyWithName('playerLog', $notif, [
            'player_id' => $player_id, 'token_id' => $card_id,
            'token_name' => $card_id
        ]);
        if ($this->saction_AutoPerform($color, $card_id, $rules)) {
            return;
        }
        //$this->debugConsole("push abi");
        //check for any other triggered abilities and find out max number
        $cur = count($this->tokens->getTokensOfTypeInLocation("abi_p_", null, "!=-1"));
        //create ability marker that contains rule type, active player color and stack index, place it on the card
        $this->dbMoveOrCreate("abi_p_${num}_${color}_${cur}", $card_id, $cur); // marker indicating ability card and its rule clause
        if ($other)
            $this->dbMoveOrCreate("abi_target_${cur}", $other, $cur); // marker indicating target card, if trigger had it
    }

    function peekAbility()
    {
        return $this->popAbility(true);
    }

    function popAbility($peek = false)
    {
        // pull all triggered abilities from all cards sort in stack order (state is order)
        $abi_stack = $this->tokens->getTokensOfTypeInLocation("abi_p_", null, "!=-1", "token_state DESC");
        $top = array_shift($abi_stack);
        if ($top == null)
            return null; // nothing on stack
        //$this->warn(toJson($top));
        $cur = $top['state'];
        $topkey = $top['key']; //"abi_p_${num}_${color}_${cur}"
        $card_id = $top['location'];
        $num = getPart($topkey, 2);
        $color = getPart($topkey, 3);
        $target_info = $this->tokens->getTokenInfo("abi_target_${cur}");
        // reconstruct trigger info from the ability
        $rules = $this->getRulesByNum($card_id, $num);
        $triginfo = ['card' => $card_id, 'num' => $num, 'rules' => $rules, 'actor_color' => $color];
        if ($target_info)
            $triginfo['other'] = $target_info['location'];
        if ($peek == false) {
            $this->tokens->moveToken($topkey, 'dev_null', -1);
            if ($target_info) {
                $this->tokens->moveToken("abi_target_${cur}", 'dev_null', -1);
            }
        }
        return $triginfo;
    }

    function getRulesByNum($card_id, $num)
    {
        $rules = '';
        if ($num < 20) { // first 20 reserved for triggered abilities
            $trigarr = $this->getRulesFor($card_id, 'trig');
            if ($trigarr) {
                $trig = $trigarr[$num];
                $woot = explode(":", $trig, 2);
                $rules = $woot[1];
            }
        } else if ($num == CARD_RULE_SPECIAL) {
            $rules = $this->getRulesFor($card_id, 'special');
        } else if ($num == CARD_RULE_ACTIVATABLE) {
            $rules = $this->getRulesFor($card_id, 'e');
        } else if ($num == CARD_RULE_ACTION) {
            $rules = $this->getRulesFor($card_id, 'a');
        } else if ($num == CARD_RULE_ACTION_PART2) {
            $rules = $this->getRulesFor($card_id, 'a');
            $i = strpos($rules, ".");
            $rules = substr($rules, $i + 1);
        } else if ($num == CARD_RULE_ROLE) {
            $rules = $this->getRulesFor($card_id, 'r');
        } else if ($num == CARD_RULE_ROLE_LEADER) {
            $rules = $this->getRulesFor($card_id, 'l');
        } else {
            $this->systemAssertTrue('not sup yet');
        }
        //$this->debugConsole("rules for $card_id $num => $rules|");
        return $rules;
    }

    function consumeOperation(&$rules, $consume, $replace = '')
    {
        $hit = false;
        $rules_a = explode('/', $rules);
        foreach ($rules_a as $i => $clause) {
            if (!$clause) {
                unset($rules_a[$i]);
                continue;
            }
            if (startsWith($clause, $consume)) {
                $rules_a[$i] = substr($clause, 1) . $replace;
                $hit = true;
            } else {
                unset($rules_a[$i]); // choice is eliminated
            }
        }
        $rules = implode('/', array_values($rules_a));
        return $hit;
    }

    function fillSlot(&$slotsarr, $restype, $target = null)
    {
        if ($target == null)
            $target = $restype;
        if (!array_key_exists($target, $slotsarr)) {
            if ($target != 'n') { // any
                return $this->fillSlot($slotsarr, $restype, 'n');
            }
            return false;
        }
        $mod = count($slotsarr[$target]);
        for ($j = 0; $j < $mod; $j++) {
            if ($slotsarr[$target][$j] === 0) {
                $slotsarr[$target][$j] = $restype; // fill the slot
                return true;
            }
        }
        // we cannot not find empty slot
        if ($target != 'n') {
            return $this->fillSlot($slotsarr, $restype, 'n');
        } else {
            return false;
        }
    }

    function debugConsole($info, $args = array())
    {
        $this->notifyAllPlayers("log", '', ['log' => $info, 'args' => $args]);
        $this->warn($info);
    }

    // Debug from chat: launch a PHP method of the current game for debugging purpose
    function debugChat($message)
    {
        $res = [];
        preg_match("/^(.*)\((.*)\)$/", $message, $res);
        if (count($res) == 3) {
            $method = $res[1];
            $args = explode(',', $res[2]);
            foreach ($args as &$value) {
                if ($value === 'null') {
                    $value = null;
                } else if ($value === '[]') {
                    $value = [];
                }
            }
            if (method_exists($this, $method)) {
                self::notifyAllPlayers('simplenotif', "DEBUG: calling $message", []);
                $ret = call_user_func_array(array($this, $method), $args);
                if ($ret !== null)
                    $this->debugConsole("RETURN: $method ->", $ret);
                return true;
            } else {
                self::notifyPlayer($this->getCurrentPlayerId(), 'simplenotif', "DEBUG: running $message; Error: method $method() does not exists", []);
                return true;
            }
        }
        return false;
    }

    function next_ActionDispatch($player_id)
    {
        $args = $this->arg_playerTurnExtra($player_id);
        if ($args['enabled']) {
            //$this->debugConsole("extra enabled for $player_id", $args);
            $this->gamestate->nextState('loopback');
            return;
        }
        $rolePlayed = $this->getGameStateValue('role_phases_played');
        if ($this->getGameStateValue('action_played') < $this->getGameStateValue('actions_allowed')) {
            $this->gamestate->nextState('loopback');
        } else if ($rolePlayed > 0) { // role already played
            $this->gamestate->nextState('last'); //upkeep
        } else {
            $this->gamestate->nextState('next'); //role
        }
    }

    function next_RoleDispatch($player_id)
    {
        $multi = $this->isMultiActive();
        $args = $this->arg_playerTurnExtra($player_id);
        if ($args['enabled']) {
            //$this->debugConsole("extra enabled for $player_id", $args);
            if ($multi)
                $this->gamestate->changeActivePlayer($player_id);
            $this->gamestate->nextState('extra');
            return;
        }
        $state_name = $this->getStateName();
        if ($state_name == 'playerTurnRole' || $state_name == 'playerTurnPreDiscard' || $state_name == 'playerTurnRoleExtra') {
            $discardPlayed = $this->getGameStateValue('discard_played');
            $rolePlayed = $this->getGameStateValue('role_phases_played');
            $roleAllowerd = $this->getGameStateValue('role_phases_allowed');
            $color = $this->getPlayerColor($player_id);
            $logistics = $this->playerHasCard('card_tech_3_86', $color);
            if ($logistics) {
                $actionPlayed = $this->getGameStateValue('action_played');
                if ($actionPlayed == 0) {
                    $this->gamestate->nextState('next'); // follow
                    return; // cannot go pre-discard yet
                }
            }
            $leader = $this->getGameStateValue('leader');
            if ($leader == $player_id && $this->getUntrivialDiscardOrActivate($color) && !$discardPlayed && $rolePlayed >= $roleAllowerd) {
                $this->gamestate->nextState('discard'); // pre-discard
                return;
            }
        }

        $this->gamestate->nextState('next'); // follow
    }

    function next_StateDispatch($player_id)
    {
        $stateName = $this->getStateName();
        if ($stateName === "playerTurnAction") {
            $this->next_ActionDispatch($player_id);
            return;
        }
        $this->next_RoleDispatch($player_id);
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Player actions
    //////////// 
    /*
     * Each time a player is doing some game action, one of the methods below is called.
     * (note: each method below must match an input method in eminentdomain.action.php)
     */
    function action_playRole($role, $card, $boost, $choices)
    {
        self::checkAction('playRole');
        $player_id = $this->getActivePlayerId();
        $color = $this->getPlayerColor($player_id);
        $this->saction_Role(true, $role, $card, $color, $boost, $choices);
        $this->incGameStateValue('role_phases_played', 1);
        $this->setGameStateValue('followers', 0);
        $this->setGameStateValue('followers_waiting', 0);
        $this->setGameStateValue('follow_played', 0);

        $this->next_RoleDispatch($player_id);
    }

    function action_playFollow($boost, $choices)
    {
        self::checkAction('playFollow');
        $player_id = $this->getCurrentPlayerId();
        //$leader_id = $this->getGameStateValue('leader');
        $color = $this->getPlayerColor($player_id);
        $role_index = $this->getGameStateValue('role');
        $role = $this->materials['role_icons'][$role_index];
        $active_follower = $this->getGameStateValue('active_follower');
        if ($role == 'S' || $role == 'R') {
            $this->userAssertTrue(clienttranslate("Cannot Follow out of order for Survey or Research roles"), $player_id == $active_follower);
        }
        $this->saction_Role(false, $role, 'x_follow', $color, $boost, $choices);
        $this->setPlayerMask($player_id, 'followers');
        $this->next_RoleDispatch($player_id);
    }

    function action_playAction($card, $choices, $actnum)
    {
        self::checkAction('playAction');
        $player_id = $this->getActivePlayerId();
        $color = $this->getPlayerColor($player_id);
        $this->saction_Action($card, $color, $choices, $actnum);
        $this->next_ActionDispatch($player_id);
    }



    function action_playActivatePermanent($card, $choices)
    {
        self::checkAction('playActivatePermanent');
        $player_id = $this->getActivePlayerId();
        $color = $this->getPlayerColor($player_id);
        // check card
        $rule = $this->getRulesFor($card, 'e');
        $this->systemAssertTrue("playActivatePermanent: bad state for $card", $this->tokens->getTokenState($card) != 0);
        $newstate = CARD_STATE_PERMANENT_TAPPED;
        if ($card === 'card_tech_3_77') { //Hyperefficency
            $newstate = CARD_STATE_PERMANENT; // can use multiple times
        }
        $notif = clienttranslate('${player_name} activates ${token_name}');
        $this->dbSetTokenLocation($card, null, $newstate, $notif);
        $this->saction_ExecuteRules($card, $color, $choices, $rule, false);
        $this->saction_ValidateRestOfChoices($choices, $rule);
        $this->next_StateDispatch($player_id);
    }

    function action_playPick($card)
    {
        self::checkAction('playPick');
        $player_id = $this->getActivePlayerId();
        $color = $this->getPlayerColor($player_id);
        // $check card
        $tokens = $this->tokens->getTokensOfTypeInLocation("card_planet", "hand_$color");
        $this->systemAssertTrue("Bad card for pick", array_key_exists($card, $tokens));
        $this->notifyWithName('playerLog', clienttranslate('You picked ${token_name}'), [
            '_private' => true,
            'token_id' => $card, 'token_name' => $card
        ]);
        $this->dbSetTokenLocation($card, "tableau_$color", PLANET_UNSETTLED, clienttranslate('${player_name} picked a planet'));
        foreach ($tokens as $planet => $info) {
            if ($planet !== $card)
                $this->dbSetTokenLocation($planet, "discard_planets", 1, ''); //clienttranslate('${player_name} discards planet ${token_name}')
        }
        $this->next_StateDispatch($player_id);
    }

    function action_playExtra($choices)
    {
        self::checkAction('playExtra');

        $player_id = $this->getMostlyActivePlayerId();
        $color = $this->getPlayerColor($player_id);
        $pending_info = $this->popAbility();
        $rules = $pending_info['rules'];
        $card = $pending_info['card'];
        $this->saction_ExecuteRules($card, $color, $choices, $rules, false);
        $this->saction_ValidateRestOfChoices($choices, $rules);
        $this->next_StateDispatch($player_id);
    }

    function action_playDiscard($choices)
    {
        self::checkAction('playDiscard');
        $player_id = $this->getActivePlayerId();
        $color = $this->getPlayerColor($player_id);
        // $check card
        $choices = trim($choices);
        $choices_arr = $choices ? explode(' ', $choices) : [];
        foreach ($choices_arr as $card) {
            $this->dbSetTokenLocation($card, "discard_$color", 0, clienttranslate('${player_name} discards ${token_name}'), [
                'nod' => true
            ]);
        }
        $this->checkTooManyCardsInHand($color);
        if (count($choices_arr) > 0)
            $this->notifyAnimate();
        $this->setGameStateValue('discard_played', 1);
        $this->gamestate->nextState('next');
    }



    function action_skipAction()
    {
        self::checkAction('skipAction');
        $this->notifyWithName('playerLog', clienttranslate('${player_name} skips ACTION'));
        $role = $this->getGameStateValue('role');
        if ($role != -1) { // role already played
            $this->gamestate->nextState('last');
        } else {
            $this->gamestate->nextState('next');
        }
    }

    function action_playDissent()
    {
        self::checkAction('playDissent');
        $player_id = $this->getCurrentPlayerId();
        $this->action_playDissentGame($player_id);
        $this->next_RoleDispatch($player_id);
    }

    function action_playDissentGame($player_id)
    {
        $color = $this->getPlayerColor($player_id);
        $this->setPlayerMask($player_id, 'followers');
        $this->notifyWithName('playerLog', clienttranslate('${player_name} dissents'), null, $player_id);
        // draw 1 card
        $this->saction_Draw($player_id);
        $dissention = $this->playerHasCard('card_tech_3_96', $color);
        if ($dissention) {
            $this->saction_Draw($player_id);
        }
        // triggered ability
        $role_index = $this->getGameStateValue('role');
        $role = $this->materials['role_icons'][$role_index];
        $this->saction_TriggerAbilities("d-$role", $color, null);
        $this->dbIncStatChecked(1, "game_dissent", $player_id);
    }

    function action_playWait()
    {
        self::checkAction('playWait');

        if ($this->getStateName() == 'playerTurnPreDiscard') {
            $this->gamestate->nextState('next');
            return;
        }
        $player_id = $this->getCurrentPlayerId();
        $active_follower = $this->getGameStateValue('active_follower');
        $this->userAssertTrue('You are next player to do an action, you cannot wait, choose Follow or Dissent', $active_follower != $player_id);
        $this->notifyWithName('playerLog', clienttranslate('${player_name} waits'), null, $player_id);
        $this->setPlayerMask($player_id, 'followers_waiting');
        $this->gamestate->setPlayerNonMultiactive($player_id, 'next');
    }

    function action_changePreference($pref, $value)
    {
        // anytime action, no checks
        $player_id = $this->getCurrentPlayerId();
        $color = $this->getPlayerColor($player_id);
        if ($color === 0) {
            return; // not a player - ignore
        }
        $this->dbSetPref($pref, $color, $value);
    }

    function saction_EndOfTurn($player_id)
    {
        $color = $this->getPlayerColor($player_id);
        // count point for resource markers
        $tokens = $this->tokens->getTokensOfTypeInLocation("resource", null, RESOURCE_STATE_MARKER);
        if (count($tokens) > 0) {
            $restypes = [];
            foreach ($tokens as $info) {
                $restype = getPart($info['key'], 1);
                $loc = $info['location'];
                $restypes[$loc][$restype] = 1;
            }
            foreach ($restypes as $card => $arr) {
                $distinct = count($arr);
                $eot_rules = $this->getRulesFor($card, 'eot');
                if ($eot_rules == 'div_r')
                    $this->saction_GainInfluenceTokens($player_id, $distinct, $card); // 1 vp per type
                else if ($eot_rules == 'max_r'); // 1 for max of 1 type - already counted
                else
                    $this->warn("unknown resource type collection for $card");
            }
            $this->notifyAnimate();
        }



        // all players set aside card go to discard
        $players = $this->loadPlayersBasicInfos();
        foreach ($players as $player_info) {
            $pcolor = $player_info['player_color'];
            $tokens = $this->tokens->getTokensOfTypeInLocation("card", "setaside_${pcolor}");
            $this->dbSetTokensLocation($tokens, "discard_${pcolor}", CARD_STATE_FACEUP, '', ['nod' => true]);
            $tokens = $this->tokens->getTokensOfTypeInLocation("resource", "setaside_${pcolor}");
            $this->dbSetTokensLocation($tokens, 'stock_resource', 0, '', ['nod' => true]);
            $tokens = $this->tokens->getTokensOfTypeInLocation("fighter", "setaside_${pcolor}");
            $this->dbSetTokensLocation($tokens, 'stock_fighter', 0, '', ['nod' => true]);
        }
        // discard resource markers
        $tokens = $this->tokens->getTokensOfTypeInLocation("resource", null, RESOURCE_STATE_MARKER);
        $this->dbSetTokensLocation($tokens, 'stock_resource', 0, '', ['nod' => true]);
        // re-activate tech cards
        $tokens = $this->tokens->getTokensOfTypeInLocation("card_tech", "tableau_${color}", CARD_STATE_PERMANENT_TAPPED);
        $this->dbSetTokensLocation($tokens, "tableau_${color}", CARD_STATE_PERMANENT, '', ['nod' => true]);


        $this->notifyAnimate();
        $this->notifyAnimate();
        $this->saction_DrawMax($player_id);
    }

    function saction_DrawMax($player_id)
    {
        $color = $this->getPlayerColor($player_id);
        $count = $this->tokens->countTokensInLocation("hand_$color");
        $iconsinfo = $this->arg_iconsInfo($color, ['h']);
        $max = $iconsinfo['hand_size'];
        if ($count > $max) {
            $this->notifyWithName('playerLog', '${player_name} has too many cards in hand ${x} out of ${max}', [
                'max' => $max, 'player_id' => $player_id, 'x' => $count
            ]);
            return;
        }
        for ($i = $count; $i < $max; $i++) {
            $this->saction_Draw($player_id, false);
        }
        $this->notifyWithName('playerLog', clienttranslate('${player_name} draws ${x} card(s) up to their hand limit of ${max}'), [
            'max' => $max, 'player_id' => $player_id, 'x' => ($max - $count)
        ]);
        $this->notifyCounter("deck_$color");
        $this->notifyCounter("discard_$color");
        $this->notifyCounter("hand_$color");
    }

    function saction_Draw($player_id, $useNotif = true)
    {
        // draw 1 card
        $color = $this->getPlayerColor($player_id);
        $picked_arr = $this->tokens->pickTokensForLocation(1, "deck_$color", "hand_$color");
        $picked = array_shift($picked_arr);
        if ($picked) {
            $notif = '';
            if ($useNotif)
                $notif = clienttranslate('${you} draw ${token_name}');
            $this->dbSetTokenLocation($picked['key'], "hand_$color", 1, $notif, [
                '_private' => true,
                'player_id' => $player_id, 'you' => 'you'
            ]);
            //clienttranslate('${you} draw ${token_name}')
        }
        if ($useNotif) {
            $this->notifyCounter("deck_$color");
            $this->notifyCounter("discard_$color");
            $this->notifyCounter("hand_$color");
        }
    }

    function saction_Role($leader, $role, $card, $color, $boost, $choices)
    {
        //throw new BgaUserException($color);
        $player_id = $this->getPlayerIdByColor($color);
        //$this->warn("plays Role leader=$leader role=$role card=$card boost=/$boost/ /$choices/");
        $choices_arr = $choices;
        $boost = trim($boost);
        $boost_arr = $boost ? explode(' ', $boost) : [];
        // can be leader
        $bureacracy = $this->playerHasCard('card_tech_3_97', $color);
        if (!$leader && $bureacracy && ($role == 'C' || $role == 'W'))
            $leader = true;

        // role notify and follower
        $role_name = $this->materials['icons'][$role];
        $role_args = ['player_id' => $player_id, 'role_name' => $role_name, 'i18n' => ['role_name']];

        if (startsWith($card, "x_follow")) {
            $follower = true;
            $this->notifyWithName('playerLog', clienttranslate('${player_name} follows ${role_name} role'), $role_args);
        } else {
            $follower = false;
            $this->notifyWithName('playerLog', clienttranslate('${player_name} plays ${role_name} role as a leader'), $role_args);
        }
        // trigger event
        if ($follower)
            $event = "f-$role";
        else
            $event = "l-$role";
        // inc role stat
        $this->dbIncStatChecked(1, "game_role_$role", $player_id);
        if ($follower)
            $this->dbIncStatChecked(1, "game_follow", $player_id);
        // card trigger role change on follow
        if ($follower && ($role == 'P' || $role == 'T')) {
            $freedome = $this->playerHasCard('card_tech_E_2', $color);
            if ($freedome) {
                if (count($choices_arr) > 0) {
                    $lookup = strtoupper($choices_arr[0][0]);
                    if ($lookup != $role) {
                        $role = $lookup; // role changed
                        $notif = clienttranslate('${player_name} activates triggered effects of ${token_name}');
                        $this->notifyWithName('playerLog', $notif, [
                            'player_id' => $player_id, 'token_id' => $freedome,
                            'token_name' => $freedome
                        ]);
                    }
                }
            }
        }

        $iconsinfo = $this->arg_iconsInfo($color, [$role], $event);
        $perm_boost = $iconsinfo['perm_boost_num'][$role];
        $hand_boost = 0;
        foreach ($boost_arr as $boost_card) {
            $this->systemAssertTrue("card $boost_card has no boost $role", array_key_exists($boost_card, $iconsinfo['hand_boost'][$role]));
            $hand_boost += $iconsinfo['hand_boost'][$role][$boost_card];
        }

        if (!startsWith($card, "x_")) {
            $this->notifyWithName('playerLog', clienttranslate('${player_name} gains ${token_name} role card'), [
                'token_name' => $card
            ]);
            array_unshift($boost_arr, $card);
            $hand_boost++;
        } else if (getPart($card, 2, true) === "bottom") {
            $perm_boost++; // except Colonize?
        }
        $strength = $hand_boost + $perm_boost;


        if (!$leader && $strength == 0 && $role != 'R') {
            //$this->userAssertTrue(self::_("To follow this Role add boost or choose Dissent"));
            $this->notifyWithName('playerLog', clienttranslate('${player_name} has no boost for this role'), $role_args);
            return;
        }
        if ($role != 'C')
            $this->saction_PlayCardsToAside($player_id, $boost_arr, clienttranslate('${player_name} plays boost ${token_names}'));
        // $this->warn("$role with ".toJson($boost_arr));
        $thor_sur = null;
        switch ($role) {
            case 'C': // Colonize
                $this->setGameStateValue('role', 3);
                while (count($choices_arr) > 0) {
                    $params = array_shift($choices_arr);
                    $command = array_shift($params);

                    if ($command === 'c') {
                        $planet = $params[0];
                        $bcard = array_shift($boost_arr);
                        if ($bcard && !startsWith($bcard, "x_"))
                            $this->saction_Colonize($bcard, $color, $planet);
                        else
                            $this->systemAssertTrue("Cannot colonize without a card");
                    } else if ($command === 's') {
                        $this->systemAssertTrue("Cannot settle the planet if not a role leader", $leader);
                        $bcard = array_shift($boost_arr);
                        $planet = $params[0];
                        if ($this->saction_Settle($bcard, $color, $planet, $choices_arr) == false) {
                            $this->userAssertTrue(self::_("Planet does not have enough colonies"));
                        }
                        if ($bcard &&  !startsWith($bcard, "x_")) {
                            $this->saction_PlayCardsToAside($player_id, [$bcard], '');
                        }
                    } else if ($command === 'x') {
                        // do nothing
                    } else {
                        $this->systemAssertTrue("Unsupported operation $command in colonize");
                    }
                }
                // anything left
                $this->saction_PlayCardsToAside($player_id, $boost_arr, clienttranslate('${player_name} plays boost ${token_names}'));
                $this->notifyCounter("supply_colonize", ['noa' => true]);
                break;
            case 'W': // Warfare
                $this->setGameStateValue('role', 0);
                if (count($choices_arr) > 0) {
                    $params = array_shift($choices_arr);
                    $command = array_shift($params);
                    if ($command === 'a') {
                        $this->userAssertTrue(self::_("Cannot attack a planet if not a role leader"), $leader);
                        $planet = array_shift($params);
                        $this->saction_Attack($color, $planet, $choices_arr);
                    } else if ($command === 'x') {
                    } else {
                        $this->systemAssertTrue("Unsupported operation $command");
                    }
                } else {
                    $collect = $this->dbGetFreeFighterInfo('F', $strength, true);
                    //$this->warn("collect fighters $strength ".toJson($collect));
                    $this->dbSetTokensLocation($collect, "tableau_$color", 0, clienttranslate('${player_name} collects ${token_div_count}'), [
                        'player_id' => $player_id
                    ]);
                }
                $this->notifyCounter("supply_warfare", ['noa' => true]);
                break;
            case 'S': // Survey

                foreach ($choices_arr as $i => $params) {
                    if ($params[0] === 'activate') {
                        if ($params[1] === 'card_tech_E_24') { //thorough survey
                            unset($choices_arr[$i]);
                            $thor_sur = 'card_tech_E_24';
                        }
                    }
                }

                $this->setGameStateValue('role', 1);
                $lookup = $strength - 1;
                if ($leader)
                    $lookup++;
                if ($lookup == 0) {
                    $this->notifyWithName('playerLog', clienttranslate('${player_name} does not have enough boost, at least 2 survey symbols required to play non-leader survey role'), $role_args);
                    break;
                }
                if ($thor_sur)
                    $lookup -= 2;
                if ($lookup <= 0) {
                    $this->notifyWithName('playerLog', clienttranslate('${player_name} does not have enough boost for Thorough Survey'), $role_args);
                    break;
                }
                $place_id = "supply_planets";
                $picked_arr = $this->tokens->pickTokensForLocation($lookup, $place_id, "hand_$color");
                $count = count($picked_arr);
                // $this->warn("survey $thor_sur lookup=$lookup f=$follower count=$count|");
                if ($count) {
                    if ($count == 1) {
                        // right into tableu no choosing since only one
                        $token_id = $picked_arr[0]['key'];
                        $this->dbSetTokenLocation($token_id, "tableau_$color", 0, clienttranslate('${player_name} draws a planet, its picked automatically since its the only one'), [
                            'player_id' => $player_id
                        ]);
                    } else {
                        $this->dbSetTokensLocation($picked_arr, "hand_$color", 0, clienttranslate('${player_name} draws ${count} planets'), [
                            'player_id' => $player_id, 'count' => $count
                        ]);
                        $this->dbSetTokensLocation($picked_arr, "hand_$color", 1, '', [
                            'player_id' => $player_id,
                            '_private' => true
                        ]);
                    }
                    if ($thor_sur && $count == 2) {
                        $this->notifyAnimate();
                        $this->dbSetTokensLocation($picked_arr, "tableau_$color", 0, clienttranslate('${player_name} picks 2 planets'), ['player_id' => $player_id]);
                    }
                    $this->revealPlanetSupplyTop();
                } else {
                    if (!$leader)
                        $this->userAssertTrue(self::_("No more planets left"));
                }
                if ($thor_sur && $count > 2) {
                    // leave a marker after all other triggered abilities, see end of function   

                } else {
                    $thor_sur = null;
                }
                $this->notifyCounter("supply_survey", ['noa' => true]);
                break;
            case 'P':
            case 'T':
                if (!$follower)
                    $this->setGameStateValue('role', ($role == 'P') ? 4 : 5);
                $rule = ($role == 'P') ? 'p' : 't';
                $rules = str_repeat($rule, $strength);
                $this->saction_ExecuteRules("x_auto", $color, $choices_arr, $rules, false);
                $this->notifyCounter("supply_produce", ['noa' => true]);
                break;
            case 'R': // Research
                $this->setGameStateValue('role', 2);
                $this->systemAssertTrue("Bad research command - no args", count($choices_arr) >= 1);
                $choice = array_shift($choices_arr);
                $params = $choice;
                $command = array_shift($params);
                if (count($params) == 0) {
                    break; // skipping tech
                }

                $tech = $params[0];
                if (startsWith($tech, 'card_fleet_b'))
                    $tech = preg_replace("/_b_/", "_i_", $tech);
                if ($this->saction_PayForTech($tech, $color, $strength, $choices_arr))
                    $this->saction_BuyTech($tech, $color, $role_args);
                $this->notifyCounter("supply_research", ['noa' => true]);
                break;
            default:
                $this->userAssertTrue("Not implemented yet");
                break;
        }
        $this->saction_ValidateRestOfChoices($choices_arr, $role);
        $this->saction_TriggerAbilities($event, $color, null);
        if ($thor_sur)
            $this->pushAbility($color, $thor_sur, null, CARD_RULE_SPECIAL);
    }

    function saction_PayForTech($tech, $color, $strength, &$choices_arr)
    {
        $techs = $this->arg_research($color, $tech, true);
        if (!isset($techs[$tech])) {
            $this->userAssertTrue(self::_("Not enough colonized planets to research this Technology"), true);
        }
        $rec = $techs[$tech];
        $cost = $rec['b'];
        $rem = $this->getGameStateValue('boost_rem');
        if ($rem > 0)
            $strength = $rem;
        $this->systemAssertTrue('missing payment info', $choices_arr && count($choices_arr) > 0);
        $payment1 = $choices_arr[0];
        if ($payment1[0] == 'R') {
            if ($strength >= $cost) {
                //$hand_boost + $perm_boost;
                $this->setGameStateValue('boost_rem', $strength - $cost);
                $mcost = $cost;
                while (count($choices_arr) > 0 && $mcost > 0) {
                    array_shift($choices_arr);
                    $mcost--;
                }
                // use research
            } else {
                $this->notifyWithName(
                    'playerLog',
                    clienttranslate('${player_name} does not have enough boost to buy ${token_name}'),
                    ['token_id' => $tech, 'token_name' => $tech]
                );
                if ($rem > 0)
                    $mess = sprintf(self::_("This card %s requires %d research cost, remaining boost is %d"), self::_($tech), $cost, $strength);
                else
                    $mess = sprintf(self::_("This card %s requires %d research cost, boost of %d was provided"), self::_($tech), $cost, $strength);
                $this->userAssertTrue($mess);
                return false;
            }
        } else {
            if ($rec['canattack']) {
                // use attack
                $ftype = $rec['bm'][1];
                $mcost = $rec['bm'][0];
                $rules = str_repeat($ftype, $mcost) . ">";
                $this->saction_ExecuteRules("x_auto", $color, $choices_arr, $rules, false);
            } else {
                $mess = sprintf(self::_("This card %s requires military cost which you cannot provide"), self::_($tech), $cost, $strength);
                $this->userAssertTrue($mess);
                return false;
            }
        }
        $sm = $this->playerHasCard('card_tech_E_9', $color, false);
        if ($sm) { // scientific method untapped
            // MARK used planets and perm boost
            $mark_planets = $rec['pl'];
            foreach ($mark_planets as $planet) {
                $this->dbSetTokenState($planet, CARD_STATE_PLANET_USED_AS_REQ);
            }
        }
        return true;
    }

    function saction_BuyTech($tech, $color = null, $extra_args = null)
    {
        if ($color === null) {
            $player_id = $this->getActivePlayerId();
            $color = $this->getPlayerColorById($player_id);
        } else
            $player_id = $this->getPlayerIdByColor($color);
        if (!$extra_args) {
            $extra_args = ['player_id' => $player_id];
        }
        if (startsWith($tech, 'card_fleet_i')) {
            $ownfleet = $this->tokens->getTokenOfTypeInLocation('card_fleet', "tableau_${color}");
            $this->dbSetTokenLocation($tech, "tableau_$color", 1, clienttranslate('${player_name} researches ${token_name} Technology'), $extra_args);
            $this->dbSetTokenLocation($ownfleet['key'], "dev_null", 0, '');
            return;
        }
        $location = $this->tokens->getTokenLocation($tech);
        $this->systemAssertTrue("Invalid tech location: $location", startsWith($location, "supply_tech"));
        $inf = $this->getRulesFor($tech, 'v');
        if ($this->isPermanentTech($tech)) {
            $this->dbSetTokenLocation($tech, "tableau_$color", 1, clienttranslate('${player_name} researches ${token_name} Technology'), $extra_args);
            $flip = $this->getRulesFor($tech, 'flip');
            $this->dbSetTokenLocation($flip, "dev_null", 0, '');
            if ($inf > 0)
                $this->dbIncScoreValueAndNotify($player_id, $inf, clienttranslate('${player_name} gains +${inc} Influence from card ${token_name}'), 'game_cards', [
                    'token_id' => $tech, 'token_name' => $tech, 'place' => $tech
                ]);
            // card come into play effects
            if ($tech === 'card_tech_3_87') { //Productivity
                $this->incGameStateValue('actions_allowed', 1);
            }
            $this->saction_TriggerAbilities("enter", $color, $tech);
        } else {
            // $this->dbSetTokenLocation($tech, "tableau_$color", 1, '');
            if ($inf > 0)
                $this->dbIncScoreValueAndNotify($player_id, $inf, clienttranslate('${player_name} gains +${inc} Influence from card ${token_name}'), 'game_cards', [
                    'token_id' => $tech, 'token_name' => $tech, 'place' => "tableau_$color"
                ]);
            $this->dbSetTokenLocation($tech, "hand_$color", 0, clienttranslate('${player_name} researches ${token_name} Technology'), $extra_args);
        }
    }

    function saction_Attack($color, $planet, &$choices_arr, $card = null)
    {
        $player_id = $this->getPlayerIdByColor($color);
        if ($planet === 'skip') {
            $this->notifyWithName('playerLog', clienttranslate('${player_name} skips attack'), [
                'player_id' => $player_id
            ]);
            return;
        }


        $annex = ($card === 'card_tech_E_36'); // the attack action was chosen by playing the annex card

        $militaryCampaign = $this->playerHasCard('card_tech_E_38', $color);

        //$this->warn("playing attack /$color/ /$planet/".toJson($choices_arr));
        $info = $this->tokens->getTokenInfo($planet);
        $state = $info['state'];
        $owner_color = getPart($info['location'], 1);
        $inc = $this->getRulesFor($planet, 'v');
        if ($state == 1 && $militaryCampaign) {
            $cost = 'B' . (str_repeat('F', $inc));
            $notif = clienttranslate('${player_name} activates effects of ${token_name}');
            $this->notifyWithName('playerLog', $notif, [
                'player_id' => $player_id, 'token_id' => $militaryCampaign,
                'token_name' => $militaryCampaign
            ]);


            $this->dbIncScoreValueAndNotify($this->getPlayerIdByColor($owner_color), -$inc, clienttranslate('${player_name} loses ${inc} Influence from ${place_name}'), 'game_planets', [
                'place_id' => $planet, 'place_name' => $planet, 'place' => $planet
            ]);
            $this->notifyAnimate();
            $this->dbSetTokenLocation($planet, "tableau_$color", 1, clienttranslate('${player_name} Attacks ${token_name}'), [
                'player_id' => $player_id
            ]);
            $this->saction_ExecuteRules(null, $color, $choices_arr, $cost . ">", false);
            $this->dbIncScoreValueAndNotify($player_id, $inc, clienttranslate('${player_name} gains +${inc} Influence from ${place_name}'), 'game_planets', [
                'place_id' => $planet, 'place_name' => $planet, 'place' => $planet
            ]);

            $this->saction_Reparations($this->getPlayerIdByColor($owner_color), 2);

            $scorchedEarthPolicy = false;
        } else {
            $this->systemAssertTrue("planet is already colonized", $state == 0);

            // warfare discounts
            $imp_fleet = $this->tokens->getTokenOfTypeInLocation("card_fleet_i", "tableau_$color");
            $scorchedEarthPolicy = $this->playerHasCard('card_tech_2_85', $color);

            $mrec = $this->mtGetFightCost($planet);
            $cost = $mrec['rule'];
            if (count($choices_arr) > 0 && $imp_fleet) {
                if ($choices_arr[0][0] === 'B') {
                    $cost = 'B';
                }
            }
            $fdiscount = $this->arg_attackDiscount($color);
            if ($fdiscount) {
                $cost = preg_replace("/F/", "", $cost, $fdiscount);
            }

            // dispose of cards on the planet (i.e. colonies)
            $ptokens = $this->tokens->getTokensInLocation($planet);
            $owner_id = $this->getPlayerIdByColor($owner_color);
            foreach ($ptokens as $key => $info) {
                $this->dbSetTokenLocation($key, "setaside_$owner_color", CARD_STATE_USED_CARD, '', [
                    'nod' => true,
                    'player_id' => $owner_id
                ]);
            }

            // attack and pay
            $this->dbSetTokenLocation($planet, "tableau_$color", 1, clienttranslate('${player_name} Attacks ${token_name}'), [
                'player_id' => $player_id
            ]);
            $this->saction_ExecuteRules(null, $color, $choices_arr, $cost . ">", false);

            // score
            $this->dbIncScoreValueAndNotify($player_id, $inc, clienttranslate('${player_name} gains +${inc} Influence from ${place_name}'), 'game_planets', [
                'place_id' => $planet, 'place_name' => $planet, 'place' => $planet
            ]);

            // reparations
            if ($owner_color !== $color && $annex) {
                $this->saction_Reparations($owner_id, 2);
            }
        }


        $this->notifyAnimate();
        if ($scorchedEarthPolicy) {
            // Move 1 fighter on the card from the reserve
            $f1 = $this->dbGetFreeFighterInfo();
            $this->dbSetTokenLocation($f1['key'], $planet, RESOURCE_STATE_BLOCKER, '', ['nod' => true]);
        }
        // triggered
        $this->saction_TriggerAbilities("attack", $color, $planet);
        $this->saction_TriggerAbilities("enter", $color, $planet);
        $this->notifyAnimate();
    }

    function saction_TriggerAbilities($event, $color, $other)
    {
        // cards with triggering ability
        // test: saction_TriggerAbilities(enter,ff0000,card_tech_2_95)   
        if ($event === 'enter') { // replenish triggers on enter
            $this->saction_AutoReplenish($other, $color);
        }
        $cards = $this->mtTriggering($event);
        //$this->debugConsole("trig event [$event] $color $other ",$cards);
        foreach ($cards as $card) {
            if ($event === 'enter') { // enter events only trigger on itself
                if ($other === $card) {
                    $forreal = $card;
                } else {
                    $forreal = false;
                }
            } else
                $forreal = $this->playerHasCard($card, $color);

            if ($forreal) {
                //$this->debugConsole("trig check $card $event $other $forreal");
                $triginfo = $this->mtTriggerInfo($card, $event);
                $this->pushAbility($color, $card, $other, $triginfo['num']);
            }
        }
    }

    function saction_AutoPerform($color, $card_id, $rules)
    {
        if (count(array_count_values(str_split($rules))) == 1) { // all rules is the same command, i.e. iii or pp
            $command = $rules[0];
            $count = strlen($rules);
            if ($command == 'p') {
                return $this->saction_ProduceBonus($card_id, $color, $count);
            }
            if ($command == 'i') {
                $player_id = $this->getPlayerIdByColor($color);
                $this->saction_GainInfluenceTokens($player_id, $count, $card_id);
                return true;
            }
        }
        return false;
    }

    function saction_RemoveCardFromGame($card, $player_id, &$ret_owner_player_id = null)
    {
        // If the removed card was worth some points, lose them
        $owner_color = $this->getCardOwnerColor($card);
        $owner_player_id = $this->getPlayerIdByColor($owner_color);
        if ($owner_player_id !== 0) {
            // move tokens off the card if any
            $tokens = $this->tokens->getTokensInLocation($card);
            foreach ($tokens as $key => $info) {
                $this->dbSetTokenLocation($key, "setaside_$owner_color", CARD_STATE_USED_CARD, '', [
                    'nod' => true,
                    'player_id' => $owner_player_id
                ]);
            }
            $inf = $this->getRulesFor($card, 'v');
            //$this->warn("removing $card $owner_color $owner_player_id $inf");
            if ($inf > 0) {
                $this->dbIncScoreValueAndNotify($owner_player_id, -$inf, clienttranslate('${player_name} loses ${inc} Influence from removing card ${token_name}'), 'game_cards', [
                    'token_id' => $card, 'token_name' => $card, 'place' => $card
                ]);
            }
        }
        $this->dbSetTokenLocation($card, "dev_null", 0, clienttranslate('${player_name} removes ${token_name} from the game'));
        $ret_owner_player_id = $owner_player_id;
    }

    function saction_Settle($card, $color, $planet, &$choices_arr)
    {
        $cost = $this->getRulesFor($planet, 'c');
        if ($cost >= 99) {
            $this->userAssertTrue(self::_('Cannot colonize hostile planet'));
        }
        $player_id = $this->getPlayerIdByColor($color);
        $perm_boost = $this->getSettleBoost($color);
        if (startsWith($card, "x_colonize")) {
            $perm_boost++;
        }

        $colship = $this->playerHasCard('card_tech_E_21', $color, true);
        if ($colship) {
            //$this->warn("colship $colship ".toJson($choices_arr));
            while (count($choices_arr) > 0) {
                $command = $choices_arr[0][0];
                if ($command == 'c') {
                    $params = array_shift($choices_arr);
                    $colony = $params[2];
                    $planet2 = $params[1];
                    $this->saction_Colonize($colony, $color, $planet2);
                } else {
                    break;
                }
            }
        }
        if ($this->isReadyToBeSettled($planet, $perm_boost)) {
            if ($card === "x_follow") {
                $this->notifyWithName('playerLog', clienttranslate('${player_name} follows Colonize ROLE: settle a planet'), [
                    'player_id' => $player_id
                ]);
            }
            $tokens = $this->tokens->getTokensInLocation($planet);
            foreach ($tokens as $key => $info) {
                $this->dbSetTokenLocation($key, "setaside_$color", CARD_STATE_USED_CARD, '', [
                    'nod' => true,
                    'player_id' => $player_id
                ]);
            }
            $this->dbSetTokenLocation($planet, "tableau_$color", 1, clienttranslate('${player_name} settles ${token_name}'), [
                'player_id' => $player_id
            ]);
            $inc = $this->token_types[$planet]['v'];
            $this->dbIncScoreValueAndNotify($player_id, $inc, clienttranslate('${player_name} gains +${inc} Influence from planet ${place_name}'), 'game_planets', [
                'place_id' => $planet, 'place_name' => $planet, 'place' => $planet
            ]);
            // triggered
            $this->saction_TriggerAbilities("settle", $color, $planet);
            $this->saction_TriggerAbilities("enter", $color, $planet);
            return true;
        } else {
            return false;
        }
    }

    function saction_Colonize($card, $color, $planet)
    {

        $cost = $this->getRulesFor($planet, 'c');
        if ($cost >= 99) {
            $this->userAssertTrue(self::_('Cannot colonize hostile planet'));
        }
        $player_id = $this->getPlayerIdByColor($color);
        $sym = $this->getIconCount('C', $card, '*');

        //$this->warn("colonize $card, $color, $planet");
        $this->dbSetTokenLocation(
            $card,
            $planet,
            RESOURCE_STATE_COLONIZE_BOOST,
            clienttranslate('${player_name} adds +${num} colony (card ${token_name}) to ${place_name}'),
            [
                'player_id' => $player_id, 'num' => $sym,
                'log_others' => clienttranslate('${player_name} adds +${num} Colony (card ${token_name}) to an unknown planet')
            ]
        );
    }

    function saction_Trade($card, $color, $planet, $token)
    {
        $player_id = $this->getPlayerIdByColor($color);
        $this->systemAssertTrue("invalid param 1", $planet);
        $this->systemAssertTrue("invalid param 2", $token);
        $weaponEmporium = $this->playerHasCard('card_tech_2_74', $color);
        $diverseMarkets = $this->playerHasCard('card_tech_2_71', $color);
        $specialization = $this->playerHasCard('card_tech_2_73', $color);

        $info = $this->tokens->getTokenInfo($token);
        if ($info['state'] == RESOURCE_STATE_COLONIZE_BOOST || $info['state'] == RESOURCE_STATE_MARKER) {
            // this is used as boost not to be traded
            $this->userAssertTrue(self::_("Cannot trade this resource, it is used as marker or boost"));
        }
        $restype = '';
        if (startsWith($token, 'fighter')) {
            $is_f = startsWith($token, 'fighter_F') || startsWith($token, 'fighter_j');
            $this->userAssertTrue(_('Can only trade Fighters with Weapon Emporium'),  $is_f && $weaponEmporium);
            $this->dbSetTokenLocation($token, "stock_fighter", 0, clienttranslate('${player_name} trades ${token_name}'), [
                'player_id' => $player_id
            ]);
        } else if (startsWith($planet, 'card_tech') && $token == 'x') {
            // resource boost
            $this->saction_PlayCardsToDiscard($player_id, [$planet], clienttranslate('${player_name} trades ${token_name} as resource'));
            $restype = $this->getRulesFor($planet, 'asr');
        } else {
            $this->systemAssertTrue("bad parent $token", $info['location'] == $planet);
            $this->dbSetTokenLocation($token, 'stock_resource', 0, clienttranslate('${player_name} trades ${token_name}'), [
                'player_id' => $player_id
            ]);
            $restype = getPart($token, 1);
        }
        if ($specialization) {
            $place = $specialization;
            $resource = $this->tokens->getTokenOnLocation($place);
            $resourceType = getPart($resource['key'], 1);
            if ($restype == $resourceType || $restype === 'n') {
                $this->saction_GainInfluenceTokens($player_id, 1, $place);
            }
        }
        if ($diverseMarkets) {
            // summon resource counter
            if ($restype) { // can be fighter
                $resourceInfo = $this->dbGetFreeResourceInfo($restype);
                $this->dbSetTokensLocation([$resourceInfo], $diverseMarkets, RESOURCE_STATE_MARKER, '');
            }
        }

        $this->saction_GainInfluenceTokens($player_id, 1, $planet);
    }

    function saction_Produce($card, $color, $planet, $restype, $resid)
    {
        if ($planet == 'x_random') {
            $this->saction_ProduceRandom($color);
            return;
        }
        $player_id = $this->getPlayerIdByColor($color);
        $this->systemAssertTrue("invalid param 1", $planet);
        $this->systemAssertTrue("invalid param 2", $restype);
        if ($resid === 'x') {
            $resourceInfo = $this->dbGetFreeResourceInfo($restype);
            $token = $resourceInfo['key'];
        } else {
            $token = $resid;
            $restype = getPart($token, 1);
            $loc = $this->tokens->getTokenLocation($resid);
            if ($loc !== 'stock_resource') {
                $resourceInfo = $this->dbGetFreeResourceInfo($restype);
                $token = $resourceInfo['key'];
            }
            //$this->systemAssertTrue("invalid resource token $resid $loc", $loc === 'stock_resource');
        }
        $planets = $this->arg_holders($color);
        $info = array_value_get($planets, $planet);
        $this->systemAssertTrue("planet has not resource slots '$planet' " . toJson($planets, null), $info);
        //$this->warn("producing on $planet for $restype $resid $token ".toJson($info));
        $this->systemAssertTrue("No more free slots on this planet $planet for $restype $resid $token " . toJson($info), array_value_get($info['resf'], $restype) > 0 || array_value_get($info['resf'], 'n') > 0);
        $this->userAssertTrue(clienttranslate("No more free slots on this planet"), array_value_get($info['resf'], $restype) > 0 || array_value_get($info['resf'], 'n') > 0);
        $this->dbSetTokenLocation($token, $planet, RESOURCE_STATE_PRODUCE, clienttranslate('${player_name} produces ${token_name} on ${place_name}'), [
            'player_id' => $player_id
        ]);
        // Genetic Engineering & Specialized Production
        // add resource marker
        $geneticEngineering = $this->playerHasCard('card_tech_2_92', $color);
        if ($geneticEngineering)
            $this->dbSetTokensLocation([$this->dbGetFreeResourceInfo($restype)], $geneticEngineering, RESOURCE_STATE_MARKER, '');
        $specProd = $this->playerHasCard('card_tech_E_18', $color);
        if ($specProd) {
            $place = $specProd;
            $resource = $this->tokens->getTokenOnLocation($place);
            $resourceTypeSelected = getPart($resource['key'], 1);
            if ($restype == $resourceTypeSelected) {
                $this->saction_GainInfluenceTokens($player_id, 1, $place);
            }
        }
    }

    /**
     * Produce bonus is triggered by $card, produce automatically if not possible mark produce for follow up
     *
     * @param string $card
     * @param string $color
     * @param number $max
     */
    function saction_ProduceBonus($card, $color, $max)
    {
        $planets = $this->arg_holders($color);
        $count = 0;
        $hasany = false;
        foreach ($planets as $planet => $info) {
            $freeresslots = $info['resf'];
            foreach ($freeresslots as $c => $v) {
                if ($v > 0) {
                    $count += $v;
                    if ($c == 'n') // cannot auto-produce on Any
                        $hasany = true;
                }
            }
        }
        if ($count > $max || $hasany) {
            return false;
        }
        $count = 0;
        // produce on  random planets
        $produced = true;
        while ($count < $max && $produced) {
            $produced = $this->saction_ProduceRandom($color);
            if ($produced) {
                $count++;
            }
        }
        return true;
    }

    function saction_ProduceRandom($color)
    {
        $planets = $this->arg_holders($color);
        shuffle_assoc($planets);
        //$this->systemAssertTrue(print_r($planets, true));
        $allres = ['w', 'f', 's', 'i'];
        foreach ($planets as $planet => $info) {
            $freeresslots = $info['resf'];
            $restypes = [];
            foreach ($freeresslots as $c => $v) {
                if ($v > 0) {
                    if ($c != 'n')
                        $restypes[] = $c;
                    else {
                        shuffle($allres);
                        $restypes[] = $allres[0];
                    }
                }
            }
            if (count($restypes) > 0) {
                shuffle($restypes);
                $this->saction_Produce('x', $color, $planet, $restypes[0], 'x');
                return true;
            }
        }
        return false;
    }

    function saction_Action($card, $color, &$choices, $actnum = 0)
    {
        $this->systemAssertTrue("bad args no choices", is_array($choices));
        $player_id = $this->getPlayerIdByColor($color);
        $rules = $this->getRulesFor($card, 'a');
        $this->systemAssertTrue("action has no rules $card", $rules && $rules != '*');
        $card_state = $this->tokens->getTokenState($card);
        $this->systemAssertTrue("$card is not in play", $card_state !== null);
        $notif = clienttranslate('${player_name} plays action of ${token_name}');
        $this->notifyWithName('playerLog', $notif, [
            'player_id' => $player_id, 'token_id' => $card,
            'token_name' => $card,
        ]);
        $partial = (strstr($rules, ".") !== false);
        if ($partial) {
            $rules2 = $this->getRulesByNum($card, CARD_RULE_ACTION_PART2);
            if ($card_state == CARD_STATE_PARTIAL_ACTION) {
                $rules = $rules2;
            }
        }

        $disposable = true;
        if ($this->isPermanentTech($card))
            $disposable = false;
        else if ($this->isPlanet($card))
            $disposable = false;
        if ($disposable) {
            $state = CARD_STATE_USED_CARD;
            if ($this->getRulesFor($card, 'lasting')) {
                $state = CARD_STATE_TURN_LASTING;
            }
            if ($partial) {
                if ($rules2 && $card_state != CARD_STATE_PARTIAL_ACTION)
                    $state = CARD_STATE_PARTIAL_ACTION; // partial play
            }
            $this->saction_PlayCardsToAside($player_id, [$card], '', $state);
        } else {
            $state = CARD_STATE_PERMANENT;
            if ($partial) {
                if ($rules2 && $card_state != CARD_STATE_PARTIAL_ACTION)
                    $state = CARD_STATE_PARTIAL_ACTION; // partial play
                $this->dbSetTokenState($card, $state, '');
            }
        }
        if ($state != CARD_STATE_PARTIAL_ACTION)
            $this->incGameStateValue('action_played', 1);
        // hardcoded rules
        if ($card == 'card_tech_E_11') { //INDUSTRIAL ESPIONAGE
            $this->saction_IndustrialEspionage($player_id, $card);
            return;
        }
        if ($card === 'card_tech_2_93') { // terraforming
            $params = array_shift($choices);
            $planet = $params[1];
            $this->saction_Colonize($card, $color, $planet);
            $this->saction_Settle($card, $color, $planet, $choices);
            return;
        }
        // generic rules
        $this->saction_ExecuteRules($card, $color, $choices, $rules, true);
        $this->saction_ValidateRestOfChoices($choices, $rules);
    }




    function saction_ExecuteRules($card, $color, &$choices_arr, $rules, $action)
    {
        if ($card === null)
            $card = "x_auto";
        //    $this->warn("rules $card $color $rules, $action".toJson($choices_arr));
        $this->systemAssertTrue("bad args no choices", is_array($choices_arr));
        $player_id = $this->getPlayerIdByColor($color);
        $pay = false;
        if (strstr($rules, ">") !== false) {
            $pay = true;
        }
        $orig_rules = $rules;
        $i = 0;
        $accomulator = [];
        while (count($choices_arr) > 0 && $rules) {
            $params = array_shift($choices_arr);
            if (!is_array($params))
                $this->systemAssertTrue("bad operation not array $params");
            $command = array_shift($params);
            $lookup = '';
            if (count($choices_arr) > 0) {
                $lookup = $choices_arr[0][0];
            }
            $consume = $command;
            $replace = "";
            if (startsWith($rules, "%")) {
                $what = $rules[1];
                $to = $rules[2];
                $finfo = $this->dbGetOwnFighterInfo($color, false);
                $num = count($finfo[$what]);
                $rules = str_repeat($to, $num) . substr($rules, 3);
            }
            // $this->warn("command $command ".toJson($params, null));
            switch ($command) {
                case 'z':
                    $pay = false;
                    $consume = ">";
                    break;
                case 'Z':
                    $pay = true;
                    $consume = "+";
                    $replace = $orig_rules;
                    break;
                case 'd': // draw a card
                    $this->saction_Draw($player_id);
                    break;
                case 'u': // draw a planet
                    $place_id = "supply_planets"; //getTokensOnTop
                    $picked_arr = $this->tokens->pickTokensForLocation(1, $place_id, "hand_$color");
                    $count = count($picked_arr);
                    if ($count) {
                        $picked = array_shift($picked_arr);
                        $token_id = $picked['key'];
                        $this->dbSetTokenLocation($token_id, "tableau_$color", 0, clienttranslate('${player_name} draws a planet'));
                        $this->revealPlanetSupplyTop();
                    } else {
                        $this->userAssertTrue(self::_("No more planets left"));
                    }
                    break;
                case 'l': // polictics or AI
                    if (startsWith($card, 'card_role_politics')) {
                        $this->saction_RemoveCardFromGame($card, $player_id);
                    }
                    $role_card = $params[0];
                    $this->dbSetTokenLocation($role_card, "hand_$color", 0, clienttranslate('${player_name} gains ${token_name} into hand'));
                    break;
                case 'e': // remove
                    $arg_card = $params[0];
                    $this->saction_RemoveCardFromGame($arg_card, $player_id);
                    break;
                case 'E':
                    $arg_card = $params[0];
                    $this->saction_RemoveCardFromGame($arg_card, $player_id);
                    if ($lookup === 'E') {
                        $consume = '';
                    }
                    break;
                case 's':
                    $planet = $params[0];
                    if ($this->saction_Settle($card, $color, $planet, $choices_arr) == false) {
                        $command = 'c';
                        $colony = $card;
                        $this->saction_Colonize($colony, $color, $planet);
                    }
                    break;
                case 'S':
                    $planet = $params[0];
                    if ($planet === 'skip')
                        break;
                    if ($this->saction_Settle($card, $color, $planet, $choices_arr) == false) {
                        $this->userAssertTrue(self::_("Planet does not have enough colonies"));
                    }
                    break;
                case 'c':
                    $planet = $params[0];
                    $colony = $params[1];
                    $this->saction_Colonize($colony, $color, $planet);
                    if ($rules[0] == 's')
                        $consume = 's';
                    break;
                case 'a':
                    $planet = array_shift($params);
                    $this->saction_Attack($color, $planet, $choices_arr, $card);
                    break;
                case 'p': // produce
                    $this->systemAssertTrue("invalid params " . count($params), count($params) == 3);
                    $this->saction_Produce($card, $color, $params[0], $params[1], $params[2]);
                    break;
                case 't': // trade
                    $this->systemAssertTrue("invalid params " . count($params), count($params) == 2);
                    $this->saction_Trade($card, $color, $params[0], $params[1]);
                    break;
                case 'F':
                case 'D':
                case 'B':
                    if ($pay) {
                        $token = $params[0];
                        if (startsWith($token, 'card')) {
                            $boost = $this->getIconCount($command, $token);
                            $this->userAssertTrue(self::_('Invalid payment'), $boost > 0);
                            $this->saction_PlayCardsToDiscard($player_id, [$token], clienttranslate('${player_name} discards ${token_name} as ship payment'));
                            for ($b = 1; $b < $boost; $b++) {
                                $this->consumeOperation($rules, $consume, $replace);
                            }
                        } else {
                            $accomulator[] = $token;
                        }
                        if (count($accomulator) > 0) {
                            if ($lookup !== $command) {
                                $this->dbSetTokensLocation($accomulator, "stock_fighter", 0, clienttranslate('${player_name} pays ${token_div_count}'), [
                                    'player_id' => $player_id
                                ]);
                                $accomulator = [];
                            }
                        }
                    } else {
                        if ($command == 'B') {
                            // only one battlecruiser allowed
                            $b = $this->tokens->getTokenOfTypeInLocation("fighter_B", "tableau_$color");
                            $this->userAssertTrue(self::_('You can only have one Battlecruiser'), !$b);
                        }
                        $resourceInfo = $this->dbGetFreeFighterInfo($command);
                        $accomulator[] = $resourceInfo;
                        if ($lookup !== $command) {
                            $this->dbSetTokensLocation($accomulator, "tableau_$color", 1, clienttranslate('${player_name} gains ${token_div_count}'), [
                                'player_id' => $player_id
                            ]);
                            $accomulator = [];
                        } else {
                            // silent move to change db state, otherwise we will get same fighter
                            $this->tokens->moveToken($resourceInfo['key'], "tableau_$color", 1);
                        }
                    }
                    break;
                case 'y': // choose resource type
                    $resourcetype = $params[0];
                    $resourceInfo = $this->dbGetFreeResourceInfo($resourcetype);
                    $token = $resourceInfo['key'];
                    $this->dbSetTokenLocation($token, $card, RESOURCE_STATE_MARKER, clienttranslate('${player_name} chooses ${token_name} TYPE'));
                    break;
                case 'i':
                    $this->saction_GainInfluenceTokens($player_id, 1, $card);
                    $this->notifyAnimate();
                    break;

                case 'K':
                    // recon deck
                    $mydeck = "deck_${color}";
                    $card = array_shift($params);
                    if ($this->tokens->countTokensInLocation($mydeck) == 0) {
                        $this->tokens->reformDeckFromDiscard($mydeck);
                    }
                    $info = $this->tokens->getTokenInfo($card);
                    $this->systemAssertTrue("recon card is not in the deck $card", $info['location'] === $mydeck);
                    $this->tokens->insertTokenOnExtremePosition($card, $mydeck, true);
                    break;
                case 'L':
                    // recon planet deck
                    $card = array_shift($params);
                    $this->tokens->insertTokenOnExtremePosition($card, "supply_planets", true);
                    $this->revealPlanetSupplyTop();
                    break;
                case 'x':
                    // no-op except discard action card
                    $consume = $rules[0];
                    break;
                case 'R': // get tech card
                    if ($pay) {
                        break;
                    }
                    $tech = array_shift($params);
                    if ($tech === 'skip')
                        break;
                    if (startsWith($tech, 'card_fleet_b'))
                        $tech = preg_replace("/_b_/", "_i_", $tech);
                    if ($this->saction_PayForTech($tech, $color, 0, $choices_arr))
                        $this->saction_BuyTech($tech, $color);
                    break;
                case 'T': // get tech card
                    if ($pay) {
                        break;
                    }
                    $tech = array_shift($params);
                    if ($tech === 'skip')
                        break;
                    $this->saction_BuyTech($tech, $color);
                    break;
                case 'o':
                    $extra = 1;
                    if ($lookup === $command) {
                        $extra = 2;
                        $params = array_shift($choices_arr);
                    }
                    $this->incGameStateValue('actions_allowed', $extra + 0);
                    $allowed = $this->getGameStateValue('actions_allowed');
                    $played = $this->getGameStateValue('action_played');
                    $this->notifyWithName('playerLog', clienttranslate('${player_name} can play ${extra} more actions, total remaining ${total}'), [
                        'extra' => $extra, 'total' => ($allowed - $played)
                    ]);
                    break;
                case 'O':
                    $this->incGameStateValue('role_phases_allowed', 1);
                    $this->notifyWithName('playerLog', clienttranslate('${player_name} can play extra role phase this turn'), [
                        'player_id' => $player_id
                    ]);
                    break;
                case 'q':
                    $card = array_shift($params);
                    $this->systemAssertTrue("bad card for pick $card", $this->tokens->getTokenInfo($card)['location'] === "hand_$color");
                    $this->dbSetTokenLocation($card, "tableau_$color", PLANET_UNSETTLED, clienttranslate('You picked ${token_name}'), [
                        'player_id' => $player_id, 'log_others' => clienttranslate('${player_name} picked a planet')
                    ]);
                    break;
                case 'Q':
                    $tokens = $this->tokens->getTokensOfTypeInLocation("card_planet", "hand_$color");
                    $this->dbSetTokensLocation($tokens, "discard_planets", 1, ''); //discard face up
                    break;
                case 'g':
                    $tech = array_shift($params);
                    $owner_id = 0;
                    $this->saction_RemoveCardFromGame($tech, $player_id, $owner_id);
                    if ($owner_id !== 0) {
                        $owner_color = $this->getPlayerColor($owner_id);
                        $incplace = null;
                        if (startsWith($tech, 'card_fleet_i')) {
                            // that was improved fleet, we need to rever it
                            $no = $this->getPlayerPosition($owner_id);
                            $newcard = "card_fleet_b_${no}";
                            $this->dbSetTokenLocation($newcard, "tableau_$owner_color", 2, clienttranslate('${player_name} gets ${token_name}'), [
                                'player_id' => $owner_id
                            ]);
                            $incplace = $newcard;
                            // marker
                            $total = count($this->tokens->getTokensOfTypeInLocation('vp'));
                            $total++;
                            $maker = $this->tokens->createToken("vp_x_$total", "stock_vp");
                            $this->dbSetTokenLocation($maker, $newcard, RESOURCE_STATE_COLONIZE_BOOST, clienttranslate('${player_name} marks ${token_name}'), [
                                'player_id' => $owner_id
                            ]);
                        }
                        $this->saction_Reparations($owner_id, 2, $incplace);
                    }
                    break;
                default:
                    $this->systemAssertTrue("not supported $command: " . toJson($choices_arr), false);
                    break;
            }
            if ($consume) {
                $before = $rules;
                $res = $this->consumeOperation($rules, $consume, $replace);
                $this->systemAssertTrue("No matching action $consume ($command) to [$before] full rules [$orig_rules]", $res);
            }
            $i++;
        }
    }

    function saction_ValidateRestOfChoices(&$choices_arr, $orig_rules = '')
    {
        while (count($choices_arr) > 0) {
            $rest = toJson($choices_arr);
            $params = array_shift($choices_arr);
            if (!is_array($params))
                $this->systemAssertTrue("bad operation not array $params");
            $command = array_shift($params);
            switch ($command) {
                case 'z': // end marker
                case 'x': // no-op marker
                case 'R': // research unused
                case 'E': // remove any card optional
                case 'e': // remove any card optional
                    break;
                default:
                    $this->systemAssertTrue("No matching action for $rest full rules [$orig_rules]");
            }
        }
    }

    function saction_PlayCardsToDiscard($player_id, $cards, $notif = '*')
    {
        if ($notif == '*')
            $notif = clienttranslate('${player_name} discards ${token_names}');
        $this->saction_PlayCardsToPlayerZone($player_id, $cards, 'discard', CARD_STATE_FACEUP, $notif);
    }

    function saction_PlayCardsToAside($player_id, $cards, $notif, $state = CARD_STATE_USED_CARD)
    {
        if ($notif == '*')
            $notif = clienttranslate('${player_name} plays ${token_names}');
        $this->saction_PlayCardsToPlayerZone($player_id, $cards, 'setaside', $state, $notif);
    }

    function saction_PlayCardsToPlayerZone($player_id, $cards, $zone, $state = null, $notif = '*')
    {
        if ($this->tokens->checkListOrTokenArray($cards) == 0)
            return;
        $color = $this->getPlayerColor($player_id);
        if ($notif == '*')
            $notif = clienttranslate('${player_name} plays ${token_names} into ${place_name}');
        $target = "${zone}_$color";
        $plus = count($cards) > 1;
        $this->dbSetTokensLocation($cards, $target, $state, $notif, ['player_id' => $player_id, 'nod' => $plus]);
        if ($plus || $zone == 'discard') // discard require extra time because it moves + fades
            $this->notifyAnimate();
    }

    function saction_AutoReplenish($card_id, $color, $info = null)
    {
        $rule = $this->getRulesFor($card_id, 'rslots');
        if ($rule) {
            $player_id = $this->getPlayerIdByColor($color);
            if ($info && $this->isPlanet($card_id) && array_value_get($info, 'state') == 0)
                return;
            $tokens = $this->tokens->getTokensOfTypeInLocation(null, $card_id);
            foreach ($tokens as $key => $info2) {
                $restype = getPart($key, 1); // i.e F,B
                $rule = preg_replace("/$restype/", "", $rule, 1);
            }
            if ($rule) { // anything left untaken?
                for ($i = 0; $i < strlen($rule); $i++) {
                    $command = $rule[$i];
                    $resourceInfo = $this->dbGetFreeFighterInfo($command);
                    $this->dbSetTokenLocation(
                        $resourceInfo['key'],
                        $card_id,
                        $player_id,
                        clienttranslate('game replenishes slot of ${place_name} with ${token_name}')
                    );
                }
            }
        }
    }

    function saction_TurnReplenish($player_id)
    {
        $color = $this->getPlayerColor($player_id);
        // replenish regeneratable slots and init turn
        $tokens = $this->tokens->getTokensOfTypeInLocation("card", "tableau_${color}");
        foreach ($tokens as $key => $info) {
            $this->saction_AutoReplenish($key, $color, $info);
        }


        $this->setGameStateValue('leader', $player_id);
        $this->setGameStateValue('action_played', 0);
        $this->setGameStateValue('followers', 0);
        $this->setGameStateValue('followers_waiting', 0);
        $this->setGameStateValue('role', -1);
        $this->setGameStateValue('boost_rem', 0);
        $this->setGameStateValue('actions_allowed', 1);
        if ($this->playerHasCard('card_tech_3_87', $color)) { //Productivity
            $this->incGameStateValue('actions_allowed', 1);
        }
        $this->setGameStateValue('role_phases_allowed', 1);
        $this->setGameStateValue('role_phases_played', 0);
        $this->setGameStateValue('discard_played', 0);
        // replenish 
        // XXX
    }

    function saction_IndustrialEspionage($player_id, $card)
    {
        $this->systemAssertTrue("incorrect card $card for special action", $card == 'card_tech_E_11');
        $players = $this->loadPlayersBasicInfos();
        foreach ($players as $player_id_o => $player_info) {
            if ($player_id_o != $player_id) {
                $vps = $this->dbGetOwnVpInfo($player_info['player_color']);
                $vp = array_shift($vps);
                if ($vp) {
                    $this->dbIncScoreValueAndNotify($player_id, -1, '', 'game_vptrade', ['player_id' => $player_id]);
                    $this->dbSetTokensLocation([$vp], 'stock_vp', 0, clienttranslate('${player_name} returns ${token_div_count} to supply'), [
                        'player_id' => $player_id_o
                    ]);
                    $color = $this->getPlayerColor($player_id_o);
                    $score = count($this->tokens->getTokensOfTypeInLocation('vp', "tableau_$color"));
                    $this->notifyCounterDirect("vp_${color}_counter", $score, '', ['place' => $card, 'inc' => -1]);
                    $this->notifyCounter("stock_vp");
                }
            }
        }
        $this->saction_GainInfluenceTokens($player_id, 1, $card);
        $this->notifyAnimate();
    }

    function saction_Reparations($player_id, $inc = 2, $place = null)
    {
        $color = $this->getPlayerColor($player_id);
        if ($place == null)
            $place = "tableau_$color";
        $this->dbIncScoreValueAndNotify($player_id, $inc, '', 'game_reparations', ['place' => $place, 'noa' => true]);
        $vp_tokens = $this->dbGetFreeVpTokens($inc);
        $this->dbSetTokensLocation($vp_tokens, "tableau_$color", 1, clienttranslate('${player_name} gains ${token_div_count} from ${place_from} as REPARATIONS'), [
            'player_id' => $player_id, 'place_from' => $place, 'noa' => true
        ]);
        $score = count($this->tokens->getTokensOfTypeInLocation('vp', "tableau_$color"));
        $this->notifyCounterDirect("vp_${color}_counter", $score, '', ['place' => $place, 'inc' => $inc]);
        $this->notifyCounter("stock_vp");
    }


    function saction_GainInfluenceTokens($player_id, $inc, $place)
    {
        $color = $this->getPlayerColor($player_id);
        if (startsWith($place, "x")) {
            $place = "tableau_$color";
        }
        $this->dbIncScoreValueAndNotify($player_id, $inc, '', 'game_vptrade', ['place_name' => $place]);
        $vp_tokens = $this->dbGetFreeVpTokens($inc);
        $this->dbSetTokensLocation($vp_tokens, "tableau_$color", 1, clienttranslate('${player_name} gains ${token_div_count} from ${place_from}'), [
            'player_id' => $player_id, 'place_from' => $place
        ]);
        $score = count($this->tokens->getTokensOfTypeInLocation('vp', "tableau_$color"));
        $this->notifyCounterDirect("vp_${color}_counter", $score, '', ['place' => $place, 'inc' => $inc]);
        $this->notifyCounter("stock_vp");
    }

    function query_revealContents($place)
    {
        // self::checkAction('showDiscard');
        // no action restrictions 
        $player_id = $this->getCurrentPlayerId();
        $this->systemAssertTrue("Cannot reveal this location $place for $player_id", $this->isContentAllowedForLocation($player_id, $place) || $place == 'supply_planets');
        $res = $this->tokens->getTokensOfTypeInLocation('card', $place);
        return shuffle_assoc($res);
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state arguments
    ////////////
    /*
     * Here, you can create methods defined as "game state arguments" (see "args" property in states.inc.php).
     * These methods function is to return some additional information that is specific to the current
     * game state.
     */
    function arg_playerTurnAction()
    {
        $player_id = $this->getActivePlayerId();
        $color = $this->getPlayerColor($player_id);
        $res = $this->arg_playerTurnExtra($player_id);
        $res += $this->arg_playerTurnRole();
        // Logistics, untapped
        $res['can_play_role_first'] = $this->playerHasCard('card_tech_3_86', $color);
        return $res + [
            'action_played' => $this->getGameStateValue('action_played'),
            'actions_allowed' => $this->getGameStateValue('actions_allowed'),
        ];
    }

    function arg_playerTurnRole()
    {
        $player_id = $this->getActivePlayerId();

        $pinfo = [];
        $pinfo[$player_id] = $this->arg_playerTurnInfo($player_id, 'l-.');
        $triggered = $this->isEndOfGameTriggered();
        $extra_played = $this->getGameStateValue('extra_turn_at_end') == 1;
        $res = array(
            'pinfo' => $pinfo, 'end_of_game' => $triggered, 'extra_turn' => $extra_played,
            'role_phases_played' => $this->getGameStateValue('role_phases_played'),
            'role_phases_allowed' => $this->getGameStateValue('role_phases_allowed'),
            'leader' => $player_id,
        );

        $res = array_merge($res, $pinfo[$player_id]);
        return $res;
    }

    function arg_playerTurnFollow()
    {
        $role_index = $this->getGameStateValue('role');
        $role = $this->materials['role_icons'][$role_index];
        $role_name = $this->materials['icons'][$role];
        $leader = $this->getGameStateValue('leader');
        $active_follower = $this->getGameStateValue('active_follower');
        $pinfo = [];
        $players = $this->loadPlayersBasicInfos();
        foreach ($players as $player_id_o => $player_info) {
            // that should have been private...
            $pinfo[$player_id_o] = $this->arg_playerTurnInfo($player_id_o, "f-" . $role);
            $canFollow = $this->canFollowOnInfo($pinfo[$player_id_o], $role, $player_id_o);
            $pinfo[$player_id_o]['can_follow'] = $canFollow;
        }
        $leader_name = $players[$leader]['player_name'];
        $triggered = $this->isEndOfGameTriggered();
        $extra_played = $this->getGameStateValue('extra_turn_at_end') == 1;
        $res = array(
            'pinfo' => $pinfo, 'leader' => $leader, 'otherplayer_id' => $leader, 'otherplayer' => $leader_name,
            'role' => $role, 'active_follower' => $active_follower, 'role_name' => $role_name,
            'i18n' => ['role_name'], 'end_of_game' => $triggered, 'extra_turn' => $extra_played,
        );
        return $res;
    }

    function arg_playerTurnInfo($player_id, $event = '')
    {
        $color = $this->getPlayerColor($player_id);
        $settle = $this->arg_planets($color);
        $holdres = $this->arg_holders($color);
        $res = array(
            'planets' => $settle, 'holders' => $holdres, 'tech' => $this->arg_research($color),
            'boost_rem' => $this->getGameStateValue('boost_rem'),
            'fdiscount' => $this->arg_attackDiscount($color)

        );
        $icons = $this->materials['role_icons'];
        $ii = $this->arg_iconsInfo($color, $icons, $event);
        $res = array_merge($res, $ii);
        return $res;
    }

    function arg_playerTurnRoleExtra($player_id = -1)
    {
        if ($player_id == -1)
            $player_id = $this->getActivePlayerId();
        $res = $this->arg_playerTurnExtra($player_id);
        if ($res['enabled']) {
            $pinfo = $this->arg_playerTurnInfo($player_id);
            return $res + $pinfo;
        }
        return $res;
    }

    function arg_attackDiscount($color)
    {
        $fdiscount = 0;
        $b = $this->tokens->getTokenOfTypeInLocation("fighter_B", "tableau_$color");
        if ($b) { // battlecruiser in play
            $fdiscount += 1;
            $imp_fleet = $this->tokens->getTokenOfTypeInLocation("card_fleet_i", "tableau_$color");
            if ($imp_fleet)
                $fdiscount += 1;
        }
        $scorchedEarthPolicy = $this->playerHasCard('card_tech_2_85', $color);
        if ($scorchedEarthPolicy) {
            $fdiscount += 2;
        }
        return $fdiscount;
    }

    function arg_playerTurnExtra($player_id = -1)
    {
        if ($player_id == -1)
            $player_id = $this->getActivePlayerId();
        $color = $this->getPlayerColor($player_id);
        $cards = $this->tokens->getTokensOfTypeInLocation("card", "setaside_$color", CARD_STATE_PARTIAL_ACTION) // 
            + $this->tokens->getTokensOfTypeInLocation("card", "tableau_$color", CARD_STATE_PARTIAL_ACTION);
        if (count($cards) > 0) {
            $data = ['enabled' => true];
            $data['state_prompt'] = ('must resolve rest of the action');
            $data['i18n'] = ['state_prompt'];
            $card = array_shift($cards);
            $data['card'] = $card['key']; // unfinished action
            $data['rules'] = $this->getRulesByNum($card['key'], CARD_RULE_ACTION_PART2);
            return $data;
        }
        $pending_info = $this->peekAbility();
        $planets = $this->tokens->getTokensOfTypeInLocation("card_planet", "hand_$color");
        if ($pending_info && $pending_info['card'] === 'card_tech_E_24') { // thur sur XXX very ugly
            // skip planet pick
        } else {
            if (count($planets) > 0) {
                $data = ['enabled' => true, 'survey' => true];
                $data['state_prompt'] = clienttranslate('must choose a planet to keep');
                $data['i18n'] = ['state_prompt'];
                $data['card'] = "survey";
                $data['rules'] = "qQ";
                return $data;
            }
        }
        //$triginfo = [ 'card' => $card_id,'num' => $num,'rules' => $rules,'actor_color' => $color ];

        if ($pending_info) {
            if ($pending_info['actor_color'] === $color) {
                // for now it must be active player
                $data = ['enabled' => true, 'extra' => true];
                $data['state_prompt'] = clienttranslate('must resolve triggered ability');
                $data['rules'] = $pending_info['rules'];
                $data['card'] = $pending_info['card'];
                $data['i18n'] = ['state_prompt'];
                return $data;
            } else {
                $this->error("out of turn trigger for $color " . toJson($pending_info));
            }
        }
        return ['state_prompt' => '', 'enabled' => false];
    }



    function arg_iconsInfo($color, $icons, $event = '')
    {
        $res = [];
        if (is_string($icons))
            $icons = str_split($icons);
        $tokens = $this->tokens->getTokensOfTypeInLocation("card", "tableau_${color}", 1);
        $cardsInHand = $this->tokens->getTokensOfTypeInLocation("card", "hand_${color}");
        if (!$event) {
            $player_id = $this->getPlayerIdByColor($color);
            $leader_id = $this->getGameStateValue('leader');
            if ($player_id != $leader_id) { // follow 
                $event = "f-.";
            } else {
                $event = "l-.";
            }
        }
        $hsize = 5;
        foreach ($tokens as $card => $info) {
            $handplus = $this->getRulesFor($card, 'h');
            if ($handplus)
                $hsize += $handplus;
        }
        $res['hand_size'] = $hsize;
        $bureacracy = false;
        $jokers = [];
        foreach ($icons as $icon) {
            $jokers[$icon] = '';
        }
        $bureacracy = $this->playerHasCard('card_tech_3_97', $color);
        foreach ($tokens as $card => $info) {
            if ($card === 'card_tech_3_76') { //Adaptability
                $this->iconReplacement($jokers, $icons, $event, '[lf]-.:R');
            } else if ($card === 'card_tech_E_31') { //wealth of knowledge
                $this->iconReplacement($jokers, $icons, $event, 'l-.:S');
            } else if ($card === 'card_tech_E_20') { // black market
                $this->iconReplacement($jokers, $icons, $event, '[lf]-.:wfsin');
            } else if ($card === 'card_tech_E_32') { //WARFARE TECHNOLOGY
                $this->iconReplacement($jokers, $icons, $event, '[lf]-R:F');
            }
        }
        $res_tokens = $this->tokens->getTokensOfTypeInLocation("fighter", "tableau_${color}");
        foreach ($tokens as $card => $info) {
            $count = $this->getRulesFor($card, 'slots');
            if ($count) {
                $res_tokens += $this->tokens->getTokensOfTypeInLocation("resource", $card);
            }
            $count = $this->getRulesFor($card, 'rslots');
            if ($count) {
                $res_tokens += $this->tokens->getTokensOfTypeInLocation("fighter", $card, "!=1");
            }
        }


        foreach ($icons as $icon) {
            if ($icon === 'h')
                continue;
            $res['perm_boost'][$icon] = [];
            $res['perm_boost_num'][$icon] = 0;
            $res['hand_boost'][$icon] = [];
            $res['hand_boost_num'][$icon] = 0;
            foreach ($tokens as $card => $info) {
                $count = $this->getIconCount($icon, $card);
                if ($count) {
                    $res['perm_boost'][$icon][$card] = $count;
                }
            }
            if ($bureacracy) {
                if ($this->mtMatchEvent('f-[^C]', $event)) {
                    $res['perm_boost'][$icon][$bureacracy] = 1;
                }
            }
            foreach ($res['perm_boost'][$icon] as $card => $count) {
                $res['perm_boost_num'][$icon] += $count;
            }
            $search = $cardsInHand + $res_tokens;
            foreach ($search as $card => $info) {
                $count = $this->getIconCount($icon, $card, $jokers[$icon]);
                if ($count) {
                    $res['hand_boost'][$icon][$card] = $count;
                    $res['hand_boost_num'][$icon] += $count;
                }
            }
        }
        return $res;
    }

    function arg_research($color, $atech = null, $details = false)
    {
        $res = [];
        $fighters = $this->dbGetOwnFighterInfo($color);
        $tokens = $this->tokens->getTokensOfTypeInLocation("card_planet", "tableau_${color}");
        $own_type = [];
        $ptypes = $this->materials['planet_types'];
        foreach ($ptypes as $type => $name) {
            $own_type[$type] = [];
        }
        foreach ($tokens as $planet => $info) {
            $state = $info['state'];
            if ($state > 0 && $state != CARD_STATE_PLANET_USED_AS_REQ) {
                $type = $this->token_types[$planet]['t'];
                $own_type[$type][] = $planet;
            }
        }
        if ($atech == null) {
            $techs = $this->tokens->getTokensOfTypeInLocation(null, "supply_tech_%");
            $ownfleet = $this->tokens->getTokenOfTypeInLocation('card_fleet', "tableau_${color}");
            if ($ownfleet && getPart($ownfleet['key'], 2) == 'b') {
                $fleet = $this->tokens->getTokensInLocation("supply_fleet");
                if (count($fleet) > 0) {
                    $ifleet = array_shift($fleet);
                    $techs[$ifleet['key']] = $ifleet;
                }
            }
        } else {
            $info = $this->tokens->getTokenInfo($atech);
            $techs[$info['key']] = $info;
        }
        foreach ($techs as $tech => $info) {
            $reqs = $this->getRulesFor($tech, 'p');
            $allfound = [];
            $own_copy = $own_type;
            for ($i = 0; $i < strlen($reqs); $i++) {
                $req = $reqs[$i];
                if ($req == 'N') {
                    $req = $reqs[$i - 1];
                    foreach ($ptypes as $type => $name) {
                        if ($type === $req || $type === 'U')
                            continue;
                        if (count($own_copy[$type]) > 0) {
                            $allfound[] = array_shift($own_copy[$type]);
                            break;
                        }
                    }
                    continue;
                }
                if (count($own_copy[$req]) == 0) {
                    $req = 'U';
                    if (count($own_copy[$req]) > 0) {
                        $allfound[] = array_shift($own_copy[$req]);
                    }
                } else {
                    $allfound[] = array_shift($own_copy[$req]);
                }
            }
            if (count($allfound) < strlen($reqs))
                continue;
            $mrec = $this->mtGetFightCost($tech, 'bm');
            $res[$tech] = [];
            //$res [$tech] ['prereq'] = true;
            if ($details)
                $res[$tech]['pl'] = $allfound;
            $res[$tech]['bm'] = $mrec['cost'] . $mrec['ship_type'];
            $res[$tech]['b'] = $this->getRulesFor($tech, 'b');
            $res[$tech]['canattack'] = false;
            $ftype = $mrec['ship_type'];
            if ($mrec['cost'] > 0 && count($fighters[$ftype]) >= $mrec['cost']) {
                $res[$tech]['canattack'] = true;
            }
        }
        //$this->warn(toJson($res));
        return $res;
    }

    function arg_planets($color)
    {
        $perm_boost = $this->getSettleBoost($color);
        $tokens = $this->tokens->getTokensOfTypeInLocation("card_planet", "tableau_${color}");
        $res = array();
        //$adaptability = $this->playerHasCard('card_tech_3_76', $color);
        foreach ($tokens as $planet => $info) {
            $state = $info['state'];
            $colonies = $this->token_types[$planet]['c'];
            $res[$planet]['settled'] = $state;
            if ($state == 0) {
                $tokens = $this->tokens->getTokensInLocation($planet);
                $count = $this->getIconCount('C', $tokens, '*');
                if ($count + $perm_boost >= $colonies) {
                    // ready
                    $res[$planet]['ready'] = true;
                } else {
                    $res[$planet]['need'] = $colonies - ($count + $perm_boost);
                    // not ready
                }
            }
        }
        $colship = $this->playerHasCard('card_tech_E_21', $color, true);
        if ($colship) {
            $planet = $colship;
            $res[$planet] = [
                'settled' => 0,
                'ready' => false,
                'need' => 1000,
            ];
        }

        return $res;
    }


    function arg_holders($color)
    {
        $res = array();
        $tokens = $this->tokens->getTokensOfTypeInLocation("card_planet", "tableau_${color}", 1) + //
            $this->tokens->getTokensOfTypeInLocation("card_tech", "tableau_${color}", 1);
        $harvest = $this->playerHasCard('scenario_3', $color);
        foreach ($tokens as $planet => $info) {
            $production = $this->token_types[$planet]['slots'] ?? '';
            if (!$production)
                continue;
            $tokens = $this->tokens->getTokensInLocation($planet);
            foreach ($tokens as $rkey => $rinfo) {
                if (startsWith($rkey, 'fighter') && $rinfo['state'] == RESOURCE_STATE_BLOCKER) {
                    continue 2; // production blocked 
                }
            }


            $is_planet = startsWith($planet, 'card_planet');
            if ($is_planet)
                $mod = $harvest ? 2 : 1;
            else
                $mod = 1;
            $count = count($tokens);

            $res[$planet]['resarr'] = [];
            $production_arr = str_split($production);
            foreach ($production_arr as $c) {
                for ($j = 0; $j < $mod; $j++) {
                    $res[$planet]['slotsarr'][$c][] = 0;
                }
                $res[$planet]['resf'][$c] = 0;
            }
            $count = 0;
            foreach ($tokens as $rkey => $rinfo) {
                if (startsWith($rkey, 'abi')) {
                    continue; // triggred marker
                }
                if (startsWith($rkey, 'fighter')) {
                    continue; // replenishing fighter
                }
                $count++;
                $c = $this->getRulesFor($rkey, 'p');
                // occupied slots
                if (!$this->fillSlot($res[$planet]['slotsarr'], $c)) {
                    $this->error("cannot find slot for $c at $planet");
                    continue;
                }
                // resources to trade
                array_value_inc($res[$planet]['resarr'], $c);
            }
            $res[$planet]['resslots'] = strlen($production) * $mod - $count;
            $res[$planet]['produced'] = $count;
            // empty slots
            foreach ($production_arr as $c) {
                $mod = count($res[$planet]['slotsarr'][$c]);
                for ($j = 0; $j < $mod; $j++) {
                    if ($res[$planet]['slotsarr'][$c][$j] === 0) {
                        array_value_inc($res[$planet]['resf'], $c);
                    }
                }
            }
            unset($res[$planet]['slotsarr']);
        }
        return $res;
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state actions
    ////////////
    /*
     * Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
     * The action method of state X is called everytime the current game state is set to X.
     */
    function st_gameTurnNextPlayer()
    {
        $player_id = $this->getGameStateValue('leader');
        $extra_played = false;
        $extra_turn = $this->getGameStateValue('extra_turn_variant') == 2;
        if ($extra_turn) {
            $extra_played = $this->getGameStateValue('extra_turn_at_end');
        }
        $logistics_played = $this->getGameStateValue('logistics_at_end');
        if ($logistics_played) {
            $this->finalScoring();
            $this->gamestate->nextState('last');
            return;
        }
        $next_player_id = $this->getPlayerAfter($player_id);
        if ($this->isEndOfGame($next_player_id)) {
            $logistics = false;
            if (!$extra_turn || $extra_played) {
                $players = $this->loadPlayersBasicInfos();
                foreach ($players as $player_id1 => $player_info) {
                    $color1 = $this->getPlayerColor($player_id1);
                    $logistics = $this->playerHasCard('card_tech_3_86', $color1);
                    if ($logistics) {
                        $this->setGameStateValue('logistics_at_end', 1);
                        $this->notifyWithName('playerLog', clienttranslate('${player_name} activates extra turn at the end of game with Logistics'), [
                            'player_id' => $player_id1
                        ]);
                        $next_player_id = $player_id1;
                        break;
                    }
                }
            }
            if ($extra_turn && !$extra_played) {
                $this->setGameStateValue('extra_turn_at_end', 1);
            } else {
                if (!$logistics) {
                    $this->finalScoring();
                    $this->gamestate->nextState('last');
                    return;
                }
            }
        }
        $this->setNextActivePlayerCustom($next_player_id);
        $this->saction_TurnReplenish($next_player_id);
        $this->gamestate->nextState('next');
    }

    protected function isPlayerZombie($player_id)
    {
        $players = self::loadPlayersBasicInfos();
        return ($players[$player_id]['player_zombie'] == 1);
    }

    function st_gameTurnUpkeep()
    {
        // end of role dispatch
        $this->setGameStateValue('follow_played', 1);
        $player_id = $this->getGameStateValue('leader');
        if ($player_id != $this->getActivePlayerId())
            $this->gamestate->changeActivePlayer($player_id);
        if ($this->isPlayerZombie($player_id)) {
            $this->gamestate->nextState('next');
            return;
        }
        $color = $this->getPlayerColor($player_id);
        // triggered abilities on stack
        $args = $this->arg_playerTurnExtra($player_id);
        if ($args['enabled']) {
            $this->gamestate->nextState('extra');
            return;
        }

        // end of role phase cleanup
        $players = $this->loadPlayersBasicInfos();
        foreach ($players as $player_info) {
            $pcolor = $player_info['player_color'];
            // unmark used planets for all players (for scientific method)
            $tokens = $this->tokens->getTokensOfTypeInLocation("card_planet", "tableau_${pcolor}", CARD_STATE_PLANET_USED_AS_REQ);
            $this->dbSetTokensLocation($tokens, "tableau_${pcolor}", CARD_STATE_PERMANENT, '', ['nod' => true]);
        }




        $discardPlayed = $this->getGameStateValue('discard_played');
        if ($discardPlayed) {
            $this->saction_EndOfTurn($player_id);
            $this->gamestate->nextState('next');
            return;
        }
        $rolePlayed = $this->getGameStateValue('role_phases_played');
        $roleAllowerd = $this->getGameStateValue('role_phases_allowed');
        //$this->debugConsole("roles $rolePlayed $roleAllowerd|");
        if ($rolePlayed < $roleAllowerd) {
            $this->gamestate->nextState('role');
            return;
        }

        $logistics = $this->playerHasCard('card_tech_3_86', $color);
        if ($logistics) {
            $actionPlayed = $this->getGameStateValue('action_played');
            if ($actionPlayed == 0) { // this is good check, if any actions played - action phase was done
                $this->dbSetTokenLocation('card_tech_3_86', "tableau_$color", CARD_STATE_PERMANENT_TAPPED, clienttranslate('${player_name} activates ${token_name}'));
                $this->gamestate->nextState('action');
                return;
            }
        }

        // check if any activatables left
        if ($this->getUntrivialDiscardOrActivate($color)) {
            $this->gamestate->nextState('discard');
            return;
        }

        $this->saction_EndOfTurn($player_id);
        $this->gamestate->nextState('next');
    }

    function getUntrivialDiscardOrActivate($color)
    {
        // check if any activatables left
        $tokens = $this->tokens->getTokensOfTypeInLocation("card_tech", "tableau_${color}"); // XXX maybe include planets?
        foreach ($tokens as $card => $info) {
            if ($this->isPermanentTech($card) && $info['state'] != CARD_STATE_PERMANENT_TAPPED) {
                $rule = $this->getRulesFor($card, 'e');
                if ($rule) {
                    return 1;
                }
            }
        }
        $cards = $this->tokens->getTokensOfTypeInLocation("card", "hand_$color");
        $count = count($cards);
        if ($count > 0) {
            return 2;
        }
        return 0;
    }

    function st_selectScenario()
    {
        $scenarios = $this->isScenarioVariant();
        if (!$scenarios) {
            $this->gamestate->nextState('last');
            return;
        }

        $selectScenarioVariant = $this->isScenarioSelection();
        if (!$selectScenarioVariant) {
            $this->tokens->shuffle("scenarios");
            $players = $this->loadPlayersBasicInfos();
            foreach ($players as $player_info) {
                $color = $player_info['player_color'];
                $this->tokens->pickTokensForLocation(1, "scenarios", "tableau_$color", 1);
            }
            $this->gamestate->nextState('last');
        }
    }

    function st_scenarioNextPlayer()
    {
        $player_id = $this->getActivePlayerId();
        $next_player_id = $this->getPlayerAfter($player_id);
        if ($next_player_id === $this->getFirstPlayer()) {
            $this->gamestate->nextState('last');
        } else {
            $this->activeNextPlayer();
            $this->gamestate->nextState('nextPlayer');
        }
    }

    function action_selectScenario($card)
    {
        self::checkAction('selectScenario');
        $player_id = $this->getCurrentPlayerId();
        $color = $this->getPlayerColor($player_id);
        // $check card
        $tokens = $this->tokens->getTokensOfTypeInLocation("scenario", "scenarios");
        $this->systemAssertTrue("Bad card for pick", array_key_exists($card, $tokens));
        $this->dbSetTokenLocation($card, "tableau_$color", 1, clienttranslate('${player_name} picked ${token_name}'));

        $sc = $this->tokens->getTokenOfTypeInLocation('scenario', "tableau_$color");
        $key = $sc['key'];

        // apply the scenario information
        $this->scenarioProcess();

        $token = $this->token_types[$key];
        $conflicts = $token['conflict'];
        // remove conflicting scenarios
        foreach ($conflicts as $confKey) {
            // Conflicts will include all expansions, even if not active
            $tokenExists = $this->tokens->getTokenLocation($confKey) !== null;
            if ($tokenExists) {
                $this->dbSetTokenLocation($confKey, 'dev_null', null, clienttranslate('${token_name} can no longer be selected'));
            }
        }

        $this->gamestate->nextState('');
    }

    function st_gamePlayerSetup()
    {
        $this->setupPlayers();
        // begging of the turn for active player
        $player_id = $this->getActivePlayerId();
        $this->saction_TurnReplenish($player_id);
        $this->incStat(1, 'turns_number', $player_id);
        $this->incStat(1, 'turns_number');

        $this->gamestate->nextState('');
    }

    function st_gameTurnNextPlayerFollow()
    {
        $role_index = $this->getGameStateValue('role');
        if ($role_index < 0) {
            // No role was played by the previous player (can happen only if it's a zombie) -> nothing to follow
            $this->gamestate->nextState('last');
            return;
        }
        $role = $this->materials['role_icons'][$role_index];
        $this->dbSetPlayerMultiactive(-1, 1); // all active

        $leader_id = $this->getGameStateValue('leader');
        $player_id = $this->getPlayerAfter($leader_id);
        $mactive_players = array();
        $waiting = array();
        $this->dbSetPlayerMultiactive($leader_id, 0);
        $alwaysWaiting = $this->isAsync() == false; // realtime
        while ($player_id != $leader_id) {
            if (!$this->isPlayerMaskSet($player_id, 'followers')) {
                if (!$this->autoDissent($player_id, $role)) {
                    $mactive_players[] = $player_id;
                    $iswaiting = $alwaysWaiting || $this->isPlayerMaskSet($player_id, 'followers_waiting');
                    $waiting[$player_id] = $iswaiting;
                } else {
                    $this->dbSetPlayerMultiactive($player_id, 0);
                }
            } else {
                $this->dbSetPlayerMultiactive($player_id, 0);
            }
            $player_id = $this->getPlayerAfter($player_id);
        }
        if (count($mactive_players) > 0) {
            $active_follower = $mactive_players[0];
            $this->setGameStateValue('active_follower', $active_follower);
            $this->clearPlayerMask($active_follower, 'followers_waiting');
            $waiting[$active_follower] = 0; // active follower cannot wait
            foreach ($mactive_players as $cur_player_id) {
                if ($waiting[$cur_player_id])
                    $this->dbSetPlayerMultiactive($cur_player_id, 0);
                else
                    $this->giveExtraTime($cur_player_id);
            }

            if ($this->autoDissent($active_follower, $role)) {
                $this->gamestate->nextState('loopback');
                return;
            }
        }
        if (!$this->gamestate->updateMultiactiveOrNextState('last')) { // last is game upkeep (STATE_GAME_TURN_UPKEEP)
            $this->setGameStateValue('boost_rem', 0); // reset research boost
            $this->gamestate->nextState('next'); // player follow
        }
    }

    function autoDissent($player_id, $role)
    {
        $color = $this->getPlayerColor($player_id);
        if ($this->dbGetPref(150, $color) != 1) { // PREF_AUTO_DISSENT
            return;
        }
        if (!$this->canFollow($player_id, $role)) {
            $role_name = $this->materials['icons'][$role];
            $role_args = ['role_name' => $role_name, 'i18n' => ['role_name']];

            $this->notifyPlayer(
                $player_id,
                'playerLog',
                clienttranslate('[private] game calculated that there is no legitimate way for you to follow ${role_name}'),
                $role_args
            );
            $this->action_playDissentGame($player_id);
            return true;
        }
        return false;
    }

    function canFollow($player_id, $role)
    {
        $info = $this->arg_playerTurnInfo($player_id, "f-" . $role);
        return $this->canFollowOnInfo($info, $role, $player_id);
    }

    function canFollowOnInfo($info, $role, $player_id)
    {
        $hb = $info['hand_boost_num'][$role];
        $pb = $info['perm_boost_num'][$role];
        $total = $hb + $pb;
        if ($this->playerHasCard('card_tech_E_2', $this->getPlayerColorById($player_id))) { //freedom of trade
            if ($role == 'P' || $role == 'T') {
                return true; // do not auto-disscent
            }
        }

        if ($total == 0 && $role != 'R')
            return false;
        if ($role == 'C' && $hb == 0)
            return false;
        if ($role == 'S' && $total <= 1)
            return false;
        if ($role == 'R') {
            if ($this->isEscalationVariant())
                return true;
            if ($total <= 2)
                return false;
        }
        return true;
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Zombie
    ////////////
    /*
     * zombieTurn:
     *
     * This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
     * You can do whatever you want in order to make sure the turn of this player ends appropriately
     * (ex: pass).
     */
    function zombieTurn($state, $active_player)
    {
        $statename = $state['name'];
        if ($state['type'] === "activeplayer") {
            switch ($statename) {
                case 'playerTurnAction':
                    $this->gamestate->nextState("last");
                    break;
                default:
                    $this->gamestate->nextState("next");
                    break;
            }
            return;
        }
        if ($state['type'] === "multipleactiveplayer") {
            //if ($statename == "playerTurnFollow")
            $this->setPlayerMask($active_player, 'followers');
            // Make sure player is in a non blocking status for role turn
            $this->gamestate->setPlayerNonMultiactive($active_player, 'next');
            return;
        }
        throw new feException("Zombie mode is supported in this game state: " . $statename);
    }

    ///////////////////////////////////////////////////////////////////////////////////:
    ////////// DB upgrade
    //////////
    /*
     * upgradeTableDb:
     *
     * You don't have to care about this until your game has been published on BGA.
     * Once your game is on BGA, this method is called everytime the system detects a game running with your old
     * Database scheme.
     * In this case, if you change your Database scheme, you just have to apply the needed changes in order to
     * update the game database and allow the game to continue to run with your new version.
     *
     */
    function upgradeTableDb($from_version)
    {
        // $from_version is the current version of this game database, in numerical form.
        // For example, if the game was running with a release of your game named "140430-1345",
        // $from_version is equal to 1404301345
        // Example:
        //        if( $from_version <= 1404301345 )
        //        {
        //            $sql = "ALTER TABLE xxxxxxx ....";
        //            self::DbQuery( $sql );
        //        }
        //        if( $from_version <= 1405061421 )
        //        {
        //            $sql = "CREATE TABLE xxxxxxx ....";
        //            self::DbQuery( $sql );
        //        }
        //        // Please add your future database scheme changes here
        //
        //
    }
}
