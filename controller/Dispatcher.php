<?php
/*
 * Copyright (c) 2009 - 2010, Frosted Design
 * All rights reserved.
 */

namespace hydrogen\controller;

use hydrogen\controller\exceptions\NoSuchMethodException;
use hydrogen\controller\exceptions\MissingArgumentException;

/**
 * The Dispatcher class processes a single page request with a list of rules that
 * determines to which controller the request should be sent.
 *
 * Using Dispatcher is a simple process.  The first step is defining dispatch rules,
 * which is done by calling the Dispatcher::add______Rule family of functions.  See the
 * documentation for those functions for details.  Once the rules have been defined,
 * {@link #dispatch} can be called to send the request to the appropriate
 * controller.
 *
 * When Dispatcher::dispatch() is called, each rule is checked in the order in which
 * it was set.  Once a rule matches the current request, the associated controller is
 * immediately triggered and no other rules are processed.
 *
 * There are two types of rules: Mapping rules and Matching rules.  A mapping rule should
 * be used when the name of the controller/function to be called is contained in the
 * url somewhere.  The most popular mapping rule (in this and other frameworks) is
 * the pathinfo auto map rule, which takes URLs like this:
 *
 * <pre>
 * http://mysite.com/myapp/index.php/blog/post/82/hi_there_everyone
 * </pre>
 *
 * And maps them to the Blog controller, calling the function post() with the
 * arguments "82" and "hi_there_everyone".  Optionally, a namespace and/or a class
 * suffix could be provided to trigger, for example, the myapp\controllers\BlogController
 * class instead of just the "Blog" class.  Note, however, that for mapping functions,
 * the first letter of the controller name is automatically capitalized when looking
 * for the matching controller class.  This is done to comply with popular naming
 * conventions for PHP, where all class names start with a capital letter.
 *
 * Matching rules trigger whenever certain conditions are met in the URL, and redirect
 * to a specified controller and function.  Often, arguments can be pulled from these
 * conditions and passed to the specified function.
 *
 * In the case where a class autoloader is not being used, paths to the controller
 * PHP files may be specified with the {@link #addControllerInclude} and
 * {@link #addControllerIncludes} commands.  The PHP file for a given controller is
 * included only when a rule with that controller is matched.
 *
 * If the Dispatcher fails to match the request to any of the rules, 
 * Dispatcher::dispatch() returns false.  At this point, a 404 page can be displayed
 * manually if that's the desired effect.  Another option is to set a "Match All" rule
 * as the final rule, which sends any request that hasn't matched any other rule to a
 * certain controller.  This controller could load a 404 page, for simplicity and
 * consistency.
 *
 * Remember, though, to always set a Home Match rule, or else direct loads to the
 * public-facing PHP file will be ignored!
 */
class Dispatcher {
	const RULE_HOME_MATCH = 0;
	const RULE_PATHINFO_AUTO_MAP = 1;
	const RULE_PATHINFO_FOLDER_MAP = 2;
	const RULE_PATHINFO_REGEX_MAP = 3;
	const RULE_PATHINFO_REGEX_MATCH = 4;
	const RULE_PATHINFO_MATCH = 5;
	const RULE_GETVAR_MAP = 6;
	const RULE_GETVAR_MATCH = 7;
	const RULE_GETVAR_REGEX_MATCH = 8;
	const RULE_URL_REGEX_MAP = 9;
	const RULE_URL_REGEX_MATCH = 10;
	const RULE_MATCH_ALL = 11;
	
	protected static $dispatchRules = array();
	protected static $controllerPaths = array();
	protected static $oldHandler = false;
	
	/**
	 * Initiates the process of analyzing the current request against the registered
	 * set of rules.  Rules should be added to the dispatcher before calling this
	 * function.  When a rule matches the current request, the request is sent to
	 * the controller and function specified in that rule.  At the completion of the
	 * controller's execution, this function returns true.  If no rules match, this
	 * function returns false.
	 *
	 * @return boolean true if a rule matched and the request was successfully passed
	 * 		to a controller/function; false if no rules match.  By extension, this
	 * 		function will return false if no rules have been defined.
	 */
	public static function dispatch() {
		$handled = false;
		foreach (static::$dispatchRules as $rule) {
			switch ($rule[0]) {
				case self::RULE_HOME_MATCH:
					$handled = static::dispatchHomeMatch(
						$rule[1]['cName'],
						$rule[1]['fName']
						);
					break;
				case self::RULE_PATHINFO_AUTO_MAP:
					$handled = static::dispatchPathInfoAutoMap(
						$rule[1]['namespace'],
						$rule[1]['suffix']
						);
					break;
				case self::RULE_PATHINFO_FOLDER_MAP:
					$handled = static::dispatchPathInfoFolderMap(
						$rule[1]['cIndex'],
						$rule[1]['fIndex'],
						$rule[1]['aIndex'],
						$rule[1]['namespace'],
						$rule[1]['suffix']
						);
					break;
				case self::RULE_PATHINFO_REGEX_MAP:
					$handled = static::dispatchPathInfoRegexMap(
						$rule[1]['regex'],
						$rule[1]['cIndex'],
						$rule[1]['fIndex'],
						$rule[1]['aIndex'],
						$rule[1]['namespace'],
						$rule[1]['suffix']
						);
					break;
				case self::RULE_PATHINFO_REGEX_MATCH:
					$handled = static::dispatchPathInfoRegexMatch(
						$rule[1]['regex'],
						$rule[1]['cName'],
						$rule[1]['fName'],
						$rule[1]['aIndex']
						);
					break;
				case self::RULE_PATHINFO_MATCH:
					$handled = static::dispatchPathInfoMatch(
						$rule[1]['match'],
						$rule[1]['cName'],
						$rule[1]['fName']
						);
					break;
				case self::RULE_GETVAR_MAP:
					$handled = static::dispatchGetVarMap(
						$rule[1]['cVar'],
						$rule[1]['fVar'],
						$rule[1]['aVar'],
						$rule[1]['namespace'],
						$rule[1]['suffix']
						);
					break;
				case self::RULE_GETVAR_MATCH:
					$handled = static::dispatchGetVarMatch(
						$rule[1]['match'],
						$rule[1]['cName'],
						$rule[1]['fName'],
						$rule[1]['aVar']
						);
					break;
				case self::RULE_GETVAR_REGEX_MATCH:
					$handled = static::dispatchGetVarRegexMatch(
						$rule[1]['regex'],
						$rule[1]['cName'],
						$rule[1]['fName'],
						$rule[1]['aVar']
						);
					break;
				case self::RULE_URL_REGEX_MAP:
					$handled = static::dispatchUrlRegexMap(
						$rule[1]['regex'],
						$rule[1]['cIndex'],
						$rule[1]['fIndex'],
						$rule[1]['aIndex'],
						$rule[1]['namespace'],
						$rule[1]['suffix']
						);
					break;
				case self::RULE_URL_REGEX_MATCH:
					$handled = static::dispatchUrlRegexMatch(
						$rule[1]['regex'],
						$rule[1]['cName'],
						$rule[1]['fName'],
						$rule[1]['aIndex']
						);
					break;
				case self::RULE_MATCH_ALL:
					$handled = static::dispatchMatchAll(
						$rule[1]['cName'],
						$rule[1]['fName']
						);
					break;
			}
			if ($handled === true)
				return true;
		}
		return false;
	}
	
	/**
	 * Associates a controller name with a path to a PHP file.  If a rule matches
	 * during the {@link #dispatch} call, the matching controller's PHP file
	 * will be automatically included if it's been defined.  Do not use this
	 * function if the controller classes being used with Dispatcher are
	 * automatically included by other means.
	 *
	 * @param controllerName string The full name of the controller for which to
	 * 		associate the PHP file.  A full name includes the entire namespace
	 * 		and class name.
	 * @param phpPath string The path to the PHP file to include.  This can either
	 * 		be an absolute path, or a path relative to this application's base path.
	 */
	public static function addControllerInclude($controllerName, $phpPath) {
		if ($controllerName[0] !== '\\')
			$controllerName = '\\' . $controllerName;
		static::$controllerPaths[$controllerName] = $phpPath;
	}
	
	/**
	 * Adds an array of controller classes and their PHP paths as key => value pairs.
	 * These paths function the same way as they do in the {@link #addControllerInclude}
	 * method.
	 *
	 * @param arrayMap array An associative array of full class names => PHP files to
	 * 		include as specified in {@link #addControllerInclude}.  Each controller
	 * 		name MUST begin with a backslash in order to be matched.
	 */
	public static function addControllerIncludeArray($arrayMap) {
		static::$controllerPaths = array_merge(static::$controllerPaths, $arrayMap);
	}
	
	/**
	 * Appends a rule of the specified type to the Dispatcher's rule list.  It's
	 * rarely appropriate to call this function directly -- instead, see Dispatcher's
	 * add________Rule family of functions for adding specific rule types.
	 *
	 * @param type int The Dispatcher constant of the rule type to be added.
	 * @param argArray array An array of arguments required for the specified rule
	 * 		type.
	 */
	public static function addRule($type, $argArray) {
		static::$dispatchRules[] = array($type, $argArray);
	}
	
	/**
	 * Appends an array of multiple rule arrays to the Dispatcher's rule set.  It's
	 * rarely appropriate to call this function directly -- instead, see Dispatcher's
	 * add________Rule family of functions for adding specific rule types.
	 *
	 * @param ruleArray array A properly formatted array of rule arrays.
	 */
	public static function addRules($ruleArray) {
		static::$dispatchRules = array_merge(static::$dispatchRules, $ruleArray);
	}
	
	/**
	 * Matches when a PHP file has been called with either no PATH_INFO data, or just
	 * a slash for the PATH_INFO.  This is the equivalent to calling the function
	 * {@link #addPathInfoMatch} with a blank match argument.
	 *
	 * When using GetVar-related dispatching rules, make sure they come before this one.
	 * Otherwise, this rule will match because there's no PATH_INFO and the GET
	 * arguments will be ignored.
	 *
	 * @param cName string The full controller name to call, including the namespace.
	 * @param fName function The function name to call within the given controller.
	 */
	public static function addHomeMatchRule($cName, $fName) {
		static::addRule(self::RULE_HOME_MATCH,
			array(
				"cName" => $cName,
				"fName" => $fName
				)
			);
	}
	
	/**
	 * Dispatches a rule set by {@link #addHomeMatchRule}.
	 *
	 * @param cName string The full controller name to call, including the namespace.
	 * @param fName function The function name to call within the given controller.
	 */
	protected static function dispatchHomeMatch($cName, $fName) {
		return static::dispatchPathInfoMatch('', $cName, $fName);
	}
		
	/**
	 * Matches in the case that a PATH_INFO exists with a controller name as its
	 * first element and a function name as its second element, with all other
	 * elements passed as arguments.
	 *
	 * The controller name element will be automatically capitalized by Dispatcher
	 * during processing, as all class names should start with a capital letter by
	 * common PHP naming convention.  Past that, a supplied namespace can be
	 * prepended to the controller name, and a suffix can be appended to it.  That
	 * allows, for example, the \myapp\controllers\HomeController to be called
	 * whenever the word "home" is passed as the first PATH_INFO element.
	 *
	 * If no function name is given in PATH_INFO, Dispatcher will attempt to call
	 * a method named "index" within the given controller.  If no such method exists,
	 * the rule does not match.
	 *
	 * If the function specified has required arguments, and not enough elements were
	 * given in the PATH_INFO to meet that number of required arguments, the rule
	 * will not match.  If this is not the intended functionality, a good solution
	 * would be to make the controller function's arguments optional.
	 *
	 * Examples (PATH_INFO value => What happens):
	 * /home/welcome => Home controller's "welcome" function is called.
	 * /home => Home controller's "index" function is called if it exists, no match 
	 * 		otherwise.
	 * /blog/post/43/hello_world => Blog controller's "post" function is called with
	 * 		the arguments "43" and "hello_world".
	 * /blog/post => If the Blog controller's "post" function requires the above two
	 * 		arguments, this rule will not match.  Otherwise, the function will be
	 *		called with no arguments.
	 *
	 * NOTE: Due to a limitation in PHP's error handling, whenever a warning is 
	 * encountered in the controller and/or view that this rule triggers, the warning 
	 * will be send as E_USER_WARNING rather than E_WARNING.  This is the only rule
	 * that has this effect.
	 *
	 * @param namespace string|boolean The namespace to prepend to the PATH_INFO-provided
	 * 		controller name, or false to use the root namespace.
	 * @param suffix string|boolean The suffix to append to the PATH_INFO-provided
	 * 		controller name, or false to not append a suffix.
	 */
	public static function addPathInfoAutoMapRule($namespace=false, $suffix=false) {
		static::addRule(self::RULE_PATHINFO_AUTO_MAP,
			array(
				"namespace" => $namespace,
				"suffix" => $suffix
				)
			);
	}
	
	/**
	 * Dispatches a rule set by {@link #addPathInfoAutoMapRule}.
	 *
	 * @param namespace string|boolean The namespace to prepend to the PATH_INFO-provided
	 * 		controller name, or false to use the root namespace.
	 * @param suffix string|boolean The suffix to append to the PATH_INFO-provided
	 * 		controller name, or false to not append a suffix.
	 */
	protected static function dispatchPathInfoAutoMap($namespace, $suffix) {
		if (isset($_SERVER['PATH_INFO'])) {
			$tokens = explode('/', $_SERVER['PATH_INFO']);
			if (count($tokens) >= 2) {
				if (count($tokens) > 3)
					$args = array_slice($tokens, 3);
				else
					$args = array();
				return static::passRequest(
					$tokens[1],
					isset($tokens[2]) ? $tokens[2] : "index",
					$args, $namespace, $suffix, true);
			}
		}
		return false;
	}
	
	public static function addPathInfoFolderMapRule($cIndex, $fIndex, $argIndexArray, $namespace=false, $suffix=false) {
		static::addRule(self::RULE_PATHINFO_FOLDER_MAP,
			array(
				"cIndex" => $cIndex,
				"fIndex" => $fIndex,
				"aIndex" => $argIndexArray,
				"namespace" => $namespace,
				"suffix" => $suffix
				)
			);
	}
	
	protected static function dispatchPathInfoFolderMap($cIndex, $fIndex, $aIndex, $namespace, $suffix) {
		if (isset($_SERVER['PATH_INFO'])) {
			$tokens = explode('/', $_SERVER['PATH_INFO']);
			return static::dispatchMapFromTokens($tokens, $cIndex, $fIndex, $aIndex, $namespace, $suffix);
		}
		return false;
	}
	
	public static function addPathInfoRegexMapRule($regex, $cIndex, $fIndex, $argIndexArray, $namespace=false, $suffix=false) {
		static::addRule(self::RULE_PATHINFO_REGEX_MAP,
			array(
				"regex" => $regex,
				"cIndex" => $cIndex,
				"fIndex" => $fIndex,
				"aIndex" => $argIndexArray,
				"namespace" => $namespace,
				"suffix" => $suffix
				)
			);
	}
	
	protected static function dispatchPathInfoRegexMap($regex, $cIndex, $fIndex, $aIndex, $namespace, $suffix) {
		if (isset($_SERVER['PATH_INFO'])) {
			if (preg_match($regex, $_SERVER['PATH_INFO'], $tokens) > 0) {
				return static::dispatchMapFromTokens($tokens, $cIndex, $fIndex, $aIndex,
					$namespace, $suffix);
			}
		}
		return false;
	}
	
	public static function addPathInfoRegexMatchRule($regex, $cName, $fName,
			$argIndexArray) {
		static::addRule(self::RULE_PATHINFO_REGEX_MATCH,
			array(
				"regex" => $regex,
				"cName" => $cName,
				"fName" => $fName,
				"aIndex" => $argIndexArray
				)
			);
	}
	
	protected static function dispatchPathInfoRegexMatch($regex, $cName, 
			$fName, $aIndex) {
		$pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
		if (preg_match($regex, $pathInfo, $tokens) > 0)
			return static::dispatchMatchFromTokens($tokens, $cName, $fName, $aIndex);
		return false;
	}
	
	public static function addPathInfoMatchRule($match, $cName, $fName) {
		static::addRule(self::RULE_PATHINFO_REGEX_MATCH,
			array(
				"match" => $match,
				"cName" => $cName,
				"fName" => $fName
				)
			);
	}
	
	protected static function dispatchPathInfoMatch($match, $cName, $fName) {
		if ((isset($_SERVER['PATH_INFO']) && $_SERVER['PATH_INFO'] === $match)
				|| (!isset($_SERVER['PATH_INFO']) && $match === '')) {
			return static::passRequest($cName, $fName);
		}
		return false;
	}
	
	public static function addGetVarMapRule($cVar, $fVar, $argVars,
			$namespace=false, $suffix=false) {
		static::addRule(self::RULE_GETVAR_MAP,
			array(
				"cVar" => $cVar,
				"fVar" => $fVar,
				"aVar" => $argVars,
				"namespace" => $namespace,
				"suffix" => $suffix
				)
			);
	}
	
	protected static function dispatchGetVarMap($cVar, $fVar, $aVar,
			$namespace, $suffix) {
		if (isset($_GET[$cVar]) && isset($_GET[$fVar])) {
			return static::passRequest(
				$_GET[$cVar],
				$_GET[$fVar],
				static::getArgsFromAssocArray($_GET, $aVar),
				$namespace,
				$suffix);
		}
		return false;
	}
	
	public static function addGetVarMatchRule($matchArray, $cName, $fName, $argVars) {
		static::addRule(self::RULE_GETVAR_MATCH,
			array(
				"match" => $matchArray,
				"cName" => $cName,
				"fName" => $fName,
				"aVar" => $argVars
				)
			);
	}
	
	protected static function dispatchGetVarMatch($match, $cName, $fName, $aVar) {
		foreach ($match as $key => $val) {
			if (!isset($_GET[$key]) || $_GET[$key] != $val)
				return false;
		}
		return static::passRequest($cName, $fName,
			static::getArgsFromAssocArray($_GET, $aVar));
	}
	
	public static function addGetVarRegexMatchRule($matchArray, $cName,
			$fName, $argVars) {
		static::addRule(self::RULE_GETVAR_REGEX_MATCH,
			array(
				"match" => $matchArray,
				"cName" => $cName,
				"fName" => $fName,
				"aVar" => $argVars
				)
			);
	}
	
	protected static function dispatchGetVarRegexMatch($regex, $cName, $fName, $aVar) {
		foreach ($regex as $key => $val) {
			if (!isset($_GET[$key]) || !preg_match($val, $_GET[$key]))
				return false;
		}
		return static::passRequest($cName, $fName,
			static::getArgsFromAssocArray($_GET, $aVar));
	}
	
	public static function addUrlRegexMapRule($regex, $cIndex, $fIndex,
			$argIndexArray, $namespace=false, $suffix=false) {
		static::addRule(self::RULE_URL_REGEX_MAP,
			array(
				"regex" => $regex,
				"cIndex" => $cIndex,
				"fIndex" => $fIndex,
				"aIndex" => $argIndexArray,
				"namespace" => $namespace,
				"suffix" => $suffix
				)
			);
	}
	
	protected static function dispatchUrlRegexMap($regex, $cIndex, $fIndex,
			$aIndex, $namespace, $suffix) {
		if (preg_match($regex, statis::getRequestedURL(), $matches)) {
			return static::dispatchMapFromTokens($matches, $cIndex, $fIndex,
				$aIndex, $namespace, $suffix);
		}
		return false;
	}
	
	public static function addUrlRegexMatchRule($regex, $cName, $fName,
			$argIndexArray) {
		static::addRule(self::RULE_URL_REGEX_MATCH,
			array(
				"regex" => $regex,
				"cName" => $cName,
				"fName" => $fName,
				"aIndex" => $argIndexArray
				)
			);
	}
	
	protected static function dispatchUrlRegexMatch($regex, $cName, $fName, $aIndex) {
		if (preg_match($regex, statis::getRequestedURL(), $matches))
			return static::dispatchMatchFromTokens($matches, $cName, $fName, $aIndex);
		return false;
	}
	
	public static function addMatchAllRule($cName, $fName) {
		static::addRule(self::RULE_MATCH_ALL,
			array(
				"cName" => $cName,
				"fName" => $fName
				)
			);
	}
	
	protected static function dispatchMatchAll($cName, $fName) {
		return static::passRequest($cName, $fName);
	}
	
	protected static function dispatchMapFromTokens($tokens, $cIndex, $fIndex,
			$aIndex, $namespace, $suffix) {
		if (isset($tokens[$cIndex]) && isset($tokens[$fIndex])) {
			$args = static::getArgsFromTokens($tokens, $aIndex);
			return static::passRequest(
				$tokens[$cIndex], $tokens[$fIndex],
				$args, $namespace, $suffix);
		}
		return false;
	}
	
	protected static function dispatchMatchFromTokens($tokens, $cName, $fName,
			$aIndex) {
		$args = static::getArgsFromTokens($tokens, $aIndex);
		return static::passRequest($cName, $fName, $args);
		return false;
	}
	
	protected static function passRequest($controller, $function, $args=false, 
			$namespace=false, $suffix=false, $argProtection=false) {
		// Generate the fully qualified class name
		if ($namespace !== false) {
			if ($namespace[0] !== '\\')
				$namespace = '\\' . $namespace;
			if ($namespace[strlen($namespace) - 1] !== '\\')
				$namespace .= '\\';
		}
		$controller = ucfirst($controller) . $suffix;
		$class = $namespace . $controller;
		
		// Include the file if this class isn't loaded
		if (!@class_exists($class) && isset($controllerPaths[$class]))
			\hydrogen\loadPath($controllerPaths[$class]);
			
		// Call it if everything's there
		if (@class_exists($class)) {	
			// Call it, Cap'n.
			$inst = $class::getInstance();
			if ($argProtection === true) {
				static::$oldHandler = set_error_handler(
					"\hydrogen\controller\Dispatcher::missingArgHandler",
					E_WARNING);
			}
			try {
				call_user_func_array(array($inst, $function), $args ?: array());
			}
			catch (NoSuchMethodException $e) {
				return false;
			}
			catch (MissingArgumentException $e) {
				return false;
			}
			return true;
		}
		return false;
	}
	
	protected static function getRequestedURL() {
		$url = 'http';
		if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
			$url .= 's';
		$url .= "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		return $url;
	}
	
	protected static function getArgsFromTokens($tokens, $aIndex) {
		$args = array();
		if (is_array($aIndex) && count($aIndex) > 0) {
			foreach ($aIndex as $i) {
				if (isset($tokens[$i]))
					$args[] = &$tokens[$i];
				else
					$args[] = false;
			}
		}
		return $args;
	}
	
	protected static function getArgsFromAssocArray($assoc, $keyArray) {
		$args = array();
		if (is_array($assoc) && count($assoc) > 0
				&& is_array($keyArray) && count($keyArray) > 0) {
			foreach ($keyArray as $key) {
				if (isset($assoc[$key]))
					$args[] = $assoc[$key];
				else
					$args[] = false;
			}
		}
		return $args;
	}
	
	public static function missingArgHandler($errno, $errstr, $errfile, $errline) {
		$errCheck = "Missing argument";
		if ($errCheck === substr($errstr, 0, strlen($errCheck)))
			throw new MissingArgumentException();
		else {
			$caller = debug_backtrace();
			$caller = $caller[1];
			trigger_error($errstr . ' in <strong>' . $caller['function'] .
				'</strong> called from <strong>' . $caller['file'] . 
				'</strong> on line <strong>' . $caller['line'] .
				"</strong>\n<br />error handler", E_USER_WARNING);
		}
	}
	
	/**
	 * This class should not be instantiated.
	 */
	private function __construct() {}
}

?>