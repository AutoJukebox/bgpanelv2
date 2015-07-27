<?php

/**
 * LICENSE:
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *
 * @package		Bright Game Panel V2
 * @version		0.1
 * @category	Systems Administration
 * @author		warhawk3407 <warhawk3407@gmail.com> @NOSPAM
 * @copyright	Copyleft 2015, Nikita Rousseau
 * @license		GNU General Public License version 3.0 (GPLv3)
 * @link		http://www.bgpanel.net/
 */

// Prevent direct access
if (!defined('LICENSE'))
{
	exit('Access Denied');
}

if ( !class_exists('Flight')) {
	trigger_error('Core -> Flight FW is missing !');
}


/**
 * Flight FW Routing Definitions
 */


// HTTP status codes VIEW
Flight::route('GET|POST|PUT|DELETE /@http:[0-9]{3}', function( $http ) {
	header( Core_Http_Status_Codes::httpHeaderFor( $http ) );
	echo Core_Http_Status_Codes::getMessageForCode( $http );
	die();
});


// LOGOUT METHOD
Flight::route('/logout/', function() {
	$authService = Core_AuthService::getAuthService();

	if ($authService->getSessionValidity() == TRUE) {
		Core_AuthService::logout();
		Flight::redirect('/login/');
	}

	die();
});


// DEFAULT BEHAVIOUR
Flight::route('GET|POST|PUT|DELETE (/@module(/@page(/@element)))', function( $module, $page, $element ) {

	// Var Init

	if (isset($module) && preg_match("#\w#", $module)) {
		$module = strtolower($module);
	} else {
		$module = '';
	}
	if (isset($page) && preg_match("#\w#", $page)) {
		$page = strtolower($page);
	} else {
		$page = '';
	}
	if (isset($element) && preg_match("#\w#", $element)) {
		$element = strtolower($element);
	} else {
		$element = '';
	}

	// User Authentication

	$authService = Core_AuthService::getAuthService();

	// Test if the user is allowed to access the system

	if ($authService->getSessionValidity() == FALSE) {

		// The user is not logged in

		if (!empty($module) && $module != 'login') {

			// Redirect to login form

			$return = '/' . str_replace( BASE_URL, '', REQUEST_URI );
			Flight::redirect( '/login?page=' . $return );
		}

		// Login

		switch (Flight::request()->method)
		{
			case 'GET':
				// Process Task Query Parameter
				$task = Flight::request()->query['task'];

				// Forgot passwd? Page
				if ( !empty($page) && $page == 'password' ) {
					$mod_path = MODS_DIR . '/login/login.password.php';
				}
				// Login Controller
				else if ( !empty($page) && $page == 'process' && !empty($task) ) {
					$mod_path = MODS_DIR . '/login/login.process.php';
				}
				// Login View
				else {
					$mod_path = MODS_DIR . '/login/login.php';
				}
				break;

			case 'POST':
				// Login Controller
				$mod_path = MODS_DIR . '/login/login.process.php';
				break;

			default:
				break;
		}

		bgp_safe_require( $mod_path );
	}
	else {

		// The user is already logged in

		if (empty($module) || $module == 'login')	{

			// Redirect to the Dashboard

			Flight::redirect('/dashboard/');
		}
		else if (!empty($module)) {

			// NIST Level 2 Standard Role Based Access Control Library

			$rbac = new PhpRbac\Rbac();


			$collection = str_replace('//', '/', ucfirst($module) . '/');
			//$resource  = NULL;

			if (!empty($page)) {
				$collection = str_replace('//', '/', ucfirst($module) . '/' . $page . '/');
			}

			//if (!empty($element)) {
			//	$resource = str_replace('//', '/', ucfirst($module) . '/resource/' . $element);
			//}


			// Verify User Authorization On The Requested Ressource
			// Root User Can Bypass

			if ( $rbac->Users->hasRole( 'root', $authService->getSessionInfo('ID') ) || $rbac->check( $collection, $authService->getSessionInfo('ID') ) ) {

				// MAINTENANCE CHECKER
				// Logout the user

				if ( BGP_MAINTENANCE_MODE == 1 && ($rbac->Users->hasRole( 'root', $authService->getSessionInfo('ID') ) === FALSE) ) {
					Core_AuthService::logout();
					Flight::redirect('/503'); // If the maintenance mode is ON, we drop the user.
				}

				switch (Flight::request()->method)
				{
					case 'GET':
						// Process Task Query Parameter
						$task = Flight::request()->query['task'];

						// Page
						if ( !empty($page) ) {
							$mod_path = MODS_DIR . '/' . $module . '/' . $module . '.' . $page . '.php';
						}
						// Controller
						else if ( !empty($page) && $page == 'process' && !empty($task) ) {

							exit(var_dump($task));

							$mod_path = MODS_DIR . '/' . $module . '/' . $module . '.process.php';
						}
						// Module Page
						else {
							$mod_path = MODS_DIR . '/' . $module . '/' . $module . '.php';
						}
						break;

					case 'POST':
					case 'PUT':
					case 'DELETE':
						// Controller

						exit(var_dump( Flight::request() ));
						exit(var_dump( Flight::request()->data->task ));

						$mod_path = MODS_DIR . '/' . $module . '/' . $module . '.process.php';

						break;

					default:
						break;
				}

				bgp_safe_require( $mod_path );
			}
			else {
				Flight::redirect('/403');
			}
		}
	}
});


/**
 * Start the FW
 */


Flight::start();
