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
 * eminentdomain.view.php
 *
 * This is your "view" file.
 *
 * The method "build_page" below is called each time the game interface is displayed to a player, ie:
 * _ when the game starts
 * _ when a player refreshes the game page (F5)
 *
 * "build_page" method allows you to dynamically modify the HTML generated for the game interface. In
 * particular, you can set here the values of variables elements defined in eminentdomain_eminentdomain.tpl (elements
 * like {MY_VARIABLE_ELEMENT}), and insert HTML block elements (also defined in your HTML template file)
 *
 * Note: if the HTML of your game interface is always the same, you don't have to place anything here.
 *
 */
  
  require_once( APP_BASE_PATH."view/common/game.view.php" );
  
  class view_eminentdomain_eminentdomain extends game_view
  {
    function getGameName() {
        return "eminentdomain";
    }    
    function getTemplateName() {
        return self::getGameName() . "_" . self::getGameName();
    }
    
    function processPlayerBlock($player_id, $player) {
        global $g_user;
        $cplayer = $g_user->get_id();
        
        $color = $player ['player_color'];
        $name = $player ['player_name'];
        $no = $player ['player_no'];
        $own = $cplayer == $player_id;
        $this->page->insert_block("player_board", array ("COLOR" => $color,"PLAYER_NAME" => $name,"PLAYER_NO" => $no,
                "PLAYER_ID" => $player_id, "CLASSES" => $own?"own":"", 
                "EMPIRE_LABEL" => self::raw(gameview_str_replace('${player_name}', $name, self::_('${player_name}\'s EMPIRE')))
              ));
    }
    
    function build_page($viewArgs) {
        global $g_user;
        $cplayer = $g_user->get_id();
        // Get players & players number
        $players = $this->game->loadPlayersBasicInfos();
        $players_nbr = count($players);
        /**
         * ********* Place your code below: ***********
         */
        $template = self::getTemplateName();
        $num = $players_nbr;
        $this->tpl ['PLS'] = $num;

        $this->tpl ['PCOLOR'] = 'ffffff'; // spectator
   
        $gameinfos = $this->game->getGameinfos();
        $default_colors = $gameinfos ['player_colors'];

       
        

        
        $players = $this->game->getPlayersInOrder($cplayer);
        //$gameinfos = $this->game->getGameinfos();
        //$default_colors = $gameinfos ['player_colors'];
        $this->page->begin_block($template, "player_board");
        // inner blocks in player blocks
        // boards in players order
        foreach ( $players as $player_id => $player ) {
            if ($player_id == $cplayer) {
                $this->tpl ['PCOLOR'] = $player ['player_color'];
            }
            $this->processPlayerBlock($player_id, $player);
        }
        /**
         * ********* Do not change anything below this line ***********
         */
    }
  }
  

