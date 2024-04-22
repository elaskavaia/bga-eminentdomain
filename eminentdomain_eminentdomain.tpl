{OVERALL_GAME_HEADER}

<!-- 
--------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- eminentdomain implementation : © Alena Laskavaia <laskava@gmail.com>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-------

    eminentdomain_eminentdomain.tpl
    
    This is the HTML template of your game.
    
    Everything you are writing in this file will be displayed in the HTML page of your game user interface,
    in the "main game zone" of the screen.
    
    You can use in this template:
    _ variables, with the format {MY_VARIABLE_ELEMENT}.
    _ HTML block, with the BEGIN/END format
    
    See your "view" PHP file to check how to set variables and control blocks
    
    Please REMOVE this comment before publishing your game on BGA
-->


	
<div id="outer_wrapper" class="anchor">
	<div class="thething" id="thething">
		<div id="endofgamewarning" class="endofgamewarning"></div>
		<div id="selection_area" class="selection_area inspect_display filter_control_area">
			<div id="selection_area_controls" class="selection_area_controls">
				<div class="control-card slidecontainer">
					<span id="R_slider_title" class="control-title">Cost</span>
					<div id="R_slider_value"
						class="icon R miniitem counter textoverlay">&nbsp;</div>
					<input type="range" min="1" max="7" value="1" step="2"
						class="gslider R-slider" id="R_slider" />
				</div>
				<div class="control-card slidecontainer">
					<span id="Vp_slider_title" class="control-title">Influence</span>
					<div id="Vp_slider_value" class="vp miniitem counter textoverlay">&nbsp;</div>
					<input type="range" min="0" max="5" value="0" step="1"
						class="gslider Vp-slider" id="Vp_slider" />
				</div>
				<div class="control-card">
					<span id="icons_title" class="control-title">Icons</span>

					<div id="W_checkbox_icon" class="icon W miniitem"></div>
					<input id="W_checkbox" class="gcheckbox" type="checkbox" />
					<div id="S_checkbox_icon" class="icon S miniitem"></div>
					<input id="S_checkbox" class="gcheckbox" type="checkbox" />
					<div id="R_checkbox_icon" class="icon R miniitem"></div>
					<input id="R_checkbox" class="gcheckbox" type="checkbox" />
					<div id="C_checkbox_icon" class="icon C miniitem"></div>
					<input id="C_checkbox" class="gcheckbox" type="checkbox" />

					<div id="P_checkbox_icon" class="icon P miniitem"></div>
					<input id="P_checkbox" class="gcheckbox" type="checkbox" />

					<div id="T_checkbox_icon" class="icon T miniitem"></div>
					<input id="T_checkbox" class="gcheckbox" type="checkbox" />
					
					<span id="n_checkbox_title" class="control-title">Resource</span> 
					<input	id="n_checkbox" class="gcheckbox" type="checkbox" />
					
					<div class="wrapped_icon"><div id="F_checkbox_icon" class="icon miniitem fighter fighter_F"></div></div>
					<input	id="F_checkbox" class="gcheckbox" type="checkbox" />
					
					<div class="wrapped_icon"><div id="D_checkbox_icon" class="icon miniitem fighter fighter_D"></div></div>
					<input	id="D_checkbox" class="gcheckbox" type="checkbox" />
					
					<div class="wrapped_icon"><div id="B_checkbox_icon" class="icon miniitem fighter fighter_B"></div></div>
					<input	id="B_checkbox" class="gcheckbox" type="checkbox" />
						
				</div>


				<div class="control-card">
					<span id="x_permanent_checkbox_title" class="control-title">Permanent</span>
					<input id="x_permanent_checkbox" class="gcheckbox" type="checkbox" />
				</div>
			</div>
		</div>

		<div id="hand_area" class="hand_area">
			<div id="hand_icon" class="hand_icon"></div>
			<div id="hand_{PCOLOR}" class="hand hand_{PCOLOR}"></div>
		</div>

		<div id="deck_display" class="deck_display inspect_display"
			data-title="{DECK_TEXT}"></div>
		<div id="discard_display" class="discard_display inspect_display"
			data-title="{DISCARD_TEXT}"></div>
		<div id="planets_display" class="planets_display inspect_display"></div>
		<div id="bboard" class="bboard">

			<div id="pboard_space" class="pboard_space">
				<!-- BEGIN player_board -->




				<div id="tableau_{COLOR}"
					class="tableau tableau_{COLOR} empire {CLASSES}">
					<div class="side_title_wrapper">
						<div id="empire_title_{COLOR}" class="side_title color_{COLOR}">{EMPIRE_LABEL}</div>
					</div>
				
					<div id="sep_scenario_{COLOR}" class="sep"></div>
					<div id="sep_fleet_{COLOR}" class="sep"></div>
					<div id="sep_tech_{COLOR}" class="sep"></div>
					<div id="sep_planetA_{COLOR}" class="sep"></div>
					<div id="sep_planetE_{COLOR}" class="sep"></div>
					<div id="sep_planetM_{COLOR}" class="sep"></div>
					<div id="sep_planetP_{COLOR}" class="sep"></div>
					<div id="sep_planet_{COLOR}" class="sep"></div>
					<div id="sep_planet0_{COLOR}" class="sep"></div>
				</div>
				<div id="setaside_{COLOR}" data-title="{SET_ASIDE_TEXT}"
					class="tableau setaside empire setaside_{COLOR} color_{COLOR} {CLASSES}"></div>

				<!--  end tableau -->

				<!--  boardblock  -->
				<div id="miniboard_{COLOR}" class="miniboard">

					<div id="miniboard_row_1_{COLOR}" class="miniboard_row">
						<div id="deck_{COLOR}" class="deck_{COLOR} deck miniitem">
							<div id="deck_icon_{COLOR}" class="deck_icon card_icon"></div>
							<div id="deck_{COLOR}_counter"
								class="counter deck_counter textoverlay"></div>
						</div>

						<div id="discard_{COLOR}" class="discard_{COLOR} discard miniitem">
							<div id="discard_icon_{COLOR}" class="discard_icon card_icon"></div>
							<div id="discard_{COLOR}_counter"
								class="counter discard_counter textoverlay"></div>
						</div>
						<div id="handwrapper_{COLOR}" class="miniitem slot_tooltip">
							<div id="hand_icon_{COLOR}" class="card_icon"></div>
							<div id="hand_{COLOR}_counter"
								class="counter hand_counter textoverlay"></div>
						</div>
						<div id="tableau_vp_{COLOR}"
							class="miniitem vp_wrapper tableau_vp_player slot_tooltip">
							<div id="vp_{COLOR}_counter"
								class="counter vp_counter textoverlay vp">0</div>
						</div>
						<!--  
						<div id="tableau_fighter_wrapper_{COLOR}" class="miniitem fighter_wrapper tableau_fighter_player slot_tooltip">
							<div id="tableau_fighter_{COLOR}_counter"
								class="counter fighter_counter textoverlay fighter">0</div>
						</div>
						-->
					</div>

					<div id="tableau_fighter_{COLOR}"
						class="miniboard_row tableau_fighter_player_row">
						<div id="tableau_fighter_F_{COLOR}"
							class="tableau_fighter_F tableau_fighter"></div>
						<div id="tableau_fighter_D_{COLOR}"
							class="tableau_fighter_D tableau_fighter"></div>
						<div id="tableau_fighter_B_{COLOR}"
							class="tableau_fighter_B tableau_fighter"></div>
					</div>

					<div id="miniboard_row_2_{COLOR}" class="miniboard_row">
						<div id="iconperm_C_{COLOR}_counter"
							class="icon C miniitem counter textoverlay"></div>
						<div id="iconperm_W_{COLOR}_counter"
							class="icon W miniitem counter textoverlay"></div>
						<div id="iconperm_S_{COLOR}_counter"
							class="icon S miniitem counter textoverlay"></div>
						<div id="iconperm_P_{COLOR}_counter"
							class="icon P miniitem counter textoverlay"></div>
						<div id="iconperm_T_{COLOR}_counter"
							class="icon T miniitem counter textoverlay"></div>
						<div id="iconperm_R_{COLOR}_counter"
							class="icon R miniitem counter textoverlay">1</div>
					</div>



					<div id="pnum_{PLAYER_ID}" class="pnum fa">#{PLAYER_NO}</div>
				</div>

				<!-- END player_board -->

			</div>
			<div id="common_space" class="common_space">
				<div id="board" class="board shadow">
					<div id="stock_vp"
						class="slot slot_board slot_board_0 stock_resource">
						<div id="stock_vp_counter" class="counter supply_counter">0</div>
					</div>

					<div id="supply_survey" class="slot slot_board slot_board_s">
						<div id="supply_survey_counter" class="counter supply_counter">0</div>
						<div id="x_survey_bottom" class="card card_role card_bottom"></div>
					</div>
					<div id="supply_warfare" class="slot slot_board slot_board_w">
						<div id="supply_warfare_counter" class="counter supply_counter">0</div>
						<div id="x_warfare_bottom" class="card card_role card_bottom"></div>
					</div>
					<div id="supply_colonize" class="slot slot_board slot_board_c">
						<div id="supply_colonize_counter" class="counter supply_counter">0</div>
						<div id="x_colonize_bottom" class="card card_role card_bottom"></div>
					</div>
					<div id="supply_produce" class="slot slot_board slot_board_p">
						<div id="supply_produce_counter" class="counter supply_counter">0</div>
						<div id="x_produce_bottom" class="card card_role card_bottom"></div>
					</div>
					<div id="supply_research" class="slot slot_board slot_board_r">
						<div id="supply_research_counter" class="counter supply_counter">0</div>
						<div id="x_research_bottom" class="card card_role card_bottom"></div>
					</div>
				</div>

				<div id="board2" class="board2">
					<div id="stock_resource"
						class="slot slot_board slot_board_0 stock_resource"></div>
					<div id="stock_fighter"
						class="slot slot_board slot_board_0 stock_fighter">
						<!--  <div id="stock_fighter_counter2" class="counter supply_counter">&#x221e;</div>-->
					</div>
					<div id="supply_planets"
						class="slot slot_board slot_board_1 slot_tooltip">
						<div id="supply_planets_counter" class="counter supply_counter">0</div>
					</div>

					<div id="discard_planets"
						class="slot slot_board slot_board_2 slot_tooltip">
						<div id="discard_planets_counter" class="counter supply_counter">0</div>
					</div>

					<div id="supply_tech_A"
						class="slot slot_board slot_board_3 supply_tech">
						<div id="supply_tech_A_counter"
							class="counter supply_counter slot_tooltip">0</div>
					</div>
					<div id="supply_tech_E"
						class="slot slot_board slot_board_4 supply_tech">
						<div id="supply_tech_E_counter"
							class="counter supply_counter slot_tooltip">0</div>
					</div>
					<div id="supply_tech_M"
						class="slot slot_board slot_board_5 supply_tech">
						<div id="supply_tech_M_counter"
							class="counter supply_counter slot_tooltip">0</div>
					</div>
					<div id="supply_fleet" class="supply_fleet supply_hidden"></div>
				</div>

				<div id="tech_display" class="tech_display inspect_display filter_control_area">
					<div id="tech_display_A_all"
						class="tech_display_A_all tech_display_row">
						<div class="side_title_wrapper">
							<div id="tech_display_A_title" class="side_title">Advanced
								Technologies</div>
						</div>
						<div id="tech_display_A" class="tech_display_A"></div>
					</div>
					<div id="tech_display_E_all"
						class="tech_display_E_all tech_display_row">
						<div class="side_title_wrapper">
							<div id="tech_display_E_title" class="side_title">Fertile
								Technologies</div>
						</div>
						<div id="tech_display_E" class="tech_display_E"></div>
					</div>
					<div id="tech_display_M_all"
						class="tech_display_M_all tech_display_row">
						<div class="side_title_wrapper">
							<div id="tech_display_M_title" class="side_title">Metallic
								Technologies</div>
						</div>
						<div id="tech_display_M" class="tech_display_M"></div>
					</div>
					<div id="tech_display_D_all"
						class="tech_display_D_all tech_display_row">
						<div class="side_title_wrapper">
							<div id="tech_display_D_title" class="side_title">Diverse
								Technologies</div>
						</div>
						<div id="tech_display_D" class="tech_display_D tech_display_D"></div>
					</div>

					<div id="tech_display_F_all"
						class="tech_display_F_all tech_display_row">
						<div class="side_title_wrapper">
							<div id="tech_display_F_title" class="side_title">Upgrades</div>
						</div>
						<div id="tech_display_F" class="tech_display_F"></div>
					</div>

				</div>

				<div id="scenarios"
					class="supply_hidden">
				</div>

				<div id="limbo" class="supply_hidden"></div>

				<div id="dev_null"></div>
			</div>
		</div>
	</div>
</div>

{OVERALL_GAME_FOOTER}
