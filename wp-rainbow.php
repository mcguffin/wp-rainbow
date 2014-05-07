<?php

/*
Plugin Name: WordPress Rainbow
Plugin URI: https://github.com/mcguffin/wp-rainbow
Description: Code Syntax coloring using <a href="http://craig.is/making/rainbows">rainbow</a>.
Author: Jörn Lund
Version: 0.0.1
Author URI: https://github.com/mcguffin

Text Domain: rainbow
Domain Path: /lang/
*/


class WPRainbow {
	private static $_instance = null;
	private $_script_queue = array();
	
	
	public static function get_instance(){
		if ( is_null( self::$_instance ) )
			self::$_instance = new self();
		return self::$_instance;
	}

	private function __construct() {
		add_action( 'init' , array( &$this , 'init' ) );
		add_action( 'init' , array( &$this , 'allow_pre_tag' ) );

		add_action( 'wp_enqueue_scripts' , array( &$this , 'enqueue_assets' ) );
		
		add_option('wprainbow_load_minified' , true );
		add_option('wprainbow_line_numbers' , false );
		add_option('wprainbow_languages' , array( 'css' , 'html' , 'php' , 'javascript' , 'java' , 'python' , 'shell' ) );
		add_option('wprainbow_theme' , 'github' );
		
	}
	function allow_pre_tag() {
		global $allowedposttags;
		if ( ! isset( $allowedposttags['pre'] ) )
			$allowedposttags['pre'] = array('width','class','id','style','title','role');
		$allowedposttags['pre'][] = 'data-language';
		$allowedposttags['pre'][] = 'data-line';
	}
	function init() {
		load_plugin_textdomain( 'rainbow' , false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
		
		$is_script_debug = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
		
		$dependencies = array( 'rainbow-core' );
		
		if ( get_option( 'wprainbow_load_minified' ) ) {
			$rainbow_script_url = plugins_url( '/js/rainbow-custom.min.js' , __FILE__ );
		} else {
			// enqueue core
			// respect WP SCRIPT_DEBUG constant
			$rainbow_script_url = plugins_url( $is_script_debug ? '/js/dev/rainbow.js' : '/js/dev/rainbow.min.js' , __FILE__ );
		}
		$line_number_script_url = plugins_url( $is_script_debug ? '/js/rainbow.linenumbers.js' : '/js/rainbow.linenumbers.min.js' , __FILE__ );
		
		wp_register_script( 'rainbow-core' , $rainbow_script_url , array() , false , true );
		wp_register_script( 'rainbow-linenumbers' , $line_number_script_url , array() , false , true );
		
		$this->_script_queue[] = 'rainbow-core';
		if ( ! get_option( 'wprainbow_load_minified' ) ) {
			// enqueue language modules
			$languages = (array) get_option('wprainbow_languages');
			
			array_unshift( $languages , 'generic' );
			
			foreach ( $languages as $lang ) {
				$script_url = apply_filters('wprainbow_language_module_url' , plugins_url( "/js/dev/language/{$lang}.js" , __FILE__ ) , $lang );
				$handle = "rainbow-lang-{$lang}";
				wp_register_script( $handle , $script_url , array( 'rainbow-core' ) , false , true );
				$this->_script_queue[] = $handle;
			}
		}
		
		if ( get_option( 'wprainbow_line_numbers' ) )
			$this->_script_queue[] = 'rainbow-linenumbers';
		
		// register style
		$theme = get_option( 'wprainbow_theme' );
		$theme_url = apply_filters('wprainbow_theme_url' , plugins_url( "/css/themes/{$theme}.css" , __FILE__ ) , $theme );
		wp_register_style( 'wp-rainbow-css' , $theme_url );

		$linenumberfix_url = plugins_url( "/css/wp-rainbow-linenumbers-fix.css" , __FILE__ );
		wp_register_style( 'wp-rainbow-linenumber-fix' , $linenumberfix_url , array() , "1.0" );
	}
	
	function enqueue_assets() {	
		foreach ( $this->_script_queue as $handle )
			wp_enqueue_script( $handle );
		
		wp_enqueue_style( 'wp-rainbow-css' );
		if ( get_option( 'wprainbow_line_numbers' ) )
			wp_enqueue_style( 'wp-rainbow-linenumber-fix' );
	}
	
	function get_available_languages() {
		$langs = array(
			'c'				=> __('C','rainbow'),
			'coffeescript'	=> __('coffeescript','rainbow'),
			'csharp'		=> __('C#','rainbow'),
			'css'			=> __('CSS','rainbow'),
			'd'				=> __('D','rainbow'),
			'go'			=> __('Go','rainbow'),
			'haskell'		=> __('Haskell','rainbow'),
			'html'			=> __('HTML','rainbow'),
			'java'			=> __('Java','rainbow'),
			'javascript'	=> __('JavaScript','rainbow'),
			'lua'			=> __('Lua','rainbow'),
			'php'			=> __('PHP','rainbow'),
			'python'		=> __('Python','rainbow'),
			'r'				=> __('R','rainbow'),
			'ruby'			=> __('Ruby','rainbow'),
			'scheme'		=> __('Scheme','rainbow'),
			'shell'			=> __('Shell script','rainbow'),
			'smalltalk'		=> __('Smalltalk','rainbow'),
		);
		return apply_filters( 'wprainbow_available_languages' , $langs );
	}
	function get_available_themes() {
		$theme_instance = new WP_Theme( 'themes' , plugin_dir_path(__FILE__) . 'css' );
		$css_files = $theme_instance->get_files('css');
		$themes = array();
		foreach ( $css_files as $filename => $path ) {
			$filename = (basename($filename,'.css'));
			$themes[ $filename ] = ucwords(str_replace('-',' ',$filename));
		}
		return apply_filters( 'wprainbow_available_themes' , $themes );
		return $themes;
	}
}

function wprainbow_get_available_languages(){
	return WPRainbow::get_instance()->get_available_languages();
}

function wprainbow_get_available_themes(){
	return WPRainbow::get_instance()->get_available_themes();
}

function load_options() {
	if ( current_user_can('manage_options') )
		require_once plugin_dir_path(__FILE__) . '/inc/class-wp-rainbow-editor.php';
}

if ( is_admin() ) {
	require_once plugin_dir_path(__FILE__) . '/inc/class-wp-rainbow-options.php';
	add_action('plugins_loaded','load_options');
}


WPRainbow::get_instance();


