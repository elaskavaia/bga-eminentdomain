<?php
/**
 * This class contants functions that work with tokens SQL model and tokens class
 *
 <code>
 require_once (APP_GAMEMODULE_PATH . 'module/table/table.game.php');
 
 require_once ('modules/EuroGame.php');
 
 class EpicKingdom extends EuroGame {
 }
 </code>
 *
 */
require_once ('APP_Extended.php');
require_once ('tokens.php');

abstract class EuroGame extends APP_Extended {
    public $tokens;
    public $token_types;

    public function __construct() {
        parent::__construct();
        $this->tokens = new Tokens();
    }

    protected function setCounter(&$array, $key, $value) {
        $array [$key] = array ('counter_value' => $value,'counter_name' => $key );
    }

    protected function fillCounters(&$array, $locs, $create = true) {
        foreach ( $locs as $location => $count ) {
            $key = $location . "_counter";
            if ($create || array_key_exists($key, $array))
                $this->setCounter($array, $key, $count);
        }
    }

    protected function fillTokensFromArray(&$array, $cards) {
        foreach ( $cards as $pos => $card ) {
            $id = $card ['key'];
            $array [$id] = $card;
        }
    }
    
    protected function getTokenName($token_id) {
        if (is_array($token_id))
            return $token_id;
            if ($token_id == null)
                return "null";
                $array = $this->token_types;
                if (isset($array [$token_id]) && isset($array [$token_id] ['name'])) {
                    $value = $array [$token_id] ['name'];
                    if ( !empty($value))
                        return $value;
                }
                $type = getPartsPrefix($token_id, -1);
                if ($type != $token_id) {
                    return $this->getTokenName($type);
                }
                return "? $token_id  $type";
    }

    protected function getAllDatas() {
        $result = array ();
        $current_player_id = self::getCurrentPlayerId(); // !! We must only return informations visible by this player !!
        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_score score, player_no no FROM player ";
        $result ['players'] = self::getCollectionFromDb($sql);
        $result ['token_types'] = $this->token_types;
        $result ['tokens'] = array ();
        $result ['counters'] = $this->getDefaultCounters();
        $locs = $this->tokens->countTokensInLocations();
        //$color = $this->getPlayerColor($current_player_id);
        foreach ( $locs as $location => $count ) {
            $info = $this->token_types [$location] ?? array ();
            $sort = $info ['sort'] ?? null;
            
            if ($this->isCounterAllowedForLocation($current_player_id, $location)) {
                $this->fillCounters($result ['counters'], [ $location => $count ]);
            }
            $content = $this->isContentAllowedForLocation($current_player_id, $location);
            if ($content !== false) {
                if ($content === true) {
                    $tokens = $this->tokens->getTokensInLocation($location, null, $sort);
                    $this->fillTokensFromArray($result ['tokens'], $tokens);
                } else {
                    $num = floor($content);
                    if ($count < $num)
                        $num = $count;
                    $tokens = $this->tokens->getTokensOnTop($num, $location);
                    $this->fillTokensFromArray($result ['tokens'], $tokens);
                }
            }
        }
        return $result;
    }

    protected function getDefaultCounters() {
        $types = $this->token_types;
        $res = [ ];
        $players_basic = $this->loadPlayersBasicInfos();
        foreach ( $types as $key => $info ) {
            if (array_key_exists('loc', $info) && $info ['loc'] && $info ['counter'] == 1) {
                if ($info ['loc'] == 1) {
                    $this->setCounter($res, "${key}_counter", 0);
                } else  if ($info ['loc'] == 2) {
                    foreach ( $players_basic as $player_id => $player_info ) {
                        $color = $player_info ['player_color'];               
                        $this->setCounter($res, "${key}_${color}_counter", 0);
                    }
                }
            }
        }
        return $res;
    }

    protected function isContentAllowedForLocation($player_id, $location) {
        if ($location === 'dev_null')
            return false;
        $key = $location;
        $attr = 'content';
        if (! array_key_exists($key, $this->token_types)) {
            $key = getPartsPrefix($location, - 1);
        }
        if (array_key_exists($key, $this->token_types)) {
            $info = $this->token_types [$key];
            if (array_key_exists('loc', $info) && $info ['loc']) {
                if ($info [$attr] == 1) {
                    return true;
                }
                if ($info [$attr] == 2) {
                    $color = $this->getPlayerColor($player_id);
                    return endsWith($location, $color);
                }
                if ($info [$attr] == 0) {
                    return false;
                }
            } else {
                return true; // not listed as location
            }
        } else {
            return true; // not listed allowed
        }
        return false;
    }

    protected function isCounterAllowedForLocation($player_id, $location) {
        if ($location === 'dev_null')
            return false;
        $key = $location;
        $attr = 'counter';
        if (! array_key_exists($key, $this->token_types)) {
            $key = getPartsPrefix($location, - 1);
        }
        if (array_key_exists($key, $this->token_types)) {
            $info = $this->token_types [$key];
            if (array_key_exists('loc', $info) && $info ['loc']) {
                if ($info [$attr] == 1) {
                    return true;
                }
                if ($info [$attr] == 2) {
                    $color = $this->getPlayerColor($player_id);
                    return endsWith($location, $color);
                }
                if ($info [$attr] == 0) {
                    return false;
                }
            }
        }
        return false;
    }
    
    function dbSetTokenState($token_id, $state = null, $notif = '*', $args = null) {
        $this->dbSetTokenLocation($token_id, null, $state, $notif, $args);
    }

    /**
     * Silent notification that updates states of token/location/state info for the client
     * @param * $token_arr
     */
    function dbSyncInfo($token_arr) {
        $type = $this->tokens->checkListOrTokenArray($token_arr);
        if ($type == 0)
            return;
        if ($type == 1)
            $token_arr = $this->tokens->getTokensInfo($token_arr);
        foreach ( $token_arr as $info ) {
            $token_id = $info ['key'];
            $place_id = $info ['location'];
            $state = $info ['state'];
            $notifyArgs = array ('token_id' => $token_id,'place_id' => $place_id,'place_name' => $place_id,
                    'new_state' => $state,'noa' => true,'nod' => true );
            $this->notifyWithName("tokenMoved", '', $notifyArgs);
        }
    }
    
    /**
     * Sends tokenMove notification with multiple objects, parameters of notication (must be handled by tokenMove)
     * list - array of token ids
     * token_divs - comma separate list of tokens (to inject visualisation)
     * new_state - if same state - new state
     * new_states - if multiple states array of integer states
     *
     * @param [] $token_arr - array of tokens keys or token info
     * @param string $place_id - location of all tokens will be set to $player_id value
     * @param null|int $state - if null is passed state won't be changed
     * @param string $notif
     * @param array $args
     */
    function dbSetTokensLocation($token_arr, $place_id, $state = null, $notif = '*', $args = [ ]) {
        $type = $this->tokens->checkListOrTokenArray($token_arr);
        if ($type == 0)
            return;
              
        $this->systemAssertTrue("place_id cannot be null", $place_id != null);
        if ($notif === '*')
            $notif = clienttranslate('${player_name} moves ${token_names} into ${place_name}');

        $keys = [ ];
        $states = [ ];
        if (isset($args ['place_from']))
            $place_from = $args ['place_from'];
        else
            $place_from = null;
        foreach ( $token_arr as $token ) {
            if (is_array($token)) {
                $token_id = $token ['key'];
                $states [] = $token ['state'];
                if ($place_from == null) {
                    $place_from = $token ['location'];
                }
            } else {
                $token_id = $token;
            }
            $keys [] = $token_id;
        }
        $this->tokens->moveTokens($keys, $place_id, $state);
        $notifyArgs = array ('list' => $keys, //
        'place_id' => $place_id, //
        'place_name' => $place_id );
        if ($state !== null) {
            $notifyArgs ['new_state'] = $state;
        } else if (count($states) > 0) {
            $notifyArgs ['new_states'] = $states;// this only used for visualization, state won't change in db
        }
        if (strstr($notif, '${you}')) {
            $notifyArgs ['you'] = 'you'; // translated on client side, this is for replay after
        }
        if (strstr($notif, '${token_divs}')) {
            $notifyArgs ['token_divs'] = implode(",", $keys);
        }
        if (strstr($notif, '${token_div}')) {
            $notifyArgs ['token_div'] = $keys [0];
        }
        if (strstr($notif, '${token_names}')) {
            $notifyArgs ['token_names'] = implode(",", $keys);
        }
        if (strstr($notif, '${token_name}')) {
            $notifyArgs ['token_name'] = $keys [0];
        }
        $num = count($keys);
        if (strstr($notif, '${token_div_count}')) {
            $notifyArgs ['token_div_count'] = [ 'log' => '${token_div} x${mod}',
                    'args' => [ 'token_div' => $token_id,'mod' => $num ] ];
        }


        $args = array_merge($notifyArgs, $args);
        //$this->warn("$type $notif ".$args['token_id']." -> ".$args['place_id']."|");
        if (array_key_exists('player_id', $args)) {
            $player_id = $args ['player_id'];
        } else {
            if ($this->gamestate->state() ['type'] === "multipleactiveplayer") {
                $state = $this->getStateName();
                $this->debugConsole("requested active player in multi-active state $state");
            }
            $player_id = $this->getActivePlayerId();
        }
        $this->notifyWithName("tokenMoved", $notif, $args, $player_id);

        // send counter update if required
        if ($place_from && $this->isCounterAllowedForLocation($player_id, $place_from)) {
            $this->notifyCounter($place_from, [ 'nod' => true ]);
        }
        if ($place_id != $place_from && $this->isCounterAllowedForLocation($player_id, $place_id)) {
            $this->notifyCounter($place_id, [ 'nod' => true ]);
        }
    }
    
    function dbSetTokenLocation($token_id, $place_id, $state = null, $notif = '*', $args = null) {
        $this->systemAssertTrue("token_id cannot be array ".toJson($token_id), !is_array($token_id));
        $this->systemAssertTrue("token_id is null/empty $token_id, $place_id $notif", $token_id != null && $token_id != '');
        if ($args == null)
            $args = array ();
        if ($notif === '*')
            $notif = clienttranslate('${player_name} moves ${token_name} into ${place_name}');
        if ($state === null) {
            $state = $this->tokens->getTokenState($token_id);
        }
        $place_from = $this->tokens->getTokenLocation($token_id);
        $this->systemAssertTrue("token_id does not exists, create first: $token_id", $place_from);
        if ($place_id === null) {
            $place_id = $place_from;
        } 
        $this->tokens->moveToken($token_id, $place_id, $state);
        $notifyArgs = array (
                'token_id' => $token_id,
                'place_id' => $place_id,
                'token_name' => $token_id,
                'place_name' => $place_id,
                'new_state' => $state );
        $args = array_merge($notifyArgs, $args);
            //$this->warn("$type $notif ".$args['token_id']." -> ".$args['place_id']."|");
        if (array_key_exists('player_id', $args)) {
            $player_id = $args ['player_id'];
        } else {
            $player_id = $this->getActivePlayerId();
        }
        
        if (strstr($notif, '${you}')) {
            $notifyArgs ['you'] = 'You';// translated on client side, this is for replay after
        }
        
        $this->notifyWithName("tokenMoved", $notif, $args, $player_id);
        if ($this->isCounterAllowedForLocation($player_id, $place_from)) {
            $this->notifyCounter($place_from, [ 'nod' => true ]);
        }
        if ($place_id != $place_from && $this->isCounterAllowedForLocation($player_id, $place_id)) {
            $this->notifyCounter($place_id, [ 'nod' => true ]);
        }
    }
    
    /**
     * This method will increase/descrease resource counter (as state)
     * @param string $token_id - token key
     * @param int $num - increment of the change
     * @param string $place - optional $place, only used in notification to show where "resource" 
     *   is gain or where it "goes" when its paid, used in client for animation
     */
    function dbResourceInc($token_id, $num, $place = null) {
        $player_id = $this->getActivePlayerId();
        $color = $this->getPlayerColor($player_id);
       
        $current = $this->tokens->getTokenState($token_id);
        $value = $this->tokens->setTokenState($token_id, $current + $num);
        if ($value < 0) {
            $this->userAssertTrue(self::_("Not enough resources to pay"), $current >= - $num);
        }

        if ($num < 0) {
            if ($place)
                $message = clienttranslate('${player_name} pays ${inc_resource} for ${place_name}');
            else
                $message = clienttranslate('${player_name} pays ${inc_resource}');
        } else {
            if ($place)
                $message = clienttranslate('${player_name} gains ${inc_resource} from ${place_name}');
            else
                $message = clienttranslate('${player_name} gains ${inc_resource}');
        }
        //$this->warn("playing inc $token_id, $num, $place");
        $this->notifyWithName("counter", $message, [
                'counter_name'=>$token_id,
                'counter_value'=>$value,
                'place'=>$place,
                'place_name'=>$place,
                'mod' => abs($num),
                'inc' => $num,
                'inc_resource' => [ 'log' => '${token_name} x${mod}',
                        'args' => [ 'token_name' => $token_id,
                                    'mod' => abs($num),
                                    'inc' => $num,
                        ] ]
                
        ]);
    }
    
    function getStaticPrefData($id, $serverCheck=true) {
        // Load user preferences
        include dirname(__FILE__) . '/../gameoptions.inc.php';
        $this->systemAssertTrue("no user preferences", $game_preferences);
        $this->systemAssertTrue("no key defined $id in preferences", array_key_exists($id, $game_preferences));
        $data = $game_preferences [$id];
        if ($serverCheck) 
            $this->systemAssertTrue("pref key $id does not require server sync", $data['server_sync']);
        return $data;
    }

    function dbCreatePref($id, $color, $defaultValue) {
        $data = $this->getStaticPrefData($id);
        if ($defaultValue === null)
            $defaultValue = $data ['default'] ?? array_keys($data ['values']) [0];
        $key = "pref_${id}_${color}";
        $this->tokens->createToken($key, "preferences_${color}", $defaultValue);
    }
    
    function dbSetPref($pref, $color, $value) {
        $key = "pref_${pref}_${color}";
        $info = $this->tokens->getTokenInfo($key);
        if ($info == null) {
            // lazy init
            $this->dbCreatePref($pref, $color, $value);
            return;
        }
        if ($info ['state'] == $value)
            return;
            $this->tokens->setTokenState($key, $value);
    }
    function dbGetPref($pref, $color) {
        $key = "pref_${pref}_${color}";
        $info = $this->tokens->getTokenInfo($key);
        if ($info == null) {
            $data = $this->getStaticPrefData($pref);
            $defaultValue = $data ['default'] ?? array_keys($data ['values']) [0];
            return (int)$defaultValue;
        }
        return (int)$info['state'];
    }
    
    function dbGetAllServerPrefs($player_id){
        $color = $this->getPlayerColor($player_id);
        if ($color === 0) {
            return []; // not a player - ignore
        }
        // Load user preferences
        include dirname(__FILE__) . '/../gameoptions.inc.php';
        $this->systemAssertTrue("no user preferences", $game_preferences);
        $values = [];
        foreach ($game_preferences as $id => $data) {
            if (! $data['server_sync']) continue;
            $values[$id]=$this->dbGetPref($id, $color);
        }
        return $values;
    }

    function notifyCounter($location, $notifyArgs = null) {
        $key = $location . "_counter";
        $value = ($this->tokens->countTokensInLocation($location));
        $this->notifyCounterDirect($key, $value, '', $notifyArgs);
    }

    function notifyCounterDirect($key, $value, $message, $notifyArgs = null) {
        $args = [ 'counter_name' => $key,'counter_value' => $value ];
        if ($notifyArgs != null)
            $args = array_merge($notifyArgs, $args);
        $this->notifyWithName("counter", $message, $args);
    }
    
    function notifyCounterInc($key, $value, $message, $notifyArgs = null) {
        $args = [ 'counter_name' => $key,'counter_inc' => $value ];
        if ($notifyArgs != null)
            $args = array_merge($notifyArgs, $args);
            $this->notifyWithName("counter", $message, $args);
    }
}
