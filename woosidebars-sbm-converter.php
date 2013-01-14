<?php
/*
Plugin Name: WooSidebars - Sidebar Manager to WooSidebars Converter
Plugin URI: http://woothemes.com/
Description: Convert your custom sidebars in the WooFramework's Sidebar Manager to Widget Areas in WooSidebars, with the appropriate conditions assigned.
Version: 1.1.1
Author: WooThemes
Author URI: http://woothemes.com/
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/
/*  Copyright 2012  WooThemes  (email : info@woothemes.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
    require_once( 'classes/class-woosidebars-sbm-converter.php' );

	global $woosidebars_sbm_converter;
	$woosidebars_sbm_converter = new Woosidebars_SBM_Converter( __FILE__ );
	$woosidebars_sbm_converter->version = '1.1.1';
?>