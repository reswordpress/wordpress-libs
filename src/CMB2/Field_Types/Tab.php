<?php

namespace Lipe\Lib\CMB2\Field_Types;

use CMB2_Field;
use Lipe\Lib\Traits\Singleton;

/**
 * Support Tabs in meta boxes
 *
 * @author  Mat Lipe
 * @since   1.2.0
 *
 * @package Lipe\Lib\CMB2\Field_Types
 */
class Tab {
	use Singleton;

	/**
	 * Current CMB2 instance
	 *
	 * @var \CMB2
	 */
	protected $cmb;

	/**
	 *
	 * @var boolean
	 */
	protected $has_tabs = false;

	/**
	 * Active Panel
	 *
	 * @var string
	 */
	protected $active_panel = '';

	protected $fields_output = [];


	/**
	 * Called via self::init_once() from various entry points
	 *
	 * @return void
	 */
	protected function hook() : void {
		add_action( 'cmb2_before_form', [ $this, 'opening_div' ], 10, 4 );
		add_action( 'cmb2_after_form', [ $this, 'closing_div' ], 20, 4 );

		add_action( 'cmb2_before_form', [ $this, 'render_nav' ], 20, 4 );
		add_action( 'cmb2_after_form', [ $this, 'show_panels' ], 10, 4 );

		add_filter( 'cmb2_wrap_classes', [ $this, 'add_wrap_class' ], 10, 2 );
	}


	/**
	 *
	 * @param string     $cmb_id
	 * @param string|int $object_id
	 * @param string     $object_type
	 * @param \CMB2      $cmb
	 *
	 * @return void
	 */
	public function opening_div( $cmb_id, $object_id, $object_type, $cmb ) {
		if( !$cmb->prop( 'tabs' ) ){
			return;
		}
		$this->cmb = $cmb;
		$this->has_tabs = true;

		$tab_style = $cmb->prop( 'tab_style' );
		$class = 'cmb-tabs clearfix';

		if( null !== $tab_style && 'default' !== $tab_style ){
			$class .= ' cmb-tabs-' . $tab_style;
		}
		$this->styles();

		echo '<div class="' . $class . '">';

	}


	public function closing_div() : void {
		if( !$this->has_tabs ){
			return;
		}
		echo '</div>';

		$this->has_tabs = false;
		$this->fields_output = [];

	}


	/**
	 *
	 * @param       $cmb_id
	 * @param       $object_id
	 * @param       $object_type
	 * @param \CMB2 $cmb
	 *
	 * @return void
	 */
	public function render_nav( $cmb_id, $object_id, $object_type, $cmb ) {
		$tabs = $cmb->prop( 'tabs' );

		if( $tabs ){
			echo '<ul class="cmb-tab-nav">';
			$active_nav = true;
			foreach( $tabs as $key => $label ){
				$class = "cmb-tab-$key";
				if( $active_nav ){
					$class .= ' cmb-tab-active';
					$this->active_panel = $key;
					$active_nav = false;
				}

				printf(
					'<li class="%s" data-panel="%s"><a href="#"><span>%s</span></a></li>',
					$class,
					$key,
					$label
				);
			}

			echo '</ul>';

		}
	}


	public function add_wrap_class( $classes ) {
		if( $this->has_tabs ){
			$classes[] = 'cmb-tabs-panel';
			if( !empty( $this->fields_output ) ){
				$classes[] = 'cmb2-wrap-tabs';
			}
		}

		return array_unique( $classes );
	}


	/**
	 *
	 * @param $field_args
	 * @param $field
	 *
	 * @return void
	 */
	public function render_field( $field_args, CMB2_Field $field ) : void {
		ob_start();
		if( 'group' === $field_args[ 'type' ] ){
			$this->cmb->render_group_callback( $field_args, $field );
		} else {
			$field->render_field_callback();
		}
		$output = ob_get_clean();
		echo $this->capture_fields( $output, $field_args, $field );
	}


	/**
	 *
	 * @param       $cmb_id
	 * @param       $object_id
	 * @param       $object_type
	 * @param \CMB2 $cmb
	 *
	 * @return void
	 */
	public function show_panels( $cmb_id, $object_id, $object_type, $cmb ) : void {
		if( !$this->has_tabs || empty( $this->fields_output ) ){
			return;
		}

		echo '<div class="', esc_attr( $cmb->box_classes() ), '">
					<div id="cmb2-metabox-', sanitize_html_class( $cmb_id ), '" class="cmb2-metabox cmb-field-list">';

		foreach( $this->fields_output as $tab => $fields ){
			$active_panel = $this->active_panel === $tab ? 'show' : '';
			echo '<div class="' . $active_panel . ' cmb-tab-panel cmb2-metabox cmb-tab-panel-' . $tab . '">';
			echo implode( '', $fields );
			echo '</div>';
		}

		echo '</div></div>';
	}


	public function capture_fields( $output, $field_args ) : string {
		if( !$this->has_tabs || !isset( $field_args[ 'tab' ] ) ){
			return $output;
		}

		$tab = $field_args[ 'tab' ];

		if( !isset( $this->fields_output[ $tab ] ) ){
			$this->fields_output[ $tab ] = [];
		}
		$this->fields_output[ $tab ][] = $output;

		return '';
	}


	protected function styles() {
		static $displayed = false;
		if( $displayed ){
			return;
		}
		$displayed = true;
		?>
		<script>
			jQuery( function( $ ){
				'use strict';
				$( '.cmb-tab-nav' ).on( 'click', 'a', function( e ){
					e.preventDefault();
					var $li = $( this ).parent(), panel = $li.data( 'panel' ),
					    $wrapper                        = $li.parents( ".cmb-tabs" ).find( '.cmb2-wrap-tabs' ),
					    $panel                          = $wrapper.find( '.cmb-tab-panel-' + panel );

					$li.addClass( 'cmb-tab-active' ).siblings().removeClass( 'cmb-tab-active' );
					$wrapper.find( '.cmb-tab-panel' ).removeClass( 'show' );
					$panel.addClass( 'show' );
				} );
			} );
		</script>
		<style type="text/css">
			/*
			<?= __FILE__; ?>   */
			.clearfix:after {
				visibility: hidden;
				display: block;
				font-size: 0;
				content: " ";
				clear: both;
				height: 0;
			}

			.clearfix {
				display: inline-block;
			}

			/* start commented backslash hack \*/
			* html .clearfix {
				height: 1%;
			}

			.clearfix {
				display: block;
			}

			/* close commented backslash hack */

			/*--------------------------------------------------------------
			Base style
			--------------------------------------------------------------*/
			.cmb-tabs .cmb-th label {
				color: #555;
				font-size: 12px;
			}

			.cmb-tabs .cmb-type-group .cmb-row,
			.cmb-tabs .cmb2-postbox .cmb-row {
				margin: 0 0 0.8em;
				padding: 0 0 0.8em;
			}

			.cmb-tabs span.cmb2-metabox-description {
				display: block;
			}

			.cmb-tabs .cmb-remove-row-button {
				background-color: #e60000;
				border: medium none;
				border-radius: 25px;
				color: #fff;
				height: 20px;
				padding: 0;
				text-indent: -999em;
				width: 20px;
				position: relative;
				-webkit-box-shadow: none;
				box-shadow: none;
			}

			.cmb-tabs .cmb-repeat-row {
				position: relative;
			}

			.cmb-tabs .cmb-remove-row {
				display: inline;
				margin: 0;
				padding: 0;
			}

			.cmb-tabs .cmb-repeat-row .cmb-td {
				display: inline-block;
			}

			/*--------------------------------------------------------------
			CMB2 Tabs
			--------------------------------------------------------------*/
			.cmb-tabs {
				margin: -6px -12px -12px;
				overflow: hidden;
			}

			.cmb-tabs ul.cmb-tab-nav:after {
				background-color: #fafafa;
				border-right: 1px solid #eee;
				bottom: -9999em;
				content: "";
				display: block;
				height: 9999em;
				left: 0;
				position: absolute;
				width: calc(100% - 1px);
			}

			.cmb-tabs ul.cmb-tab-nav {
				background-color: #fafafa;
				-webkit-box-sizing: border-box;
				box-sizing: border-box;
				display: block;
				line-height: 1em;
				margin: 0;
				padding: 0;
				position: relative;
				width: 20%;
				float: left;
			}

			.cmb-tabs ul.cmb-tab-nav li {
				display: block;
				margin: 0;
				padding: 0;
				position: relative;
			}

			.cmb-tabs i,
			.cmb-tabs i:before {
				font-size: 16px;
				vertical-align: middle;
			}

			.cmb-tabs ul.cmb-tab-nav li a {
				border-right: 1px solid #eee;
				border-left: 2px solid #fafafa;
				-webkit-box-shadow: none;
				box-shadow: none;
				display: block;
				line-height: 20px;
				margin: 0;
				padding: 10px;
				text-decoration: none;
				display: -webkit-box;
				display: -ms-flexbox;
				display: flex;
				-webkit-box-align: center;
				-ms-flex-align: center;
				align-items: center;
				font-weight: 600;
			}

			.cmb-tabs ul.cmb-tab-nav li i {
				display: -webkit-box;
				display: -ms-flexbox;
				display: flex;
				-webkit-box-align: center;
				-ms-flex-align: center;
				align-items: center;
			}

			.cmb-tabs ul.cmb-tab-nav li i,
			.cmb-tabs ul.cmb-tab-nav li img {
				padding: 0 5px 0 0px;
			}

			.cmb-tabs ul.cmb-tab-nav li a {
				color: #555;
				border: 1px solid transparent;
			}

			.cmb-tabs ul.cmb-tab-nav li.cmb-tab-active a {
				background-color: #fff;
				position: relative;
				border: 1px solid #eee;
				border-left: 3px solid #00a0d2;
				border-right-color: #fff;
			}

			.cmb-tabs ul.cmb-tab-nav li:first-of-type.cmb-tab-active a {
				border-top: none;
			}

			.cmb-tabs .cmb-tabs-panel {
				-webkit-box-sizing: border-box;
				box-sizing: border-box;
				color: #555;
				display: none !important;
				width: 80%;
				padding: 0;
			}

			.cmb-tabs .cmb-tabs-panel.cmb2-wrap-tabs {
				display: -webkit-inline-box !important;
				display: -ms-inline-flexbox !important;
				display: inline-flex !important;
			}

			.cmb-tabs .cmb2-metabox {
				display: block;
				width: 100%;
			}

			.cmb-tabs .cmb-th {
				width: 18%;
			}

			.cmb-tabs .cmb-th,
			.cmb-tabs .cmb-td {
				padding: 0 2% 0 2%;
				-webkit-box-sizing: border-box;
				box-sizing: border-box;
			}

			.cmb-tabs .cmb-th + .cmb-td,
			.cmb-tabs .cmb-th + .cmb-td {
				float: right;
				width: 82%;
			}

			.cmb2-wrap-tabs .cmb-tab-panel {
				display: none;
			}

			.cmb2-wrap-tabs .cmb-tab-panel.show {
				display: block;
			}

			/*--------------------------------------------------------------
			Classic Tab
			--------------------------------------------------------------*/
			.cmb-tabs.cmb-tabs-classic ul.cmb-tab-nav {
				width: 100%;
				float: none;
				background-color: #fafafa;
				border-right: medium none;
				padding: 0;
				border-bottom: 1px solid #dedede;
				padding-top: 15px;
			}

			.cmb-tabs.cmb-tabs-classic .cmb-tab-nav li {
				background: #ebebeb none repeat scroll 0 0;
				margin: 0 5px -1px 5px;
				display: inline-block;
			}

			.cmb-tabs.cmb-tabs-classic .cmb-tab-nav li:first-of-type {
				margin-left: 18px;
			}

			.cmb-tabs.cmb-tabs-classic ul.cmb-tab-nav::after {
				display: none;
			}

			.cmb-tabs.cmb-tabs-classic .cmb-tabs-panel {
				width: 100%;
			}

			.cmb-tabs.cmb-tabs-classic .cmb-tab-panel {
				/*background: #ebebeb none repeat scroll 0 0;*/
				padding-top: 10px;
			}

			.cmb-tabs.cmb-tabs-classic ul.cmb-tab-nav li a {
				padding: 8px 12px;
				background-color: #fafafa;
				border: none;
				border-bottom: 1px solid #dedede;
			}

			.cmb-tabs.cmb-tabs-classic ul.cmb-tab-nav li.cmb-tab-active a {
				background-color: #fff;
				border-color: #fff;
				border: none;
				border-top: 2px solid #00a0d2;
				border-bottom: 1px solid #fff;
			}

			/*--------------------------------------------------------------
			Media Query
			--------------------------------------------------------------*/
			@media (max-width: 750px) {
				.cmb-tabs ul.cmb-tab-nav {
					width: 10%;
				}

				.cmb-tabs .cmb-tabs-panel {
					width: 90%;
				}

				.cmb-tabs ul.cmb-tab-nav li i,
				.cmb-tabs ul.cmb-tab-nav li img {
					padding: 0;
					margin: 0 auto;
					text-align: center;
					display: block;
					max-width: 25px;
				}

				.cmb-tabs ul.cmb-tab-nav li span {
					padding: 10px;
					position: relative;
					text-indent: -999px;
					display: none;
				}
			}

			@media (max-width: 500px) {
				.cmb-tabs .cmb-th,
				.cmb-tabs .cmb-th + .cmb-td,
				.cmb-tabs .cmb-th + .cmb-td {
					float: none;
					width: 96%;
				}

				.cmb-tabs .cmb-repeat-row .cmb-td {
					width: auto;
				}
			}
		</style>
		<?php

	}

}
