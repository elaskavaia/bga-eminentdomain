/**
 * ------ BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com> EminentDomain
 * implementation : © Alena Laskavaia <laskava@gmail.com>
 * 
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com. See
 * http://en.boardgamearena.com/#!doc/Studio for more information. -----
 * 
 * eminentdomain.js
 * 
 * EminentDomain user interface script
 * 
 * In this file, you are describing the logic of your user interface, in Javascript language.
 * 
 */
var SYM_RIGHTARROW = " &rarr; ";
define(["dojo", "dojo/_base/declare", "ebg/core/gamegui", "ebg/counter",
	// load my own module!!!
	g_gamethemeurl + "modules/sharedparent.js"], function(dojo, declare) {
		return declare("bgagame.eminentdomain", bgagame.sharedparent, // parent declared in shared module
			{
				constructor: function() {
					console.log('eminentdomain constructor');
					g_img_preload = ['cards.jpg', 'board.jpg', 'planets.jpg'];

					this.curstate = null;
					this.pendingUpdate = false;
					this.curActive = false;
					this.defaultTooltipDelay = 600;
				},
				/*
				 * setup:
				 * 
				 * This method must set up the game user interface according to current game situation specified in parameters.
				 * 
				 * The method is called each time the game interface is displayed to a player, ie: _ when the game starts _ when a player refreshes
				 * the game page (F5)
				 * 
				 * "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
				 */
				setup: function(gamedatas) {
					console.log("Starting game setup", gamedatas);
					//   dojo.destroy('debug_output');
					this.inSetup = true;
					// Setting up player boards
					this.inherited(arguments);
					if (!this.isSpectator) {
						// ok
					} else {
						dojo.style($('hand_area'), "display", "none");
					}

					if (gamedatas.variants.learning_variant_on) {
						dojo.addClass($('thething'), "learning_variant_on");
						dojo.place("<div class='noresearch'>" + _("Learning Game<br>No Reasearch") + "</div>", 'supply_research');
					}

					this.updateEndOfGameMessage(gamedatas);
	

					if (!this.isReadOnly()) {
						this.checkPreferencesConsistency(this.gamedatas.server_prefs);
					}
					
					this.setupPreference();

					console.log("Ending game setup");
				},
				
				checkPreferencesConsistency: function(backPrefs) {
					//console.log('check pref',backPrefs,this.prefs);
					for (var key in backPrefs) {
						let value = backPrefs[key];
						let pref = key;
						let user_value = parseInt(this.prefs[pref].value);
						if (this.prefs[pref] !== undefined && user_value != value) {
							var args = { pref: pref, value: user_value, player: this.player_id };
							backPrefs[key] = user_value;
							this.ajaxcall("/" + this.game_name + "/" + this.game_name + "/" + 'changePreference' + ".html", args,// 
								this, () => {}, (err, res) => { if (err) console.error("changePreference callback failed " + res); else console.log("changePreference sent " + pref + "=" + user_value); });

						}
					}
				},
				setupPreference: function() {
					// Extract the ID and value from the UI control
					var _this = this;
					function onchange(e) {
						var match = e.target.id.match(/^preference_[cf]ontrol_(\d+)$/);
						if (!match) {
							return;
						}
						var prefId = +match[1];
						var prefValue = +e.target.value;
						_this.prefs[prefId].value = prefValue;
						_this.onPreferenceChange(prefId, prefValue);

					}

					dojo.query(".preference_control").connect("onchange", onchange);
					// Call onPreferenceChange() now
					dojo.query("#ingame_menu_content .preference_control").forEach((el) => onchange({ target: el }));
				},

				onPreferenceChange: function(prefId, prefValue) {
					console.log("Preference changed", prefId, prefValue);

					if (!this.isReadOnly()) {
						this.checkPreferencesConsistency(this.gamedatas.server_prefs);
					}
				},


				updateEndOfGameMessage: function(args) {
					var endmessage = _('End of game is triggered.');
					endmessage += " ";
					if (this.gamedatas.variants.extra_turn_variant_on) {
						if (args.extra_turn) {
							endmessage += _('This is last round (extra).');
							if (this.player_id == args.leader) {
								endmessage += " " + _('This is your last turn.');
							}
						} else {
							endmessage += _('There will be an extra round.');
						}
					} else {
						endmessage += _('This is last round.');
						if (this.player_id == args.leader) {
							endmessage += " " + _('This is your last turn.');
						}
					}



					$('endofgamewarning').innerHTML = endmessage;

					if (this.gamedatas.end_of_game || args.end_of_game) {
						dojo.addClass($('thething'), "end_of_game");
					}
				},

				setupGameTokens: function() {
					if (!this.isSpectator) {
						this.drawHandIcon(5, 'hand_icon', this.player_color, -2);
					}
					this.updateCountersSafe(this.gamedatas.counters);
					for (var token in this.gamedatas.tokens) {
						var tokenInfo = this.gamedatas.tokens[token];
						var location = tokenInfo.location;
						if (!$(location) && this.gamedatas.tokens[location]) {
							this.placeTokenWithTips(location);
						}
						this.placeTokenWithTips(token);
					}
					dojo.query(".counter,.slot_tooltip").forEach(dojo.hitch(this, function(node) {
						this.updateTooltip(node.id);
					}));
					this.connectClass('card_bottom', 'onclick', 'onCard');
					this.connectClass('supply_tech', 'onclick', 'onCard');
					this.connectClass('discard', 'onclick', 'onDiscard');
					this.connect($('discard_planets'), 'onclick', 'onDiscard');
					
					                    // Labels
                    $('deck_display').setAttribute('data-title', _('Deck'));
                    $('discard_display').setAttribute('data-title', _('Discard'));

					this.connectClass('gslider', 'oninput', 'onSlider');
					this.connectClass('gcheckbox', 'oninput', 'onCheckbox');

					console.log("enging token setup");
					//dojo.place(this.getJemImg('green'),'bboard');
				},
				setupPlayer: function(player_id, playerInfo) {
					// console.log("player info " + player_id, playerInfo);
					var color = playerInfo.color;
					var playerBoardDiv = dojo.byId('player_board_' + player_id);
					// var name = "player_" + player_id + "_status";
					// dojo.place("pnum_" + player_id, name, "before");
					dojo.place('miniboard_' + color, playerBoardDiv);
					this.drawHandIcon(1, 'deck_icon_' + color, color, 0);
					this.drawHandIcon(2, 'discard_icon_' + color, color);
					this.drawHandIcon(5, 'hand_icon_' + color, color, -2);
					// player position
					var name = "player_" + player_id + "_status";
					dojo.place("pnum_" + player_id, name, "before");
					// player deck
					if (this.player_id == player_id) {
						dojo.connect($('deck_' + color), "onclick", this, "onDeck");
						dojo.style($('deck_' + color), 'cursor', 'pointer');
					}
					$('setaside_'+color).setAttribute('data-title', _('Set Aside Zone'));
				},
				// /////////////////////////////////////////////////
				// // Game & client states
				// onEnteringState: this method is called each time we are entering into a new game state.
				// You can use this method to perform some user interface changes at this moment.
				//
				onEnteringState: function(stateName, args) {
					console.log('Entering state: ' + stateName, args);

					this.curstate = stateName;
					dojo.addClass($('thething'), stateName);

					if (!this.on_client_state) {
						this.clientStateArgs = {
							action: 'none',
							choices: [],
							unprocessed_choices: '',
							boost: '',
						};
						if (args && args.args && args.args.end_of_game) {
							dojo.addClass($('thething'), "end_of_game");
						}

						switch (stateName) {
							case 'playerTurnAction':
								this.clientStateArgs.action = 'playAction';
								var color = this.getPlayerColor(this.getActivePlayerId());
								this.updatePermCounters(color, args.args.perm_boost_num);
								this.updateEndOfGameMessage(args.args);
								if (args.args.card) {
									var myargs = args.args;
									this.clientStateArgs.nocancel = true;
									setTimeout(() => this.playAction(myargs.card, myargs.rules)
										, 300);
									break;
								}

								break;
							case 'playerTurnRole':


								this.clientStateArgs.action = 'playRole';
								var color = this.getPlayerColor(this.getActivePlayerId());
								this.updatePermCounters(color, args.args.perm_boost_num);
								this.updateEndOfGameMessage(args.args);
								break;
							case 'playerTurnFollow':
								this.clientStateArgs.action = 'playFollow';
								this.updateEndOfGameMessage(args.args);
								break;
							case 'playerTurnDiscard':
							case 'playerTurnPreDiscard':
								this.clientStateArgs.action = 'playDiscard';
								break;
							case 'playerTurnSurvey':
							case 'playerTurnSurveyFollow':
							case 'client_playerTurnSurvey':
								this.clientStateArgs.action = 'playPick';
								break;
							case 'playerTurnRoleExtra':
							    if (!this.isCurrentPlayerActive())
									break;
								this.clientStateArgs.action = 'playExtra';
								if (this.instantaneousMode) {
									break;
								}
								//console.log(args.args.rules);

								if (args.args.rules == 'R') { //scientific method

									var oargs = args.args;
									this.clientStateArgs.unprocessed_choices = oargs.rules;
									this.clientStateArgs.card = oargs.card;
									this.clientStateArgs.nocancel = true;

									var total = oargs.boost_rem;
									this.setClientStateAction('client_selectTechCard',
										_('Select a technology to research <div class="icon research"></div>x${boost_count}'), 0, {
										boost_count: total,

									});

									this.addActionButton('button_skip', _('Skip'), (e) => {
										if (this.commitOperationAndSubmit('R', 'skip')) {
											this.expandTech(false);
										}
									});
									break;
								}
								if (args.args.survey) { //survey select card
									this.clientStateArgs.action = 'playPick';
									this.clientStateArgs.nocancel = true;
									this.setClientStateAction('client_playerTurnSurvey',this.gamedatas.gamestate.descriptionmyturn, 0);
									break;
								}




								this.clientStateArgs.unprocessed_choices = args.args.rules;
								this.clientStateArgs.nocancel = true;
								setTimeout(() => this.actionPrompt(), 300);
								break;
							default:
								break;
						}

						//console.log('-- settings cargs ', this.clientStateArgs);
						dojo.query(".selected").removeClass("selected");
						dojo.query(".toremove").removeClass("toremove");
						this.adjust3D();
					} else {
						console.log('-- client state --');
						// this.runAutoBot();
					}

					if (args) {
						args = args.args;
					}
					// Call appropriate method
					var methodName = "onEnteringState_" + stateName;
					if (this[methodName] !== undefined) {
						console.log('Calling ' + methodName, args);
						this[methodName](args);
					}
					if (this.pendingUpdate) {
						this.onUpdateActionButtons(stateName, args);
						this.pendingUpdate = false;
					}
				},
				// onLeavingState: this method is called each time we are leaving a game state.
				// You can use this method to perform some user interface changes at this moment.
				//
				onLeavingState: function(stateName) {
					console.log('Leaving state: ' + stateName);
					//console.log('-- cargs: ' + stateName,  this.clientStateArgs);
					dojo.query(".active_slot").removeClass('active_slot');
					dojo.query(".selected").removeClass('selected');
					dojo.query(".claimed").removeClass('claimed');
					dojo.removeClass($('thething'), stateName);
					this.curActive = false;
					//this.expandTech(false);
				},

				onUpdateActionButtons_playerTurnRole: function(args) {
					dojo.query(".board  .card_role:last-child").addClass('active_slot');
					dojo.query(".tableau_" + this.player_color + " .tech.state_1.activatable").addClass('active_slot');
					this.addImageActionButton('button_warfare', this.createDiv("icon warfare role"), dojo.hitch(this, function() {
						this.clientStateArgs.role = 'W';
						this.clientStateArgs.card = this.getRoleCard('warfare');
						if (!this.clientStateArgs.card.startsWith("x_"))
							this.placeTokenLocal(this.clientStateArgs.card, 'setaside_' + this.player_color, 12);
						this.setClientStateAction('client_selectBoostOrLeader');
					}));
					this.addImageActionButton('button_colonize', this.createDiv("icon colonize role"), dojo.hitch(this, function() {
						this.clientStateArgs.role = 'C';
						this.clientStateArgs.card = this.getRoleCard('colonize');
						if (!this.clientStateArgs.card.startsWith("x_"))
							this.placeTokenLocal(this.clientStateArgs.card, 'setaside_' + this.player_color, 12);
						this.setClientStateAction('client_selectBoostOrLeader');
					}));
					this.addImageActionButton('button_survey', this.createDiv("icon survey role"), dojo.hitch(this, function() {
						this.clientStateArgs.role = 'S';
						this.clientStateArgs.card = this.getRoleCard('survey');
						if (!this.clientStateArgs.card.startsWith("x_"))
							this.placeTokenLocal(this.clientStateArgs.card, 'setaside_' + this.player_color, 12);
						this.setClientStateAction('client_selectBoost');
					}));
					if (!this.gamedatas.variants.learning_variant_on) {
						this.addImageActionButton('button_research', this.createDiv("icon research role"), dojo.hitch(this, function() {
							this.clientStateArgs.role = 'R';
							this.clientStateArgs.card = this.getRoleCard('research');
							if (!this.clientStateArgs.card.startsWith("x_"))
								this.placeTokenLocal(this.clientStateArgs.card, 'setaside_' + this.player_color, 12);
							this.setClientStateAction('client_selectBoost');
						}));
					}
					this.addImageActionButton('button_produce', this.createDiv("icon produce role"), dojo.hitch(this, function() {
						this.clientStateArgs.role = 'P';
						this.clientStateArgs.card = this.getRoleCard('produce');
						if (!this.clientStateArgs.card.startsWith("x_"))
							this.placeTokenLocal(this.clientStateArgs.card, 'setaside_' + this.player_color, 12);
						this.setClientStateAction('client_selectBoost');
					}));
					this.addImageActionButton('button_trade', this.createDiv("icon trade role"), dojo.hitch(this, function() {
						this.clientStateArgs.role = 'T';
						this.clientStateArgs.card = this.getRoleCard('produce');
						if (!this.clientStateArgs.card.startsWith("x_"))
							this.placeTokenLocal(this.clientStateArgs.card, 'setaside_' + this.player_color, 12);
						this.setClientStateAction('client_selectBoost');
					}));

					return true;
				},
				// onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
				// action status bar (ie: the HTML links in the status bar).
				//        
				onUpdateActionButtons: function(stateName, args) {
					//console.log('onUpdateActionButtons: ' + stateName, args);
					if (this.curstate != stateName) {		// delay firing this
						this.pendingUpdate = true;
						this.curActive = false;
						//console.log('   DELAYED onUpdateActionButtons');
						return;
					}

					if (!this.isCurrentPlayerActive()) {
						return;
					}

					console.log('onUpdateActionButtons: ' + stateName, args);
					var hookiscalled = false;
					this.pendingUpdate = false;

					// Call appropriate method
					var methodName = "onUpdateActionButtons_" + stateName;
					if (this[methodName] !== undefined) {
						console.log('Calling ' + methodName, args.args);
						if (this[methodName](args) == false) return;
						hookiscalled = true;
					}

					if (!hookiscalled) {
						switch (stateName) {
							case 'playerTurnAction':
								dojo.query(".hand > .card").addClass('active_slot');
								dojo.query(".tableau_" + this.player_color + " .tech.state_1.activatable").addClass('active_slot');
								dojo.query(".tableau_" + this.player_color + " .state_1.actionable").addClass('active_slot');


								if (args.can_play_role_first) {
									this.addActionButton('button_playrolefirst', _("Play Role first"), () => {
										this.ajaxClientStateAction('skipAction');
									});
								} else {
									this.addActionButton('button_skip', _("Skip Action"),
										() => this.confirmationDialog(_("Please confirm if you don't want to take any action this turn."), () => this.ajaxClientStateAction('skipAction')));
								}
								break;
							case 'playerTurnDiscard':
							case 'playerTurnPreDiscard':
								var hcards = dojo.query(".hand > .card");
								hcards.addClass('active_slot');
								dojo.query(".tableau_" + this.player_color + " .tech.state_1.activatable").addClass('active_slot');
								dojo.query(".tableau_" + this.player_color + " .tech.state_10.activatable").addClass('active_slot');
								this.addDoneButton();


								if (args.hand_size < hcards.length) {
									this.setDescriptionOnMyTurn(_('${you} must discard from your hand down to ${hand_size} cards'));
								};
								if (stateName == 'playerTurnPreDiscard') {
									this.addActionButton('button_wait', _("Wait"), () => this.ajaxClientStateAction('playWait'));
									this.addTooltip('button_wait', 
									     _("Now you can discard cards instead of waiting until end of turn. If you do your turn will end automatically."),
										 _("Click Wait to skip discarding now and do it after after player done following"), 1000);
								}

								break;
							case 'playerTurnSurvey':
							case 'playerTurnSurveyFollow':
							case 'client_playerTurnSurvey':
								dojo.query(".hand > .card_planet").addClass('active_slot');
								break;
							case 'playerTurnGameEnd':
								this.addActionButton('button_skip', _("End Game"), dojo.hitch(this, function() {
									this.ajaxClientStateAction('skipAction');
								}));
								break;
							case 'playerTurnRole':
								// hook
								break;
							case 'playerTurnFollow':
								var role = args.role;
								var div = this.createDiv("icon role " + role);
								if ((role != 'S' && role != 'R') || args.active_follower == this.player_id) {
									this.addImageActionButton('button_follow', _("Follow") + " " + div, () => {
										this.clientStateArgs.role = role;
										this.clientStateArgs.card = 'x_follow';
										this.clientStateArgs.choices = [];
										var pinfo = this.gamedatas.gamestate.args.pinfo[this.player_id];
										//console.log("pinfo", pinfo);

										// bureacracy
										var bureacracy = this.playerHasOnTableau("card_tech_3_97");

										if (bureacracy && (role != 'C' || role != 'W')) {
											this.setClientStateAction('client_selectBoostOrLeader', '', 0, pinfo);
										} else {
											this.setClientStateAction('client_selectBoost', '', 0, pinfo);
										}
									});
									var freedomOfTrade = this.playerHasOnTableau("card_tech_E_2");
									if (freedomOfTrade) {
										var as_role = '';
										if (role == 'P') {
											as_role = 'T';
										} else if (role == 'T') {
											as_role = 'P';
										}
										if (as_role) {
									
											var div = this.createDiv("icon role " + as_role);
											this.addImageActionButton('button_follow2', _("Follow as ") + " " + div, () => {
												this.clientStateArgs.role = as_role;
												this.clientStateArgs.card = 'x_follow';
												this.clientStateArgs.choices = [];
												var pinfo = this.gamedatas.gamestate.args.pinfo[this.player_id];
												this.setClientStateAction('client_selectBoost', '', 0, pinfo);

											});
										}
									}

								} else {
									var tip = _("Cannot follow out of order for this role, will wait until your turn");
									this.addImageActionButton('button_follow_red', _("Follow") + " " + div, () => {
										this.showMessage(tip, "info");
										this.ajaxClientStateAction('playWait');
									}, 'red', tip);
								}
								this.addActionButton('button_dissent', _("Dissent"), dojo.hitch(this, function() {
									this.ajaxClientStateAction('playDissent');
								}));
								if (this.prefs[150].value == 2) { // auto dissent with timer
									if (args.pinfo[this.player_id].can_follow == false) {
										var timeout = parseInt( Math.random()*10)+10; 
										this.addButtonTimer('button_dissent', undefined, timeout);
									}
								}
								if (args.active_follower != this.player_id) {
									this.addActionButton('button_wait', _("Wait"), dojo.hitch(this, function() {
										this.ajaxClientStateAction('playWait');
									}));

									if ($('button_follow_red')) {
										this.addTooltip('button_wait', _("Cannot follow out of order for this role, will wait until your turn"),
											_("Click Wait to stop the player turn timer and wait for your turn"), 1000);
									} else

										this.addTooltip('button_wait', _("Now you can make your move out of order since it won't interfere with other player. However you can press Wait and wait for your turn"),
											_("Click Wait to stop the player turn timer and wait for your turn"), 1000);
								}
								break;
							case 'client_selectRoleCard':
								dojo.query(".board  .card_role:not(.card_bottom):last-child").addClass('active_slot');
								break;
							case 'client_selectCardToRemove':
								dojo.query(".hand > .card").addClass('active_slot');
								dojo.addClass(this.clientStateArgs.card, 'active_slot');
								this.addDoneButton();

								var rules = this.clientStateArgs.unprocessed_choices.replace("!", "");
								var total = rules.length;
								if (total == 0) {
									this
										.setDescriptionOnMyTurn(_('Selected cards will be removed permanently, press Done to confirm'));
								} else if (rules[0] == 'e') {
									this.setDescriptionOnMyTurn(_('Select up to ${total} card(s) to remove from the game'), {
										total: total
									});
									if (!dojo.hasClass(this.clientStateArgs.card, 'toremove') && !dojo.hasClass(this.clientStateArgs.card, 'permanent')) {
										this.addActionButton('button_self', _("Remove Itself"), dojo.hitch(this, function() {
											dojo.addClass(this.clientStateArgs.card, 'toremove');
											this.commitOperationAndSubmit('e', this.clientStateArgs.card);
										}));
									}
								} else if (rules[0] == 'E') {
									this.setDescriptionOnMyTurn(_('Select any number of cards to remove from the game'), {
										total: total
									});
								}
								break;
							case 'client_selectPlanetToProduce':
								// var planets = this.gamedatas.gamestate.args.planets;
								// console.log(planets);
								// resource slot on planet
								dojo.query(".tableau_" + this.player_color + " .planet.state_1.has_slots").addClass('active_slot');
								dojo.query(".tableau_" + this.player_color + " .tech.has_slots").addClass('active_slot');

								this.setDescriptionOnMyTurn(_('Select a planet to produce: ${boost_count} left'), {
									boost_count: this.clientStateArgs.unprocessed_choices.length
								});
								this.addActionButton('random', _('All Random'), () => {
									// produce on random planets
									var num = this.clientStateArgs.unprocessed_choices.length;
									for (var i = 0; i < num; i++) {
										if (!this.commitOperation('p', 'x_random', 'n', 'x')) {
											break;
										}
									}
									this.actionPromptAndCommit();
								});
								this.addDoneButton();
								break;
							case 'client_selectResourceToTrade':
								// resource on planet and cards
								var ress=dojo.query(".tableau_" + this.player_color + " .resource.state_2");
								ress.addClass('active_slot');

								// weapon emporium
								if (this.getFirstChild(".tableau_" + this.player_color + " #card_tech_2_74")) {
									dojo.query("#tableau_fighter_" + this.player_color + " .fighter_F:last-child").addClass('active_slot');
									dojo.query("#tableau_" + this.player_color + " .fighter_F").addClass('active_slot');

									dojo.query(".hand_" + this.player_color + " .card.as_fighter_F").addClass('active_slot');
								}
								// hand card as resources								
								dojo.query(".hand_" + this.player_color + " .card.as_resource").addClass('active_slot');

								this.setDescriptionOnMyTurn(_('Select a resource to trade: ${boost_count} left'), {
									boost_count: this.clientStateArgs.unprocessed_choices.length
								});
								
								if (ress.length > 0)
									this.addActionButton('random', _('All Random'), () => {
										// produce on random planets
										var num = this.clientStateArgs.unprocessed_choices.length;
										var allres = dojo.query(".tableau_" + this.player_color + " .resource.active_slot");
										this.shuffleArray(allres);
										for (var i = 0; i < num; i++) {
											if (i >= allres.length) break;
											var resource = allres[i].id;
											var planet = allres[i].parentNode.id;
											if (this.commitOperation('t', planet, resource)) {
												dojo.removeClass(resource, 'active_slot');
												this.placeTokenLocal(resource, 'setaside_' + this.player_color, 1)
											}
										}
										this.actionPromptAndCommit();
									});
								

								this.addDoneButton();
								break;
							case 'client_selectResourceType':
								// resource on planet
								//dojo.query(".stock_resource .resource").addClass('active_slot');
								//debugger;
								var resources = ['w', 'f', 's', 'i'];
								var handler = (restype) => {
									this.commitOperationAndSubmit('y', restype);
								}
								if (this.gamedatas.gamestate.args.restypes) {
									resources = this.gamedatas.gamestate.args.restypes;
									for (var i in resources) {
										var r = resources[i];
										if (r == 'n') { // any
											resources = ['w', 'f', 's', 'i'];
											break;
										}
									}
								}
								if (this.gamedatas.gamestate.args.buthandler) {
									handler = this.gamedatas.gamestate.args.buthandler;
								}
								for (var i in resources) {
									var r = resources[i];
									var div = this.createDiv("resource resource_" + r);
									this.addImageActionButton('button_' + r, div, (event) => {
										var id = event.currentTarget.id;
										dojo.stopEvent(event);
										var restype = id.replace("button_", "");
										handler(restype);
									});
								}

								if (resources.length == 1) {
									// auto
									setTimeout(() => handler(resources[0]), 300);
								}


								break;
							case 'client_selectActionAttack':
								this.setDescriptionOnMyTurn(_('Select a planet to attack'));

								dojo.query(".tableau_" + this.player_color + " .card_planet.state_0").addClass('active_slot');

								if (this.playerHasOnTableau('card_tech_E_38')) {
									dojo.query(".tableau" + " .card_planet.state_1").addClass('active_slot');
									dojo.query(".tableau_" + this.player_color + " .card_planet.state_1").removeClass('active_slot');
								}

								if (this.clientStateArgs.card == 'card_tech_E_36') { // annex
									dojo.query(".tableau" + " .card_planet.state_0").addClass('active_slot');
									dojo.query(".tableau_" + this.player_color + " .card_planet.state_0").removeClass('active_slot');
								}

								if (this.clientStateArgs.choices.length > 0) {
									this.setDescriptionOnMyTurn(_('Select another planet to attack'));
									this.addActionButton('button_skip', _("Skip Attack"), (e) => {
										this.commitOperationAndSubmit('a', "skip");// XXX check if works on server
									});
								}
								break;
							case 'client_selectFaceDownPlanetSettleOnly':
								dojo.query(".tableau_" + this.player_color + " .card_planet.state_0").addClass('active_slot');
								var message = _('Select a planet to settle');
								this.setDescriptionOnMyTurn(message);
								this.addDoneButton();
								break;
							case 'client_selectFaceDownPlanet':
								dojo.query(".tableau_" + this.player_color + " .card_planet.state_0").addClass('active_slot');
								if (this.clientStateArgs.action == 'playAction') {
									var rule = this.clientStateArgs.unprocessed_choices[0];
									var message = _('${You} may select planet to settle or add colonies');
								
									switch (rule) {
										//.
										case 's': // settle or +1 colony
											this.addActionButton('button_settle', _("Settle 1 Planet"), dojo.hitch(this,
												function() {
													this.setClientStateAction('client_selectFaceDownPlanetSettleOnly');
												}));
											this.addImageActionButton('button_colony', _("+1 Colony ") + this.createDiv("icon colonize"), dojo.hitch(this,
												function() {
													this.clientStateArgs.unprocessed_choices = "c";
													this.setClientStateAction('client_selectFaceDownPlanetColonize',
														_('Select a planet to add a colony'));
												}), 'blue');
											break;
										case 'S':// settle or skip
											var message = _('${You} may select planet to settle or skip');
											this.addActionButton('button_skip', _("Skip Settle"), dojo.hitch(this,
												function() {
													this.commitOperationAndSubmit('S', "skip");
												}));
											break;
										default:
											break;
									}
									this.setDescriptionOnMyTurn(message);


								}
								this.addDoneButton();
								break;
							case 'client_selectTechCard':
								//debugger;
								var totalResearch = args.perm_boost_count + args.hand_boost_count;
								if (this.gamedatas.gamestate.args.extra) {
									totalResearch = args.boost_rem;
								}
								
								var techs = this.gamedatas.gamestate.args.tech;
								var settledTypes = 0;

								for (tech in techs) {
									var info = techs[tech];


									if (info.canattack || info.b <= totalResearch) {
										if (tech.startsWith('card_fleet_i')) {
											dojo.query(".tableau_" + this.player_color + " .fleet_basic").addClass('active_slot');
										} else
											dojo.addClass(tech, 'active_slot');
									}
									settledTypes++;

								}

								if (dojo.query(".tech.active_slot").length == 0) {
									if (settledTypes == 0)
										this.showError(_("You cannot research any technologies, you need at least one settled planet"));
									else if (totalResearch < 3)
										this.showError(_("You cannot research any technologies, you need at least 3 reasearch icons or fleet"));

									else if (!this.gamedatas.gamestate.args.extra)
										this.showError(_("You cannot research any technologies"));
									this.setDescriptionOnMyTurn(_('${You} cannot research'));
									if (this.gamedatas.gamestate.args.extra) {
										//
									} else if (this.clientStateArgs.card != 'x_follow') {
										this.addDoneButton(_("Forfeit Technology card, gain Role card only"));
									} else {
										this.addDoneButton(_("Forfeit Technology card"));
									}

								} else {
									this.expandTech(true, ".tech.active_slot:not(.fleet_basic)", "selection_area");
								}
								if (this.gamedatas.gamestate.args.extra) {
									this.setMainTitle(this.getTokenName(this.gamedatas.gamestate.args.card) + ":", 'before');
								}
								break;
							case 'client_selectFaceDownPlanetColonize':

								dojo.query(".tableau_" + this.player_color + " .card_planet.state_0").addClass('active_slot');
								dojo.query(".tableau_" + this.player_color + " .coloniseable").addClass('active_slot');
								this.addDoneButton();
								break;
							case 'client_selectBoostOrLeader':
								var info = this.getTokenDisplayInfo(this.clientStateArgs.card);
								var laction;
								if (info == null && this.clientStateArgs.card == 'x_follow') {
									laction = this.clientStateArgs.role;
								} else {
									laction = info.l;
								}
								//console.log(laction, this.clientStateArgs);

								this.setDescriptionOnMyTurn(_('${You} may choose leader bonus or select more role boost'));
								switch (laction) {
									case 'a':
									case 'W':
										this.addActionButton('button_attack', "<span class='yellow'>Leader:</span> Attack 1 Planet", dojo.hitch(this, function() {
											this.clientStateArgs.unprocessed_choices = 'a';
											this.setClientStateAction('client_selectActionAttack');
										}));
										break;
									case 's':
									case 'C':
										this.addActionButton('button_settle', "<span class='yellow'>Leader:</span> Settle 1 Planet", dojo.hitch(this, function() {
											this.clientStateArgs.unprocessed_choices = 's';
											this.setClientStateAction('client_selectFaceDownPlanetSettleOnly');
										}));
										break;
									default:
										if (info != null && info.r == 'P/T') {
											this.setDescriptionOnMyTurn(_('${You} must select between produce and trade'));
											this.addImageActionButton('button_produce', this.createDiv("icon produce"), dojo.hitch(this,
												function() {
													this.clientStateArgs.role = 'P';
													this.setClientStateAction('client_selectBoost');
												}));
											this.addImageActionButton('button_trade', this.createDiv("icon trade"), dojo.hitch(this, function() {
												this.clientStateArgs.role = 'T';
												this.setClientStateAction('client_selectBoost');
											}));
											dojo.destroy('button_skip');
											this.addCancelButton();

										} else {
											this.setDescriptionOnMyTurn(_('${You} may select more role boost'));
										}
										break;
								}
							// fallthrough
							case 'client_selectBoost':

								var icon = this.clientStateArgs.role;
								var hand_boost = args['hand_boost'][icon];
								var possible_boost = 0;
								if (hand_boost) {
									for (var card in hand_boost) {
										if ($(card)) dojo.addClass(card, 'active_slot');
										possible_boost++;
									}
								}

								args.perm_boost_count = args['perm_boost_num'][icon];

								if (!args.hand_boost_count) {
									if (this.clientStateArgs.card.startsWith('x_')) {
										args.hand_boost_count = 0;
									} else {
										args.hand_boost_count = 1; // role itself
										//possible_boost++;
									}
								}
								if (icon == 'C') {
									args.perm_boost_count = 0;
								} else if (getPart(this.clientStateArgs.card, 2) == 'bottom') {
									args.perm_boost_count++; // Empty stack gives 1 boost except for Colonize
								}

								//console.log(this.clientStateArgs.card);

								if (stateName != 'client_selectBoostOrLeader') {
									this.setDescriptionOnMyTurn(_('${You} may select more role boost'));
								}
								var follow = this.clientStateArgs.card == 'x_follow';
								var total = args.perm_boost_count + args.hand_boost_count;
								var messagex = '${icon} x ${total}'; // when 1 - keep number 1 there
								if (total == 2)
									messagex = '${icon}${icon}';
								else if (total == 3)
									messagex = '${icon}${icon}${icon}';
								else if (total == 4)
									messagex = '${icon}${icon}${icon}${icon}';
								if (possible_boost) 
									messagex += ' '+('(unused boost ${remaining_boost})');
					
								var icondiv = this.format_string_recursive(messagex, {
									icon: this.createDiv("icon " + this.clientStateArgs.role),
									total: total,
									remaining_boost: possible_boost
								});
								args.possible_boost=possible_boost;
								if (this.clientStateArgs.role == 'S') {
									var thorough_survery = this.playerHasOnTableau('card_tech_E_24')
									var lookup = total - 1;

									if (!follow) lookup++;


									if (thorough_survery && lookup - 2 >= 2) {
										var div = _("Execute with Thorough Survey ") + " " +  icondiv;
										var but = this.addImageActionButton('button_done_' + total + "_ts", div, 'onExecuteRole', 'blue');
									    var ll=lookup-2;
										this.addTooltip(but.id, _('Draw') + " " + ll + " - " + _('Keep 2'), '', 1000);
									}
									var div = _("Execute Role ") + " " +  icondiv;
									var col = 'blue';
									if (lookup <= 0) col = 'red';
									var but = this.addImageActionButton('button_done_' + total, div, 'onExecuteRole', col);
									this.addTooltip(but, _('Draw') + " " + lookup + " - "+_('Keep 1'), '',1000);
					

								} else if (this.clientStateArgs.role != 'P/T') {
									var div = _("Execute Role ") + " " +  icondiv;
									this.addImageActionButton('button_done_' + total, div, 'onExecuteRole', 'blue');
								}
								break;
							case 'client_confirm':
								this.addDoneButton('Confirm');
								break;
						}
					}

					if (this.on_client_state && !this.clientStateArgs.nocancel) {
						this.addCancelButton();
					}
					if (this.clientStateArgs.boost) {
						var str = this.clientStateArgs.boost;
						var choices_arr = str.trim().split(' ');
						for (var i = 0; i < choices_arr.length; i++) {
							var element = choices_arr[i];
							if ($(element)) dojo.addClass(element, 'selected');
							else
								console.error("Unknown node for selection: ", element);
						}
					}
					if (this.clientStateArgs.choices) {
						var choices_arr = this.clientStateArgs.choices;
						for (var i = 0; i < choices_arr.length; i++) {
							var element_a = choices_arr[i];
							var operands = element_a;
							var element = operands.length > 1 ? operands[1] : operands[0];
							if ($(element)) dojo.addClass(element, 'selected');
							else if (element == 'x' || element == 'z') {
								// skip
							} else
								console.error("Unknown node for selection", element, choices_arr);
						}
					}
					if (this.clientStateArgs.card && $(this.clientStateArgs.card)) dojo.addClass(this.clientStateArgs.card, 'selected');
					// this.updateBreadCrumbs(this.on_client_state);

				},
				// /////////////////////////////////////////////////
				// // Utility methods
				/*
				 * 
				 * Here, you can defines some utility methods that you can use everywhere in your javascript script.
				 * 
				 */
				getPlaceRedirect: function(token, tokenInfo) {
					if (!tokenInfo) {
						return {
							location: 'dev_null',
							inlinecoords: false,
							temp: true
						};
					}
					var location = tokenInfo.location;
					var result = {
						location: location,
						inlinecoords: false
					};
					if (location.startsWith('discard_planets')) {
						// nothing
					} else if (location == 'discard_display') {
						result.createOn = 'discard_' + this.player_color;
					} else if (location == 'deck_display') {
						result.createOn = 'deck_' + this.player_color;
					} else if (location.startsWith('discard_' + this.player_color)) {
						result.temp = true;
					} else if (location.startsWith('discard')) {
						result.temp = true;
					} else if (location.startsWith('dev_null')) {
						result.temp = true;
					} else if (location.startsWith('tableau') && token.startsWith('card')) {
						// result.location = token + "_group";
						result.createOn = location;
						
						var color=getPart(location,1);
						var sep='';
						if (token.startsWith('scenario')) {
							 sep = "sep_scenario_"+color;
						} else {
							var cardtype = getPart(token, 1);
							if (cardtype == 'planet' ) {
								if (tokenInfo.state == 0)
									cardtype += '0';
								else {
									var ptype = this.getRulesFor(token, 't');
									if ($("sep_" + cardtype + ptype + "_" + color))
										cardtype += ptype;
								}
							}
							sep = "sep_" + cardtype + "_" + color;
						}
						result.location = sep;
						result.relation = "before";
						if ($(token) && $(token).parentNode.id == location) {
							result.noa = true;// no animation already there
						}
					} else if (location.startsWith('tableau') && token.startsWith('fighter')) {
						var type = getPart(token, 1);
						if (type=='j1') type='F';
						result.location = 'tableau_fighter_' + type + "_" + getPart(location, 1);
						result.createOn = 'stock_fighter';
					} else if (token.startsWith('scenario')) {
						result.relation = 3;
						result.createOn = 'limbo';
					} else if (location.startsWith('tableau') && token.startsWith('vp')) {
						result.location = 'tableau_vp_' + getPart(location, 1);
					} else if (location.startsWith('supply_tech')) {
						// result.location = 'tech_display';
					} else if (location.startsWith('card_planet') && token.startsWith('card')) {
						result.createOn = location;
					} else if (location.startsWith('hand_' + this.player_color)) {
						result.createOn = 'deck_' + this.player_color;
						//if ($(token)) dojo.removeStyle(token,'opacity')
					} else if (location.startsWith('hand_')) {
						result.temp = true;
						result.location = 'discard_' + getPart(location, 1);
						//if ($(token)) dojo.removeStyle(token,'opacity')
					} else if (location.startsWith('stock_fighter')) {
						result.inlinecoords = true;
						var size = 32;
						var box = dojo.marginBox(location);
						result.x = Math.floor((Math.random() * (box.w - size))) - size / 2;
						result.y = Math.floor((Math.random() * (box.h - size))) - size / 2;
						// var d = Math.floor( (Math.random() * 360));
						// result.transform = "rotate("+d+"deg)";
					} else if (location.startsWith('stock')) {
						result.inlinecoords = true;
						var size = 32;
						var box = dojo.marginBox(location);
						result.x = Math.floor((Math.random() * (box.w - size)));
						result.y = Math.floor((Math.random() * (box.h - size)));
					} else if (location.startsWith('deck')) {
						result.temp = true;
					}
					if (token.startsWith('card_')) {
						result.onclick = 'onCard';
					} else if (token.startsWith('fighter_')) {
						result.onclick = 'onFighter';
					} else if (token.startsWith('resource_')) {
						result.onclick = 'onResource';
					}
					return result;
				},
				getJemImg: function(colorId) {
					var jem = "<img class='imgtext' src='" + g_themeurl + "img/mainsite/status-" + colorId + ".png' alt='" + colorId + "' />";
					return jem;
				},
				/** @override */
				change3d: function(xaxis, xpos, ypos, zaxis, scale, enable3d, clear3d) {
					this.inherited(arguments);
					this.adjust3D();
				},
				adjust3D: function(e) {
					if (this.isSpectator) return;
					var hand = 'hand_area';

					setTimeout(dojo.hitch(this, function() {
						var control3dmode3d = this.control3dmode3d;
						//console.log("3d mode: " + control3dmode3d);
						if (control3dmode3d) {
							// Only executed in 3d mode
							dojo.place(hand, 'page-title', 'last');
						} else {
							dojo.place(hand, 'selection_area', 'after');
						}
					}), 300);
				},
				/**
				 * draws icon in the hand area
				 * @param {*} num 
				 * @param {*} place 
				 * @param {*} player_color 
				 * @param {*} off 
				 */
				drawHandIcon: function(num, place, player_color, off) {
					if (typeof off == 'undefined') off = -1;
					for (var i = 0; i < num; i++) {
						var deg = (i + off) * 20;
						dojo.place('<div class="cardicon" style="border-color: #' + player_color +
							';transform-origin: bottom center;transform: rotate(' + deg + 'deg)"></div>', place);
					}
				},
				cancelLocalStateEffects: function() {
					this.inherited(arguments);
					this.expandTech(false);
					dojo.query('.claimed').removeClass('claimed');
					dojo.query('.thething .expanded').removeClass('expanded');
				},
				setClientStateCustom: function(state, descr, args, onHandlers) {

					if (onHandlers) {
						for (var met in onHandlers) {
							var handler = onHandlers[met];
							this[met + '_' + state] = handler;
						}
					}
					this.setClientStateAction(state, descr, 0, args);
				},

				shuffleArray: function(array) {
					//https://stackoverflow.com/questions/2450954/how-to-randomize-shuffle-a-javascript-array
					for (let i = array.length - 1; i > 0; i--) {
						const j = Math.floor(Math.random() * (i + 1));
						[array[i], array[j]] = [array[j], array[i]];
					}
				},

				/** @Override */
				format_string_recursive: function(log, args) {
					try {
						//console.trace("format_string_recursive(" + log + ")", args);
						if (args.log_others !== undefined && this.player_id != args.player_id) {
							log = args.log_others;
						}

						if (log && args && !args.processed) {
							args.processed = true;

							if (args.you)
								args.you = this.divYou(); // will replace ${You} with colored version

							args.You = this.divYou(); // will replace ${You} with colored version

							var keys = ['token_name', 'place_name', 'action_name',
								'token_divs', 'token_names', 'token_div', 'place_from'];
							for (var i in keys) {
								var key = keys[i];
								// console.log("checking " + key + " for " + log);
								if (typeof args[key] != 'undefined') {
									if (key == 'token_divs' || key == 'token_div') {
										var list = args[key].split(",");
											var res = "";
										for (var l = 0; l < list.length; l++) {
											var name = this.getTokenDivForLogValue('token_div', list[l]);
											res += name + " ";
										}
										if (res) args[key] = res;
										continue;
									}
									if (key == 'token_names') {
										var list = args[key].split(",");
										var res = "";
										for (var l = 0; l < list.length; l++) {
											var name = this.getTokenDivForLogValue('token_name', list[l]);
											res += name + " ";
										}
										res = res.trim();
										if (res)
											args[key] = res;
										continue;
									}
									if (typeof args[key] == 'string') {
										if (this.getTranslatable(key, args) != -1) {
											continue;
										}
									}
									var res = this.getTokenDivForLogValue(key, args[key]);
									if (res) args[key] = res;
								}
							}
						}
					} catch (e) {
						console.error(log, args, "Exception thrown", e.stack);
					}
					return this.inherited(arguments);
				},
				getTokenDivForLogValue: function(key, value) {
					// ... implement whatever html you want here
					var token_id = value;
					if (value===undefined) {
						console.trace(key);
					}
					if (token_id == null) { return "? " + key; }
					if (key.endsWith('name') || key == 'place_from') {
						var name = this.getTokenName(token_id);
						var div = "<span>'" + name + "'</span>";
						return div;
					}
					var item_type = getPart(token_id, 0);
					switch (item_type) {
						case 'fighter':
							if (!$(token_id)) {
								this.createToken(token_id, this.gamedatas.tokens[token_id], 'stock_fighter');
							}
							break;
						case 'card':
							var name = this.getTokenName(token_id);
							var div = "<span>'" + name + "'</span>";
							return div;
						default:
							break;
					}
					return this.divInlineToken(token_id);
				},
				updateDisplayInfo: function(info) {
					var tt = getFirstParts(info.key, 2);
					//					console.log(tt, info.tokenKey);
					switch (tt) {
						case 'card_planet':
							var args = { w: info.w, v: info.v, c: info.c };
							var type = info.t;
							args.planet_type = this.getTr(this.gamedatas.materials.planet_types[type]);
							var icons = info.i ? info.i : '';
							if (info.h)
								icons += 'h';

							if (icons) {
								var icon_names = [];
								for (j = 0; j < icons.length; j++) {
									var c = icons[j];
									icon_names.push(this.getTr(this.gamedatas.materials.icons[c]));
								}
								args.icons = icon_names.join(", ");
							} else {
								args.icons = this.getTr('None');
							}

							var resources = info.slots ? info.slots: '';
							if (resources) {
								resource_names = [];
								for (j = 0; j < resources.length; j++) {
									c = resources[j];
									resource_names.push(this.getTr(this.gamedatas.materials.resources[c]));
								}
								args.resources = resource_names.join(", ");
								info.type += " has_slots slots_resources";
							} else {
								args.resources = this.getTr('None');
							}

							var mcost = info.w;
							var ftype = 'F';
							if (mcost === 'D' || mcost === 'B') {
								ftype = mcost;
								mcost = 1;

							}

							var ship = this.gamedatas.materials.icons[ftype];
							args.ship = this.getTr(ship);
							args['w'] = mcost;

							if (args.c == -1) {
								args.c = "&empty;"
							}


							var message = this.format_string_recursive(
								('Planet Type: ${planet_type}<p>  Warfare Cost: ${w} [${ship}] <p>  Colonize Cost: ${c}<p><br> Boost Symbols: ${icons}<p>  Influence: ${v}<p>  Production: ${resources}'),
								args
							);
							var orig_toolip = info.tooltip;

							info.tooltip = message;
							if (orig_toolip) {
								info.tooltip += "<p><br>";
								if (info.a) {
									info.tooltip += this.createSpanHtml(this.getTr('Action:'), 'yellow');
									info.tooltip += " ";
								}
								info.tooltip += this.getTr(orig_toolip);
							}



							var message = this.format_string_recursive(
								_('Planet Type: ${planet_type}<p>  Warfare Cost: ${w} [${ship}]<p>  Colonize Cost: ${c}'),
								args
							);
							info.tooltip_back = message;

							break;
						case 'card_tech':

							// add translated planet type
							var args = {};
							var req = info.p;
							var ptype = info.t;


							args['tech_type'] = this.gamedatas.materials['planet_types'][ptype];
							args['prereq'] = this.gamedatas.materials['planet_cost'][req];

							// add icons for html
							var cost = info['b']; // research cost
							var icons = info.i ? info.i: ''; // icons
							if (info.h)
								icons += 'h';

							if (icons) {
								var icon_names = [];
								for (j = 0; j < icons.length; j++) {
									var c = icons[j];
									icon_names.push(this.getTr(this.gamedatas.materials.icons[c]));
								}
								args.icons = icon_names.join(", ");
							} else {
								args.icons = _('None');
							}

							var resources = info.slots ? info.slots : '';
							if (resources) {
								resource_names = [];
								for (j = 0; j < resources.length; j++) {
									c = resources[j];
									resource_names.push(this.getTr(this.gamedatas.materials.resources[c]));
								}
								args.resources = resource_names.join(", ");
								//info.type += " has_slots slots_resources";
							} else {
								args.resources = _('None');
							}


							args['i18n'] = ['name', 'tech_type', 'action'];
							args['v'] = info['v'];
							if (info['bm']) {
								var mcost = info['bm'][0];
								ship = this.gamedatas.materials['icons'][info['bm'][1]];
								args['rcost'] = { //
									'log': _('${b} Research or ${mcost} ${ship}'),
									'args': { //
										'b': cost,
										'mcost': mcost, //
										'i18n': ['ship'],
										'ship': ship
									}
								}; //

							} else {
								args['rcost'] = { //
									'log': _('${b} Research'),
									'args': { 'b': cost } //
								};

							}
							var message = this.format_string_recursive(
								_('${tech_type} Technology<p>Planet Prerequisite: ${prereq}<p>Cost: ${rcost}<p><br> Boost Symbols: ${icons}<p>  Influence: ${v}'),
								args);
							var orig_toolip = info.tooltip;

							info.tooltip = message;
							if (orig_toolip) {
								info.tooltip += "<p><br>";
								if (info.side) {
									info.tooltip += this.createSpanHtml(_('Permanent Technology'), 'yellow');
									info.tooltip += "<br>";
								}
								if (info.a) {
									info.tooltip += this.createSpanHtml(_('Action:'), 'yellow');
									info.tooltip += "<br>";
								}

								info.tooltip += this.getTr(orig_toolip);
							}
							if (info.side) {
								info.tooltip += "<p>";
								info.tooltip += this.createSpanHtml(_('Reverse Side:'), 'yellow');
								info.tooltip += " ";
								var flip = info.flip;
								var reverseInfo = this.gamedatas.token_types[flip];
								info.tooltip += this.getTr(reverseInfo.name); 
								info.tooltip += "<br>";
								// add type
								info.type += " side_"+info.side;
							}

							break;
						case 'card_fleet':
							if (info.key.startsWith('card_fleet_b')) {
								info.tooltip += "<p>";
								info.tooltip += this.createSpanHtml(_('Reverse Side:'), 'yellow');
								info.tooltip += "<br>";
								var reverseInfo = this.getTokenDisplayInfo('card_fleet_i');
								info.tooltip += this.getTr(reverseInfo.name); 
								info.tooltip += "<p>";
								info.tooltip += this.getTr(reverseInfo.tooltip);
							}
							break;
						default:

							break;
					}


					if (info.rulings) {
						info.tooltip += "<p>";
						info.tooltip += this.createSpanHtml(_('Note:'), 'yellow');
						info.tooltip += "<br><i class='errnote'>";
						info.tooltip += this.getTr(info.rulings);
						info.tooltip += "</i>";
					}
				},
				getTooptipHtmlForTokenInfo: function(tokenInfo) {
					var main = this.getTooptipHtml(tokenInfo.name, tokenInfo.tooltip, tokenInfo.imageTypes, "<hr/>");
					var token = tokenInfo.tokenKey;
					// see also updateDisplayInfo where main tooltip build is going
					if (token.startsWith('card_planet')) {
						if (this.showPlanetBackTooltip(tokenInfo.key)) {
							main = this.getTooptipHtml(_("Unknown Planet"), tokenInfo.tooltip_back, tokenInfo.imageTypes + " state_0", "<hr/>");
						}
					}
					if (token.startsWith('card_tech')) {
						var node = $(tokenInfo.key);
						var parentNode = node.parentNode;
						if (parentNode.id.startsWith('supply_tech')) {
							//console.log('tooltips ' + tokenInfo.key + " " + parentNode.id);
							return null;
						}
					}

					var action = tokenInfo.tooltip_action;
					if (action && main !== null) {
						main += "<br/>" + this.getActionLine(action);
					}
					return main;
				},
				showPlanetBackTooltip: function(token) {
					var node = $(token);
					var parentNode = node.parentNode;
					if (dojo.hasClass(node, "state_0")) {
						if (dojo.hasClass(parentNode, "own")) return false;
						return true;
					}
					return false;
				},
				strRepeat: function(comm, multiplier) {
					if (multiplier === undefined)
						multiplier = 1;
					if (multiplier == 1) return comm;
					var res = "";
					for (var i = 0; i < multiplier; i++) {
						res += comm;
					}
					return res;
				},
				prependOperation: function(comm, multiplier) {
					var rules = this.strRepeat(comm, multiplier);
					var subrules = rules.split('/');
					if (subrules.length <= 1) {
						this.clientStateArgs.unprocessed_choices = rules + this.clientStateArgs.unprocessed_choices;
						return this.clientStateArgs.unprocessed_choices;
					}
					var subrules_new = [];
					for (var i = 0; i < subrules.length; i++) {
						var subrule = subrules[i] + this.clientStateArgs.unprocessed_choices;
						subrules_new.push(subrule);
					}

					this.clientStateArgs.unprocessed_choices = subrules_new.join("/");
					return this.clientStateArgs.unprocessed_choices;
				},

				consumeOperation: function(op) {
					var rules = this.clientStateArgs.unprocessed_choices;
					if (!rules) return false;
					var subrules = rules.split('/');
					var subrules_new = [];
					var found = false;
					for (var i = 0; i < subrules.length; i++) {
						var subrule = subrules[i];
						if (subrule.startsWith(op)) {
							subrules_new.push(subrule.substring(1));
							found = true;
						}
					}
					if (!found) return false;
					this.clientStateArgs.unprocessed_choices = subrules_new.join("/");
					return true;
				},
				commitOperation: function(ops, id, extra1, extra2) {
					if (id === undefined)
						id = 'x';
					var operation = [ops, id];
					if (extra1 !== undefined && extra1 !== null) {
						operation.push(extra1);
						if (extra2 !== undefined) operation.push(extra2);
					}

					for (var c = 0; c < ops.length; c++) {
						var op = ops.charAt(c);
						var succ = this.consumeOperation(op);
						if (succ) {
							operation[0] = op;
							this.clientStateArgs.choices.push(operation);
							//console.log("selecting "+op+" "+id);
							return true;
						}
					}
					this.showMoveUnauthorized();
					return false;
				},

				commitOperationAndSubmit: function(ops, id, extra, animation_handler) {
					if (this.commitOperation(ops, id, extra)) {
						if (animation_handler !== undefined)
							animation_handler();
						this.actionPromptAndCommit();
						return true;
					}
					return false;
				},
				commitOperationAndAction: function(ops, id, extra, animation_handler) {
					if (this.commitOperation(ops, id, extra)) {
						if (animation_handler !== undefined)
							animation_handler();
						return this.actionPrompt();
					}
					return false;
				},
				showMoveUnauthorized: function() {
					console.error("This move is not authorized now");
					console.trace("trace");
					this.showMessage(__("lang_mainsite", "This move is not authorized now"), "error");
				},
				addCancelButton: function() {
					if (!$('button_cancel')) {
						this.addActionButton('button_cancel', _('Cancel'), () => this.cancelLocalStateEffects(), null, null, 'red');
					}
				},
				addDoneButton: function(text) {
					if ($('button_done')) {
						dojo.destroy('button_done');
					}
					if (typeof text == 'undefined') text = _("Done");
					var cs = this.clientStateArgs.unprocessed_choices;

					this.addActionButton('button_done', text, dojo.hitch(this, function() {
						var command = this.clientStateArgs.unprocessed_choices[0];
						if (command == '!' || command == '>' || !command) {
							this.ajaxClientStateAction();
						} else {
							console.log(this.clientStateArgs.unprocessed_choices);
							this.confirmationDialog(
								_('You have more operations you can do for this action. Are you sure you want to execute it partially?'),
								() => {
									this.clientStateArgs.choices.push(['x']);
									this.ajaxClientStateAction();
								});
						}
					}))
					if (cs && (cs.length == 0 || cs[0] == '!')) {
						dojo.addClass('button_done', 'blinking');
					}
				},
				zeroBoostPrompt: function(leader) {
					var message;
					if (leader) message = _('You have no boost or not enough boost, this will only gain you a role card. Proceed?');
					else message = _('You have no boost or not enough boost, that will be no-op. Proceed?');
					this.confirmationDialog(
						message,
						() => this.ajaxClientStateAction()
					);
				},
				createDivFromInfo: function(type, id, extra, classes) {
					var info = this.getTokenDisplayInfo(type);
					return this.createDiv(info.imageTypes + " " + (classes ? classes: ''), id, extra);
				},
				createActionVisuals: function(rules) {
					var res = "";
					for (var i = 0; i < rules.length; i++) {
						var command = rules[i];
						var div = '';
						var name = this.gamedatas.materials.actions[command];
						switch (command) {
							case ' ':
							case '+':
							case '!':
								continue;
							case 'Z':
								div = dojo.create("span", { innerHTML: "&infin; ", style: { "font-size": "x-large" } }).outerHTML;
								break;
							case 'F':
							case 'D':
							case 'B':
								div += this.createDivFromInfo('fighter_' + command);
								break;
							case 'R':
								div += this.createDivFromInfo('iconperm_R');
								break;
							case 'p':
								div += this.createDivFromInfo('iconperm_P');
								break;
							case 't':
								div += this.createDivFromInfo('iconperm_T');
								break;
							case 'i':
								div += this.createDivFromInfo('vp_counter');
								break;
							case '>':
								if (i + 1 < rules.length) {
									div = this.createSpanHtml(SYM_RIGHTARROW);
								}
								break;
							case '.':
								div = this.createSpanHtml(_("Done"));
								break;
							case 'x':
								div = this.createSpanHtml(_("Skip"));
								break;
							default:
								if (name === undefined) name = "?" + command;
								div = this.createSpanHtml(_(name));
								break;
						}
						res += div;
					}
					return res;
				},
				canPay: function(action_rules) {
					var reqs = action_rules.split('>', 2);
					if (reqs.length > 1) {
						var paycost = reqs[0];
						if (this.payAll(paycost) < paycost.length) {
							return false;
						}
					}
					return true;
				},
				payAll: function(rules) {
					var same = 1;
					for (var i = 0; i < rules.length; i++) {
						var command = rules[i];
						if (i < rules.length - 1) {
							var lookup = rules[i + 1];
							if (lookup == command) {
								same++;
								continue;
							}
						}
						if (!this.payOne(command, false, same)) {
							return i;
						}
					}
					return rules.length;
				},
				payOne: function(rule, showError, num) {
					if (!num) num = 1;
					var querys;
					var queryc;
					switch (rule) {
						case 'Z':
							return 'x';
						case '!':
							return 'x';
						case 't':
							return 'x';

						case 'R':
							var args = this.gamedatas.gamestate.args;
							var total = parseInt(args.perm_boost_count + args.hand_boost_count) || 0;
							if (!total) total = parseInt(args.boost_rem) || 0;
							if (total >= num) {
								return 'x';
							}
							return null;
						case 'F':
						case 'D':
						case 'B':
				
							var querys = this.queryIds("#tableau_fighter_" + rule + "_" + this.player_color + " > *:not(.claimed)");
							var queryc = this.queryIds("#hand_" + this.player_color + " > .as_fighter_" + rule + ":not(.claimed)");
							var queryr = this.queryIds("#tableau_" + this.player_color + " > .has_rslots > .fighter_" + rule + ":not(.claimed)");

							var hand_icons_count = 0;
							for (var i = 0; i < queryc.length; i++) {
								var icons = this.getRulesFor(queryc[i], 'i');
								if (icons)
									hand_icons_count += (icons.split(rule).length - 1);
							}
							
				
							var total = querys.length + hand_icons_count + queryr.length;
							if (!showError) {
								return total >= num;
							}
							if (total == 0) {
								this.showError(_("Not enough to pay") + this.createActionVisuals(rule));
								return null;
							}
							var custom = queryc.length;
						

							var res = queryc;
							if (queryr.length >= 1) {
								res.push(queryr[0]);
							} else if (querys.length >= 1) {
								res.push(querys[0]);
							}
							//console.log("pay rule select", res);
							
							if (res.length == 1 && custom == 0) {
								var payment = res[0];
								dojo.addClass(payment, 'claimed');
								this.placeTokenLocal(payment, 'setaside_' + this.player_color);
								return payment;
							}
							return res;
						default:
							return null;
					}


				},
				playerHasOnTableau: function(card, state) {
					if (state !== undefined) {
						if (dojo.hasClass(card, "state_" + state))
							return false;
					}
					return this.getFirstChild(".tableau_" + this.player_color + "> #" + card);
				},
				playerHas: function(query) {
					return this.getFirstChild(".tableau_" + this.player_color + "> " + query);
				},
				getFirstChild: function(q, x) {
					var res = dojo.query(q);
					if (res.length == 0) return x;
					return res[0].id;
				},

				ajaxClientStateAction: function(action) {
					// massage data
					delete this.clientStateArgs.unprocessed_choices;
					this.clientStateArgs.choices_js = JSON.stringify(this.clientStateArgs.choices),
						delete this.clientStateArgs.choices;
					this.inherited(arguments);
				},

				actionPromptAndCommit: function() {
					var auto = this.actionPrompt();
					if (auto) {
						this.ajaxClientStateAction();
					}
					return false;
				},
				actionPrompt: function() {
					//debugger;
					if (this.clientStateArgs.unprocessed_choices === undefined) {
						this.showError("Bad state - no action");
						return;
					}
					if (!this.clientStateArgs.unprocessed_choices) return true;

					this.clientStateArgs.nocancel = false;
					var rules = this.clientStateArgs.unprocessed_choices;
					var subactions = rules.split('/');
					if (subactions.length > 1) {
						// multiple choice
						dojo.empty('generalactions');
						
					
						var pay = false;
						for (var i = 0; i < subactions.length; i++) {
							var sub = subactions[i];
							if (!sub) continue;
							if (sub == ".") {
								this.addDoneButton();
								continue;
							}
							var div = this.createActionVisuals(sub);
							var color = 'blue';
							if (!this.canPay(sub)) {
								color = 'red';
							}
							if (sub.includes('>')) {
								pay = true;
							}

							this.addImageActionButton('button_' + i, div, (e) => {
								var j = this.onClickSanity(e, false);
								if (this.clientStateArgs.actnum === undefined)
									this.clientStateArgs.actnum = j;
								this.clientStateArgs.unprocessed_choices = subactions[j];
								this.clientStateArgs.nocancel = false;
								this.actionPromptAndCommit();
							}, color);

						}
						if (pay)
							this.setDescriptionOnMyTurn(_('${You} must choose payment') + " ");
						else
							this.setDescriptionOnMyTurn(_('${You} must choose') + " ");

						dojo.destroy('button_skip');
						if (!this.clientStateArgs.nocancel)
							this.addCancelButton();

						return false;
					} 
						
					
					this.clientStateArgs.actnum = 0;

					var reqs = rules.split('>', 2);
					if (reqs.length > 1) {
						this.clientStateArgs.pay = true;
					}

					var command = this.clientStateArgs.unprocessed_choices[0];
					var lookup = this.clientStateArgs.unprocessed_choices[1];
					
					switch (command) {
						case 'l': // polictics
							this.setClientStateAction('client_selectRoleCard', _('Select a role card to gain'));
							break;
						case 'e': // trash/remove
						case 'E':
							this.setClientStateAction('client_selectCardToRemove');
							break;
						case 's':
						case 'S':
							this.setClientStateAction('client_selectFaceDownPlanet');
							break;
						case 'c':
							if (this.clientStateArgs.card == 'card_tech_E_21') {
								// colony ship require to select card to tuck
								this.setClientStateCustom('clSelectColony', _("Select a Colony card"), [], {
									onUpdateActionButtons: (args) => {
										var icon = 'C';
										var hand_boost = args['hand_boost'][icon];
										if (hand_boost) {
											for (var card in hand_boost) {
												if ($(card)) dojo.addClass(card, 'active_slot');
											}
										}
									},
									onCard: (id) => {
										if (!this.isActiveSlot(id)) return false;
										if (!this.isCurrentPlayerActive(id)) return false;
										this.clientStateArgs.boost = id;
										this.setClientStateAction('client_selectFaceDownPlanetColonize', _('Select a planet to add a colony'));
										return true;
									}
								});
								break;
							}
							this.setClientStateAction('client_selectFaceDownPlanetColonize', _('Select a planet to add colonies'));
							break;
						case 'a':
							this.setClientStateAction('client_selectActionAttack');
							break;
						case 't':
							this.setClientStateAction('client_selectResourceToTrade');
							break;
						case 'y':
							this.setClientStateAction('client_selectResourceType', _('Select a resource TYPE'));
							break;
						case 'p':
							this.setClientStateAction('client_selectPlanetToProduce');
							break;
						case 'L': // recon planets

							this.setClientStateCustom('clReconPlanets', _('RECON planet Deck (select a top card)'), [], {
								'onUpdateActionButtons': (args) => {
									this.revealLocation('supply_planets', true);
								},
								onCard: (id) => {
									if (!this.isActiveSlot(id)) return false;
									if (!this.isCurrentPlayerActive(id)) return false;

									if (this.commitOperation('L', id)) {
										dojo.query(".planets_display .card").forEach(
											(elt) => {
												// console.log("expand tech " + elt.id);
												this.removeTooltip(elt.id);
												this.gamedatas.tokens[id] = null;
												this.placeToken(elt.id);
											});
										this.gamedatas.tokens[id] = {
											key: id,
										}
										this.placeTokenLocal(id, 'supply_planets', 0);
										this.actionPromptAndCommit();
									}
									return true;
								}
							});
							break;
						case 'K':
							var mydeck = "deck_" + this.player_color;
							const mydeck_counter = 'deck_' + this.player_color + '_counter';
							if ($(mydeck_counter).innerHTML == 0) {
								mydeck = "discard_" + this.player_color;
							}
									
							this.setClientStateCustom('client_reconDeck', _('RECON your Deck (select a top card)'), [], {
								onUpdateActionButtons: (args) => {
									this.revealLocation(mydeck, true);
								},
								onCard: (id) => {
									if (!this.isActiveSlot(id)) return false;
									if (!this.isCurrentPlayerActive(id)) return false;

									//console.log("recon " + id);
									this.revealLocation(mydeck, false);
									if (this.commitOperation('K', id)) {
										this.actionPromptAndCommit();
										return true;
									}
									return false;
								}
							});
							
							break;
						case 'T':
							this.setClientStateCustom('clSelectTech', _('Select Technology'), [], {
								onUpdateActionButtons: (args) => {
									var techs = this.queryIds(".supply_tech .tech");

									for (i in techs) {
										var tech = techs[i];
										var rcost = this.getRulesFor(tech, 'b');
										if (rcost <= 3) {
											dojo.addClass(tech, 'active_slot');
										}
									}
									this.expandTech(true, ".tech.active_slot", "selection_area");
								},
								onCard: (id) => {
									if (!this.isActiveSlot(id)) return false;
									if (!this.isCurrentPlayerActive(id)) return false;

									if (this.commitOperation('T', id)) {
										this.expandTech(false);
										this.actionPromptAndCommit();
									}
									return true;
								}
							});
							break;

						case 'g':
							// select permanent tech in play to remove
							var name = this.gamedatas.materials.actions[command];
							this.setClientStateCustom('clSelectPermTech', this.getTr(name), [], {
								onUpdateActionButtons: () => {
									dojo.query(".tableau .permanent.tech").addClass('active_slot');
									dojo.query(".fleet_advanced").addClass('active_slot');
								},
								onCard: (id) => {
									if (!this.isActiveSlot(id)) return false;
									this.commitOperationAndSubmit('g', id, null, () => {
										this.expandTech(false);
									});
									return true;
								}
							});
							break;
						case 'F':
						case 'B':
						case 'D':
							if (this.clientStateArgs.pay) {
								debugger;
								var payment_token = this.payOne(command, true);
								if (typeof payment_token == 'string') {
									return this.commitOperationAndAction(command, payment_token);
								} else if (!payment_token) {
									return false;
								} else {
									this.setClientStateCustom('client_selectShipToPay', _('Select a ship or discard a card to pay cost'), {
										selectionList: payment_token,
										buthandler: (node_id) => {
								
											var icons = this.getRulesFor(node_id, 'i');
											var icons_count = 0;

											if (icons)
												icons_count = (icons.split(command).length - 1);
											else {
												var fighter = this.getRulesFor(node_id, 'p');
												icons_count = (command == fighter) ? 1 : 0;
											}

											if (icons_count == 0) {
												this.showMoveUnauthorized();
												return;
											}

											if (this.commitOperation(command, node_id)) {
												dojo.addClass(node_id, 'claimed');
												this.placeTokenLocal(node_id, 'setaside_' + this.player_color);
												for (var i = 1; i < icons_count; i++)
													this.consumeOperation(command);
												this.actionPromptAndCommit();
												return;
											}
										}
									}, {
										onUpdateActionButtons: (args) => {
											args.selectionList.forEach((value) => {
												if (value.startsWith('fighter')) {
													var div = this.divInlineToken(value);
													this.addImageActionButton("button_" + value, div,
														(e) => {
															var id = this.onClickSanity(e);
															this.gamedatas.gamestate.args.buthandler(id)
														});
												} else
													dojo.addClass(value, 'active_slot');
											});
										},
										onCard: (id) => {
											if (!this.checkActivePlayer()) return true;
											if (!this.checkActiveSlot(id)) return true;
											
											this.gamedatas.gamestate.args.buthandler(id);
											return true;
										}
									});


								}
							} else {
								return this.commitOperationAndAction(command);
							}
							break;
						case 'q':
							var card_name = this.getTokenName(this.gamedatas.gamestate.args.card);
							if (lookup !== 'q') message = _('${you} must choose a planet to keep for ${card_name} (last one)');
							else message = _('${you} must choose a planet to keep for ${card_name}');
							this.setClientStateCustom('clSelectPlanetToKeep', message , {
								card_name: card_name
							}, {
								onUpdateActionButtons: (args) => {
									dojo.query(".hand > .card_planet").addClass('active_slot');
								},
								onCard: (id) => {
									if (dojo.hasClass(id, 'selected')) {
										this.showError(_('Cannot unselect one card, press Cancel to start over'));
										return true;
									}
									if (!this.isActiveSlot(id)) return false;

									if (this.commitOperationAndSubmit('q', id)) {
										dojo.addClass(id, 'selected');
									}
									return true;
								}
							});
							break;
						case 'Q': // discard rest of planets from hand
							return this.commitOperationAndAction(command);
						case '+':
							// rinse and repeat
							this.consumeOperation("+");
							var info = this.getTokenDisplayInfo(this.clientStateArgs.card);
							var rules = info.a;
							var subactions = rules.split('/');
							if (subactions.length > 1) {
								rules = subactions[this.clientStateArgs.actnum];
							}
							if (!this.canPay(rules) || !rules) {
								return this.actionPrompt();
							}

							this.clientStateArgs.unprocessed_choices = 'Z' + rules + "/.";
							return this.actionPrompt();

						case '%':
							// count
							this.consumeOperation("%");
							var rule = this.clientStateArgs.unprocessed_choices[0];
							this.consumeOperation(rule);

							var querys = this.queryIds("#tableau_fighter_" + rule + "_" + this.player_color + " > *:not(.claimed)");
							//var queryc = this.queryIds("#hand_" + this.player_color + " > .as_fighter_" + rule + ":not(.claimed)");
							var queryr = this.queryIds("#tableau_" + this.player_color + " > .has_rslots > .fighter_" + rule + ":not(.claimed)");
							var total = querys.length + queryr.length;

							if (total == 0) {
								this.showError(_('Cannot find any fighters/resource to count'));
								this.clientStateArgs.unprocessed_choices += "!";
								return this.actionPrompt();
							}
							var comm = this.clientStateArgs.unprocessed_choices[0];
							this.prependOperation(comm, total - 1);
							return this.actionPrompt();

						case '!':
							this.consumeOperation("!");
							dojo.empty('generalactions');
							this.addDoneButton();
							this.addCancelButton();
							this.setDescriptionOnMyTurn(_('Press Done to confirm'));
							break;
						case '>':
							this.clientStateArgs.pay = false;
							this.consumeOperation(">");
							this.clientStateArgs.choices.push(['z']);
							return this.actionPrompt();
						case '.':
							return true; // submit
						case '?': // hardcoded cards
							return true; // submit
						case 'x':// skip
							return this.commitOperationAndAction(command);
						
						case 'i': // gain inf
						case 'R': // reaseach icon
						case 'd': // draw
						case 'o': // add action
						case 'O': // add role
						case 'Z': // start new action repeater
						case 'u': // draw a planet
							return this.commitOperationAndAction(command);
						default:
							this.showError("Unknown command: " + command);
							return false; //this.commitOperationAndAction(command);

					}
					return false;
				},
				getRoleCard: function(type) {
					var nodes = dojo.query(".board .card_role_" + type);
					if (nodes.length > 0) return nodes[0].id;
					else
						return "x_" + type + "_bottom";//x_research_bottom
				},

				getRulesFor: function(card_id, field) {
					if (field == undefined) field = 'r';
					var key = card_id;
					while (key) {
						var info = this.gamedatas.token_types[key];
						if (info == undefined) {
							key = getParentParts(key);
							if (!key) {
								this.showError("Internal Error for " + card_id);
								console.error("Undefined info for " + card_id);
								return '';
							}
							continue;
						}
						var rule = info[field];
						if (rule == undefined) return '';
						break;
					}
					return rule;
				},

				getCostRule: function(card_id) {
					var rcost = this.getRulesFor(card_id, 'b');
					var mcost = this.getRulesFor(card_id, 'bm');
					var rcoststr = this.strRepeat('R', rcost);

					if (mcost) {
						var mcostletter = mcost.charAt(1);
						var mcostnum = parseInt(mcost.charAt(0));
						var mcoststr = this.strRepeat(mcostletter, mcostnum);
						return rcoststr + ">/" + mcoststr + "!>";
					} else {
						return rcoststr + ">";
					}
				},

				updateMyCountersAll: function() {
					// console.log("updating counters");
					// var type = ".card:not(.card_bottom)";
					// this.updateLocalCounter('supply_survey', type);
					// this.updateLocalCounter('supply_warfare', type);
					// this.updateLocalCounter('supply_colonize', type);
					// this.updateLocalCounter('supply_produce', type);
					// this.updateLocalCounter('supply_research', type);
					// this.updateLocalCounter('discard_planets', type);

					//for (var player_id in this.gamedatas.players) {
					//	var playerInfo = this.gamedatas.players[player_id];
					//	this.updateLocalCounter('tableau_fighter_' + playerInfo.color, '.fighter:not(.counter)');
					//}
				},
				updatePermCounters: function(color, perm_boost_num) {
					var role_icons = this.gamedatas.materials.role_icons;
					for (var i in role_icons) {
						var x = role_icons[i];
						var perm_boost_count = perm_boost_num[x];
						var counter = 'iconperm_' + x + '_' + color + '_counter';
						if ($(counter)) $(counter).innerHTML = perm_boost_count;
					}
				},
				updateLocalCounter: function(location, childtype) {
					var query = dojo.query("#" + location + ' ' + childtype);
					var counter = location + '_counter';
					//console.log("** LOCAL counter " + counter + " -> " + query.length);
					if ($(counter)) $(counter).innerHTML = query.length;
					else
						console.error("Cannot find counter " + counter);
				},
				expandTech: function(state, query, loc) {
					console.log("expand tech " + state);
					if (typeof query == 'undefined') {
						query = ".supply_tech .tech";
					}
					if (typeof loc == 'undefined') {
						loc = 'tech_display';
					}

					if (state) {
						dojo.addClass(loc, 'expanded');
						if (loc == 'tech_display') {
							dojo.place('selection_area_controls', loc, 'first');
						} else {
							dojo.place('selection_area_controls', 'selection_area', 'first');
						}
				
						var queryres = dojo.query(query);
						queryres.forEach((elt, i) => {
							// console.log("expand tech " + elt.id);
							var info = this.getTokenDisplayInfo(elt.id);
							if (info.side == 2) {
								return;
							}
							var rloc = loc;
							if (loc == 'tech_display') {
								rloc = loc + "_" + info.t;
							}

							this.slideToObjectRelative(elt.id, rloc, 500, i * 2, (node) => {
								this.updateTooltip(node.id);
							});
						}

						);
						// second pass place slip side next to 1st side
						setTimeout(() => {
							dojo.query(query).forEach((elt, i) => {
								// console.log("expand tech " + elt.id);
								var info = this.getTokenDisplayInfo(elt.id);
		
								if (info.side != 2) return;// ??? should not happen
								var rloc = loc;
								var relation = undefined;
		
								var flip = info.flip;
						
								var flipNode = $(flip);
								if (flipNode && flipNode.parentNode.id.startsWith(loc)) {
									rloc = flip;
									relation = 'after';
								}

								this.slideToObjectRelative(elt.id, rloc, 500, i * 2, (node) => {
									this.updateTooltip(node.id);
								}, relation);
							}
							);
						}, queryres.length * 2 + 100);

						setTimeout(() => this.updateFilters(), queryres.length * 2 +600);
					} else {
						dojo.place('selection_area_controls', 'selection_area', 'first');
		
						
						if (loc == 'discard_display') {
							queryFold = ".discard_display  .card";
							queryUnexpand = ".discard_display";
						} else if (loc == 'deck_display') {
							queryFold = ".deck_display  .card";
							queryUnexpand = ".deck_display";
						} else if (loc == 'planets_display') {
							queryFold = ".planets_display  .card";
							queryUnexpand = ".planets_display";
						} else {
							queryFold = ".common_space  .tech,.selection_area .tech,.inspect_display  .card";
							queryUnexpand = ".inspect_display";
						}

						dojo.query(queryFold).forEach(
							(elt) => {
								// console.log("expand tech " + elt.id);
								this.removeTooltip(elt.id);
								this.placeToken(elt.id);
							});

						dojo.query(queryUnexpand).removeClass('expanded');
					}
				},
				playAction: function(card_id, rules) {
					if (rules === undefined)
						rules = this.getRulesFor(card_id, 'a');
					this.clientStateArgs.card = card_id;
					this.clientStateArgs.unprocessed_choices = rules;

					this.clientStateArgs.action = 'playAction';
					if (this.gamedatas.gamestate.args.extra)
						this.clientStateArgs.action = 'playExtra';
					this.clientStateArgs.choices = [];

					dojo.query(".active_slot").removeClass('active_slot');

					this.actionPromptAndCommit();
				},
				// /////////////////////////////////////////////////
				// // Player's action
				/*
				 * 
				 * Here, you are defining methods to handle player's action (ex: results of mouse click on game objects).
				 * 
				 * Most of the time, these methods: _ check the action is possible at this game state. _ make a call to the game server
				 * 
				 */
				onCard: function(event) {
					var id = event.currentTarget.id;
					dojo.stopEvent(event);
					console.log("on slot " + id);
					if (id == null) return;

					// Call handler method
					var methodName = "onCard_" + this.getStateName();
					if (this[methodName] !== undefined) {
						console.log('Calling ' + methodName + " " + id);
						if (this[methodName](id))
							return;
					}


					var card_id = id;
					var checkActive = true;
					var node = $(id);
					var parentNode = node.parentNode;

					if (parentNode.id == 'discard_display') {
						this.expandTech(false);
						return;
					}
					if (parentNode.id == 'deck_display') {
						this.expandTech(false);
						return;
					}
					if (parentNode.id == 'discard_planets' || parentNode.id == 'planets_display') {
						this.revealLocation(parentNode.id);
						return;
					}

					if (id.startsWith("card")) {
						if (parentNode.id.startsWith('supply_tech')) {
							this.expandTech(true);
							return;
						} else if (parentNode.id.startsWith('tech_display')) {
							this.expandTech(false);
							if (this.getStateName() != 'client_selectTechCard') return;
						}
					}
					if (id.startsWith("supply_tech")) {
						this.expandTech(false);
						return;
					}

					switch (this.getStateName()) {
						case 'playerTurnDiscard':
						case 'playerTurnPreDiscard':
						
							checkActive = false;
							break;
						case 'client_selectResourceToTrade':
							checkActive = false;
							break;
					}
					if (!this.checkActivePlayer()) return;
					if (checkActive && !this.checkActiveSlot(id)) return;

					var info = this.getTokenDisplayInfo(card_id);

					// activatable tech card
					if (id.startsWith("card") && parentNode.id.startsWith('tableau_' + this.player_color) && info.e && !this.on_client_state) {
						var info = this.getTokenDisplayInfo(card_id);
						this.clientStateArgs.card = card_id;
						this.clientStateArgs.unprocessed_choices = info.e;
						this.clientStateArgs.choices = [];
						this.clientStateArgs.action = 'playActivatePermanent';
						this.actionPromptAndCommit();
						return;
					}


					switch (this.getStateName()) {
						case 'playerTurnAction':
							this.clientStateArgs.card = card_id;

							if (parentNode.id == 'tableau_' + this.player_color) {
								// permanent
							} else {
								this.placeTokenLocal(card_id, 'setaside_' + this.player_color, 11);
							}

							this.playAction(card_id);
							return;
						case 'playerTurnRole':
							this.clientStateArgs.card = card_id;
							this.clientStateArgs.role = info.r;
							if (!card_id.startsWith("x_"))
								this.placeTokenLocal(card_id, 'setaside_' + this.player_color, 11);
							this.setClientStateAction('client_selectBoostOrLeader');
							return;

						case 'playerTurnSurvey':
						case 'playerTurnSurveyFollow':
						case 'client_playerTurnSurvey':
							this.clientStateArgs.card = card_id;
							this.ajaxClientStateAction();
							return;
						case 'playerTurnDiscard':
						case 'playerTurnPreDiscard':
							if (dojo.hasClass(card_id, 'selected')) {
								dojo.removeClass(card_id, 'selected');
								dojo.addClass(card_id, 'active_slot');
							} else {
								if (!this.checkActiveSlot(card_id)) return;
								dojo.addClass(card_id, 'selected');
								dojo.removeClass(card_id, 'active_slot');
							}
							var divpick = dojo.query(".hand .card.selected").map(function(node) {
								return node.id;
							}).join(" ");
							this.clientStateArgs.boost = divpick;
							return;
						case 'client_selectTechCard':
							if (this.commitOperation('R', card_id)) {
								dojo.removeClass(card_id,'active_slot');
								this.placeTokenLocal(card_id, 'setaside_' + this.player_color, 1);
								this.expandTech(false);
								var crule = this.getCostRule(card_id);

								this.clientStateArgs.unprocessed_choices = crule;

								this.actionPromptAndCommit();

							}
							return;
						case 'client_selectRoleCard':
							this.placeTokenLocal(card_id, 'hand_' + this.player_color, 1);
							return this.commitOperationAndSubmit('l', card_id);
						case 'client_selectCardToRemove':
							if (card_id == this.clientStateArgs.card && !dojo.hasClass(this.clientStateArgs.card, 'toremove')) {
								dojo.addClass(this.clientStateArgs.card, 'toremove');
								dojo.removeClass(this.clientStateArgs.card, 'selected');
							}

							if (dojo.hasClass(card_id, 'selected')) {
								this.showError(_('Select another card or Cancel to start over'));
								return;
							}
							dojo.addClass(card_id, 'selected');
							if (this.clientStateArgs.unprocessed_choices == 'E') {
								if (this.commitOperation('E', card_id)) {
									if (this.clientStateArgs.unprocessed_choices == '') {
										this.clientStateArgs.unprocessed_choices = 'E';

									}
									this.actionPrompt();
									return;
								}
							} else {
								return this.commitOperationAndSubmit('e', card_id);
							}
							return;
						case 'client_selectFaceDownPlanet':
						case 'client_selectFaceDownPlanetSettleOnly':
							var info = this.getTokenDisplayInfo(this.clientStateArgs.card);

							if (dojo.hasClass(card_id, 'selected')) {
								this.showError(_('This planet is already selected'));
								return;
							}
							var action = this.clientStateArgs.unprocessed_choices[0];
							var planets = this.gamedatas.gamestate.args.planets;
							if (action == 'S' || this.getStateName() == 'client_selectFaceDownPlanetSettleOnly') {
								var need = planets[card_id].need;
								if (this.getStateName() == 'client_selectFaceDownPlanetSettleOnly' && info !== null && info.a == 'Ss') {
									// Second Settle of an Improved Colonize: we must adjust planet need if the 1st planet to be settled has a colonize symbol
									var queueEntries = dojo.query('.selected');
									for (var i = 0; i < queueEntries.length; i++) {
										var card = queueEntries[i];
										if (!dojo.hasClass(card, 'card_planet')) continue;

										var pinfo = this.getTokenDisplayInfo(card.id);
										if (pinfo.i == 'C') {
											need--;
										}
									}
								}
								if (need > 0) {
									var settling_planet = card_id;
									if (this.playerHasOnTableau('card_tech_E_21')) { // colony ship
										this.clientStateArgs.unprocessed_choices += "!";
										if (this.commitOperationAndSubmit(action, settling_planet)) {
											this.setClientStateCustom('clRedistributeColonies', _('Redistribute Colonies: Select colonies, then select a planet to move them to'), [], {
												onUpdateActionButtons: (args) => {
													dojo.query(".card > .card").addClass('active_slot');
													dojo.query(".tableau_" + this.player_color + " .card_planet.state_0").addClass('active_slot');
													dojo.query(".tableau_" + this.player_color + " .coloniseable").addClass('active_slot');
													this.addDoneButton();
													//this.addActionButton('button_done', _("Done Reditributing"), () => {
													//	this.commitOperationAndSubmit('s', settling_planet);
													//});
												},
												onCard: (id) => {
													if (!this.isActiveSlot(id)) return false;
													if (!this.isCurrentPlayerActive(id)) return false;


													if ($(id).parentNode.id.startsWith("card")) {
														// colony
														dojo.addClass(id, 'selected');
													} else {
														// planet
														var planet = id;
														var res = dojo.query(".card > .card.selected");
														if (res.length == 0) {
															this.showError(_('Nothing is selected'));
															return;
														}
														res.forEach((node) => {
															var colony = node.id;
															this.clientStateArgs.unprocessed_choices += "c";
															if (this.commitOperation('c', planet, colony)) {
																dojo.removeClass(colony, 'selected');
																this.placeTokenLocal(colony, planet, 2);
															};
														});
													}

													return true;
												}
											});
										}
										return;
									} else {
										this.showError(_('Not enough colonies to Settle this planet'));
										return;
									}
								}
							}
							return this.commitOperationAndSubmit(action, card_id);

						case 'client_selectFaceDownPlanetColonize':
							var planets = this.gamedatas.gamestate.args.planets;

							if (planets[card_id].settled == 1) {
								this.showMoveUnauthorized();
								return;
							}


							var need = planets[card_id].need;
							var symbols = 1;
							var terra = this.clientStateArgs.card == 'card_tech_2_93';
							if (terra) symbols = 2;
							if (need <= 0 && !terra || planets[card_id].ready === true) {// not terraformin
								if (!confirm(_('You already have enough colonies to settle this planet. Are you sure you want to add more?')))
									return;
							}
							var many = 0;
							for (var p in planets) {
								var op = planets[p];
								if (op.settled == 0 && op.need > 0) {
									many++;
								}
							}
							var boost_arr = this.clientStateArgs.boost ? this.clientStateArgs.boost.trim().split(' ') : [];
							if (this.clientStateArgs.action != 'playActivatePermanent') {
								if (!this.clientStateArgs.card.startsWith("x_")) {
									boost_arr.unshift(this.clientStateArgs.card);
								}
							}
							var boost = boost_arr.length;
							if (boost == 0) {
								this.showError(_('No more colonies'));
								return;
							}

							if (this.clientStateArgs.boost_used === undefined)
								this.clientStateArgs.boost_used = 0;
							var i = this.clientStateArgs.boost_used;

							for (; i < boost; i++) {
								var colony = boost_arr[i];
								if (this.commitOperation('cs', card_id, colony)) {
									this.placeTokenLocal(colony, card_id, 14);
									if (this.clientStateArgs.unprocessed_choices == '') {
										this.ajaxClientStateAction();
										return;
									}
								} else {
									break;
								}
								planets[card_id].need -= symbols;

								this.clientStateArgs.boost_used = i + 1;
								if (many > 1 || planets[card_id].ready === true) {
									this.setClientStateAction('client_selectFaceDownPlanetColonize',
										_('Select a planet to add colony'));
									return;
								}
							}

							return;

						case 'client_selectActionAttack':
							if (this.commitOperation('aA', card_id)) {
								this.clientStateArgs.planet = card_id;


								if (this.playerHasOnTableau("card_tech_E_38") && dojo.hasClass(card_id, "state_1")) {
									// military campaign
									var vp = this.getRulesFor(card_id, 'v');
									var vcost = this.strRepeat('F', vp);
									this.prependOperation("B" + vcost + ">");
									return this.actionPromptAndCommit();
								}
								var wcost = this.gamedatas.token_types[card_id]["w"];
								var fdiscount = this.gamedatas.gamestate.args.fdiscount;
								if (!isNaN(wcost)) {// number
									wcost -= fdiscount;
									if (wcost <= 0) {
										return this.actionPromptAndCommit();
									}
									wcost = this.strRepeat('F', wcost);
								}

								if (this.playerHas(".fleet_advanced") && wcost != 'B') {
									this.prependOperation(wcost + ">/B>");
								} else {
									this.prependOperation(wcost + ">");
								}

								return this.actionPromptAndCommit();
							}


							return false; // AAA

						case 'client_selectBoost':
						case 'client_selectBoostOrLeader':
							var icon = this.clientStateArgs.role;
							this.clientStateArgs.boost += card_id + " ";
							var hand_boost_count = this.gamedatas.gamestate.args.hand_boost[icon][card_id];
							delete this.gamedatas.gamestate.args.hand_boost[icon][card_id];
							this.gamedatas.gamestate.args.hand_boost_count += hand_boost_count;
							// console.log( this.gamedatas.gamestate.args)
							this.setClientStateAction('client_selectBoost', '');
							return;
						case 'client_selectPlanetToProduce':
							var planets = this.gamedatas.gamestate.args.holders;
							if (!planets[card_id]) {
								this.showMoveUnauthorized();
								return;
							}
							var countfree = planets[card_id].resslots;
							var arr = planets[card_id].resf;


							if (countfree == 0) {
								this.showError(_('Card does not have resource slots to produce'));
							} else if (countfree == 1) {
								// Only one choice left, take it
								for (var key in arr) {
									if (arr[key] > 0 && key != 'n') {
										var nodes = dojo.query('.stock_resource .resource_' + key);
										var node_id = nodes.length > 0 ? nodes[0].id : 'x';
										if (this.commitOperation('p', card_id, key, node_id)) {
											if (node_id != 'x') this.placeTokenLocal(node_id, card_id);
											planets[card_id][key]--;
											planets[card_id].resslots--;
											this.actionPromptAndCommit();
											return;
										}
									}
								}
							}
							// 2 choices, overlay card with the choice to make
							var restypes = [];
							for (var key in arr) {
								if (arr[key] > 0) {
									restypes.push(key);
								}
							}
							this.setClientStateAction('client_selectResourceType', _('Select a resource type to produce'), 0, {
								restypes: restypes,
								buthandler: (lkey) => {
									var nodes = dojo.query('.stock_resource .resource_' + lkey);
									var node_id = nodes.length > 0 ? nodes[0].id : 'x';
									if (this.commitOperation('p', card_id, lkey, node_id)) {
										if (node_id != 'x') this.placeTokenLocal(node_id, card_id);
										planets[card_id][lkey]--;
										planets[card_id].resslots--;
										this.actionPromptAndCommit();
										return;
									}
								}
							});



							return;
						case 'client_selectResourceToTrade':

							if (dojo.hasClass(card_id, 'as_resource') || dojo.hasClass(card_id, 'as_fighter')) {
								this.commitOperationAndSubmit('t', card_id, 'x');
								return;
							}
							var planets = this.gamedatas.gamestate.args.holders;
							if (planets[card_id] === undefined) {
								this.showError(_('Card does not have resource slots'));
								return;
							}

							if (!planets[card_id].produced) {
								this.showError(_('Card does not have produced resources'));
								return;
							}
							var arr = planets[card_id].resarr;
							var restypes = [];
							for (var key in arr) {
								if (arr[key] > 0) {
									restypes.push(key);
								}
							}
							this.setClientStateAction('client_selectResourceType', _('Select a resource type to trade'), 0, {
								restypes: restypes,
								buthandler: (lkey) => {
									var resource = this.getFirstChild("#" + card_id + ' .resource_' + lkey);
									if (this.commitOperation('t', card_id, resource)) {
										planets[card_id].resarr[lkey]--;
										planets[card_id].produced--;
										this.placeTokenLocal(resource, 'setaside_' + this.player_color, 1);
										this.actionPromptAndCommit();
										return;
									}
								}
							});


							return;
					}
					this.showMoveUnauthorized();
				},


				onExecuteRole: function(event) {
					var id = this.onClickSanity(event, false);
					if (id == null) return;
					var total = getIntPart(id, 1);
					//console.log(total);
					if (!this.clientStateArgs.action) {
						this.showError(_('Internal error: unknown action'));
						return;
					}

					this.clientStateArgs.choices = [];




					// leader extra boost for role card
					var leader = !(this.clientStateArgs.card.startsWith('x_'));


					switch (this.clientStateArgs.role) {
						case 'C':
							if (leader) this.clientStateArgs.unprocessed_choices = 'c';
							var boost_arr = this.clientStateArgs.boost.trim().split(' ');
							for (var i = 0; i < boost_arr.length; i++) {
								if (boost_arr[i]) this.clientStateArgs.unprocessed_choices += 'c';
							}
							var total = this.clientStateArgs.unprocessed_choices.length;
							if (total == 0) {
								this.zeroBoostPrompt(leader);
								return;
							}
							this.setClientStateAction('client_selectFaceDownPlanetColonize', _('Select a planet to add colonies'));
							break;
						case 'W':
							var args = this.gamedatas.gamestate.args;
							var total = args.perm_boost_count + args.hand_boost_count;
							if (total == 0 && !leader) {
								this.zeroBoostPrompt(leader);
								return;
							}
							this.ajaxClientStateAction();
							break;
						case 'S':
							var args = this.gamedatas.gamestate.args;
							var total = args.perm_boost_count + args.hand_boost_count;
							var message = _('SURVEY: Draw ${look} planets - keep ${keep} (unused boost ${remaining_boost})');
						
							if (leader) total++;
							var look = total - 1;
							var keep = 1;

							if (id.endsWith('_ts')) {
								this.clientStateArgs.choices.push(['activate', 'card_tech_E_24']);
								keep = 2;
								look -= 2;
							}
							if (total <= 1 && !leader) {
								this.zeroBoostPrompt(leader);
								break;
							}
							if (args.possible_boost == 0) {
								this.ajaxClientStateAction();
								break;
							}
							var text = this.format_string_recursive(message, {
								look: look,
								keep: keep,
								remaining_boost: args.possible_boost
							});

							this.setClientStateAction('client_confirm',text);
	
							break;
						case 'P':
							var args = this.gamedatas.gamestate.args;
							var total = args.perm_boost_count + args.hand_boost_count;
							for (var i = 0; i < total; i++) {
								this.clientStateArgs.unprocessed_choices += 'p';
							}
							if (total == 0) {
								this.zeroBoostPrompt(leader);
								return;
							}
							this.setClientStateAction('client_selectPlanetToProduce');
							break;
						case 'T':
							var args = this.gamedatas.gamestate.args;
							var total = args.perm_boost_count + args.hand_boost_count;
							for (var i = 0; i < total; i++) {
								this.clientStateArgs.unprocessed_choices += 't';
							}
							if (total == 0) {
								this.zeroBoostPrompt(leader);
								return;
							}
							this.setClientStateAction('client_selectResourceToTrade');
							break;
						case 'R':
							this.clientStateArgs.unprocessed_choices = 'R';
							var args = this.gamedatas.gamestate.args;
							var total = args.perm_boost_count + args.hand_boost_count;

							//							if (total == 0) {
							//								this.zeroBoostPrompt(leader);
							//								return;
							//							}

							this.setClientStateAction('client_selectTechCard', _('Select a technology to research <div class="icon research"></div>x${boost_count}'), 0, {
								boost_count: total
							});

							break;
						default:
							this.showMoveUnauthorized();
							break;
					}
				},
				onDeck: function(event) {
					var id = event.currentTarget.id;
					dojo.stopEvent(event);
					console.log("on slot " + id);
					if (id == null || this.isSpectator) return;
					this.revealLocation(id);
				},
				onDiscard: function(event) {
					var id = event.currentTarget.id;
					dojo.stopEvent(event);
					console.log("on slot " + id);
					if (id == null || this.isSpectator) return;
					this.revealLocation(id);
				},

				revealLocation: function(id, activate) {
					var location = 'discard_display';
					if (id == 'discard_planets' || id == 'planets_display' || id == 'supply_planets') {
						location = 'planets_display';
					}
					var action = 'showDiscard';
					var args = { place: id, lock: false, checkaction: false };

					if (id.indexOf('deck_') >= 0) {
						location = 'deck_display';
					}

					if (dojo.query("#" + location + " > .card").length > 0) {
						this.expandTech(false, null, location);
						return;
					}
					var self = this;
					func = function(result) {
						console.log(result);
						if (result.data && result.data.contents) {
							if (result.data.length > 0) {
								console.log("expanding " + location);
								dojo.addClass(location, 'expanded');
								for (var elem in result.data.contents) {
									//console.log(elem,activate);
									result.data.contents[elem].location = location;
									result.data.contents[elem].state = 1;
									self.placeTokenWithTips(elem, result.data.contents[elem]);

									if (activate) {
										dojo.addClass(elem, 'active_slot');
									}
								}
							}
						}
					};
					this.ajaxAction(action, args, func);

				},
				onResource: function(event) {
					var id = this.onClickSanity(event, true);
					if (id == null) return;
					var resource = id;
					var planet = $(resource).parentNode.id;

					switch (this.getStateName()) {
						case 'client_selectResourceToTrade':
							this.commitOperationAndSubmit('t', planet, resource, () => {
								dojo.removeClass(resource, 'active_slot');
								this.placeTokenLocal(resource, 'setaside_' + this.player_color, 1)
							}
							);
							return;
						case 'client_selectBoost':
						case 'client_selectBoostOrLeader':
							var icon = this.clientStateArgs.role;
							this.clientStateArgs.boost += id + " ";
							var hand_boost_count = this.gamedatas.gamestate.args.hand_boost[icon][id];
							delete this.gamedatas.gamestate.args.hand_boost[icon][id];
							this.gamedatas.gamestate.args.hand_boost_count += hand_boost_count;
							this.placeTokenLocal(id, 'setaside_' + this.player_color, 0);
							// console.log( this.gamedatas.gamestate.args)
							this.setClientStateAction('client_selectBoost', '');
							return;
					}
					this.showMoveUnauthorized();
				},

				onFighter: function(event) {
					var id = this.onClickSanity(event, false);
					if (id == null) return;

					// Call handler method
					var methodName = "onFighter_" + this.getStateName();
					if (this[methodName] !== undefined) {
						console.log('Calling ' + methodName + " " + id);
						this[methodName](id);
						return;
					}

					var card_id = id;
					//var info = this.getTokenDisplayInfo(card_id);
					switch (this.getStateName()) {
						case 'client_selectResourceToTrade':
							if (!this.checkActiveSlot(id)) return;
							this.commitOperationAndSubmit('t', 'x', id, () =>
								this.placeTokenLocal(card_id, 'setaside_' + this.player_color, 1)
							);
							return;
						case 'client_selectBoost':
						case 'client_selectBoostOrLeader':
							var icon = this.clientStateArgs.role;

							var hand_boost_count = this.gamedatas.gamestate.args.hand_boost[icon][id];
							if (hand_boost_count) {
								this.clientStateArgs.boost += id + " ";
								delete this.gamedatas.gamestate.args.hand_boost[icon][id];
								this.gamedatas.gamestate.args.hand_boost_count += hand_boost_count;
								this.placeTokenLocal(id, 'setaside_' + this.player_color, 0);
								// console.log( this.gamedatas.gamestate.args)
								this.setClientStateAction('client_selectBoost', '');
								return;
							}
							break;
						default:
							// just for fun it goes to tableau and back
							if ($(id).parentNode.id == 'stock_fighter') {
								var loc = 'tableau_' + this.player_color;

								this.delayedExec(
									() => this.placeTokenLocal(card_id, loc, 0),
									() => this.placeTokenLocal(card_id, 'stock_fighter', 0),
									800, 0);
								return;
							}


					}
					this.showMoveUnauthorized();
				},
				onSlider: function(event) {
					var id = event.currentTarget.id;
					dojo.stopEvent(event);
					var sliderId = id;
					this.onSliderChange(sliderId);
				},
				onSliderChange: function(sliderId) {
					var slider_value = $(sliderId).value;
					var output = document.getElementById(sliderId + "_value");
					output.innerHTML = slider_value;


					if (sliderId == 'R_slider') {
						if (slider_value == 1) {
							output.innerHTML = "&nbsp;";
						}
					} else
						if (sliderId == 'Vp_slider') {
							if (slider_value == 0) {
								output.innerHTML = "&nbsp;";
							}
						}
					this.updateFilters();
				},
				onCheckbox: function(event) {
					//var id = event.currentTarget.id;
					dojo.stopEvent(event);
					this.updateFilters();
				},
				updateFilters: function() {
					var hide_opacity = 0.3;
					dojo.query(".filter_control_area .card").forEach((node) => {
						dojo.style(node, "opacity", 1);
					});
					var r_slider_value = $('R_slider').value;
					if (r_slider_value > 1) {
						dojo.query(".filter_control_area .card:not(.cost_" + r_slider_value + ")").forEach((node) => {
							dojo.style(node, "opacity", hide_opacity);
						});
					}
					var vp_slider_value = $('Vp_slider').value;
					if (vp_slider_value > 0) {
						dojo.query(".filter_control_area .card:not(.vp_" + vp_slider_value + ")").forEach((node) => {
							dojo.style(node, "opacity", hide_opacity);
						});
					}

					var perm = $('x_permanent_checkbox').checked;
					if (perm) {
						dojo.query(".filter_control_area .card:not(.permanent)").forEach((node) => {
							dojo.style(node, "opacity", hide_opacity);
						});
					}

					for (var icon in this.gamedatas.materials.icons) {
						var but = $(icon + '_checkbox');
						if (but) {
							var check_value = but.checked;
							if (check_value) {
								if (icon == 'n') {
									dojo.query(".filter_control_area .card:not(.as_resource)").forEach((node) => {
										dojo.style(node, "opacity", hide_opacity);
									});
								} else {
									dojo.query(".filter_control_area .card:not(.has_icon_" + icon + ")").forEach((node) => {
										dojo.style(node, "opacity", hide_opacity);
									});
								}
							}
							//console.log(icon + " " + check_value);
						}
					}
				},
				// /////////////////////////////////////////////////
				// // Reaction to cometD notifications
				/*
				 * setupNotifications:
				 * 
				 * In this method, you associate each of your game notifications with your local method to handle it.
				 * 
				 * Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in your eminentdomain.game.php file.
				 * 
				 */
				setupNotifications: function() {
					console.log('notifications subscriptions setup enminent');
					this.inherited(arguments);
				},
			});
	});
