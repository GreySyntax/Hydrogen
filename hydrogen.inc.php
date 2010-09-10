<?php
/*
 * Copyright (c) 2009 - 2010, Frosted Design
 * All rights reserved.
 *
 *************************************************************************
 * Hydrogen loader.  require_once() this file from any php page that can
 * be loaded directly.  This file will autoload any other hydrogen classes
 * as they're used, so no others requires are necessary.
 */
namespace hydrogen;

function load($namespace) {
	$path_args = explode ( '\\', $namespace );
	$path_count = count ( $path_args );
	$ns_path = explode('\\', $namespace); //define BASE in your root path

	for($i = 0; $i < $path_count; $i ++) {
		$ns_path .= $path_args[$i];
		
		if (! (($i + 1) == $path_count)) {
			$ns_path .= '/';
		}
	}
	
	$loc = $ns_path;
	
	if ( is_dir( $loc ) && !is_dir( $loc . '.php' ) ) {
		//Requre all files in directory ending with ".php"
		$scripts = array();
		
		//glob is faster than readdir etc, so why not!
		foreach ( glob( $lock . '/*.php' ) as $f ) {
			$scripts[] = $f;
		}
		
		if ( is_array( $scripts ) && count( $scripts ) > 0 ) {
			foreach ( $scripts as $f ) {
				loadPath( $loc . $f );	
			}
		}
		return;
	}
	
	loadPath($loc . '.php');
	return;
}

function loadPath($absPath) {
	return include_once($absPath);
}

spl_autoload_register(__NAMESPACE__ . '\load');
include(__DIR__ . DIRECTORY_SEPARATOR . 'hydrogen.autoconfig.php');

?>