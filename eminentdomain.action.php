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
 * eminentdomain.action.php
 *
 * EminentDomain main action entry point
 *
 *
 * In this file, you are describing all the methods that can be called from your
 * user interface logic (javascript).
 *       
 * If you define a method "myAction" here, then you can call it from your javascript code with:
 * this.ajaxcall( "/eminentdomain/eminentdomain/myAction.html", ...)
 *
 */
class action_eminentdomain extends APP_GameAction {

    // Constructor: please do not modify
    public function __default() {
        if (self::isArg('notifwindow')) {
            $this->view = "common_notifwindow";
            $this->viewArgs ['table'] = self::getArg("table", AT_posint, true);
        } else {
            $this->view = "eminentdomain_eminentdomain";
            self::trace("Complete reinitialization of board game");
        }
    }

    public function playAction() {
        self::setAjaxMode();
        $card = self::getArg('card', AT_alphanum, true);
        $num = self::getArg('actnum', AT_posint, false, 0);
        $this->game->action_playAction($card, $this->getJsArg("choices_js"), $num);
        self::ajaxResponse();
    }
    
    public function playActivatePermanent() {
        self::setAjaxMode();
        $card = self::getArg('card', AT_alphanum, true);
        $choices = $this->getJsArg("choices_js");
        $this->game->action_playActivatePermanent($card, $choices);
        self::ajaxResponse();
    }

    public function playRole() {
        self::setAjaxMode();
        $role = self::getArg('role', AT_alphanum, true);
        $card = self::getArg('card', AT_alphanum, false, "");
        $choices = $this->getJsArg("choices_js");
        $boost = self::getArg('boost', AT_alphanum, false, "");
        $this->game->action_playRole($role, $card, $boost, $choices);
        self::ajaxResponse();
    }

    public function playDiscard() {
        self::setAjaxMode();
        $choices = self::getArg('boost', AT_alphanum, false, "");
        $this->game->action_playDiscard($choices);
        self::ajaxResponse();
    }

    public function playPick() {
        self::setAjaxMode();
        $card = self::getArg('card', AT_alphanum, true);
        $this->game->action_playPick($card);
        self::ajaxResponse();
    }

    public function skipAction() {
        self::setAjaxMode();
        $this->game->action_skipAction();
        self::ajaxResponse();
    }
    
    public function playFollow() {
        self::setAjaxMode();
        $choices = $this->getJsArg("choices_js");
        $boost = self::getArg('boost', AT_alphanum, false, "");
        $this->game->action_playFollow($boost, $choices);
        self::ajaxResponse();
    }
    
    public function playExtra() {
        self::setAjaxMode();
        $choices = $this->getJsArg("choices_js");
        $this->game->action_playExtra($choices);
        self::ajaxResponse();
    }
    
    
    public function playDissent() {
        self::setAjaxMode();
        $this->game->action_playDissent();
        self::ajaxResponse();
    }
    public function playWait() {
        self::setAjaxMode();
        $this->game->action_playWait();
        self::ajaxResponse();
    }

    public function selectScenario(){
        self::setAjaxMode();
        $card = self::getArg("card", AT_alphanum, true);
        $this->game->action_selectScenario($card);
        self::ajaxResponse();
    }

    function showDiscard() {
        self::setAjaxMode();
        $arg1 = self::getArg("place", AT_alphanum, true);
        $res = $this->game->query_revealContents($arg1);
        self::ajaxResponseWithResult([ 'contents' => $res,'length' => count($res) ]);
    }
    
    public function changePreference()
    {
        self::setAjaxMode();
        $pref = self::getArg('pref', AT_posint, false);
        $value = self::getArg('value', AT_posint, false);
        $this->game->action_ChangePreference($pref, $value);
        self::ajaxResponse();
    }
    
    function getJsArg($var) {
        $value = self::getArg($var, AT_json, true);
        $this->validateJSonAlphaNum($value, $var);
        return $value;
    }
    
    function validateJSonAlphaNum($value, $argName = "unknown"){
        if (is_array($value)) {
            foreach ($value as $key => $v) {
                $this->validateJSonAlphaNum($key);
                $this->validateJSonAlphaNum($v);
            }
            return true;
        }
        if (is_int($value)) return true;
        $bValid = (preg_match("/^[0-9a-zA-Z_ \-]*$/", $value) === 1); // NOI18N
        if ( !$bValid) {
            $this->error("Bad value for: $argName $value");
            throw new feException("Bad value for: $argName", true, true, FEX_bad_input_argument);
        }
        return true;
    }
}
  

