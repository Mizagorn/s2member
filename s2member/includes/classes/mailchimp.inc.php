<?php
/**
 * MailChimp
 *
 * Copyright: © 2009-2011
 * {@link http://www.websharks-inc.com/ WebSharks, Inc.}
 * (coded in the USA)
 *
 * Released under the terms of the GNU General Public License.
 * You should have received a copy of the GNU General Public License,
 * along with this software. In the main directory, see: /licensing/
 * If not, see: {@link http://www.gnu.org/licenses/}.
 *
 * @since 141004
 * @package s2Member\List_Servers
 */
if(realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME']))
	exit('Do not access this file directly.');

if(!class_exists('c_ws_plugin__s2member_mailchimp'))
{
	/**
	 * MailChimp
	 *
	 * @since 141004
	 * @package s2Member\List_Servers
	 */
	class c_ws_plugin__s2member_mailchimp extends c_ws_plugin__s2member_list_server_base
	{
		/**
		 * Subscribe.
		 *
		 * @since 141004
		 * @package s2Member\List_Servers
		 *
		 * @param array $args Input arguments.
		 *
		 * @return bool True if successful.
		 */
		public static function subscribe($args)
		{
			if(!($args = self::validate_args($args)))
				return FALSE; // Invalid args.

			if(!$GLOBALS['WS_PLUGIN__']['s2member']['o']['mailchimp_api_key'])
				return FALSE; // Not possible.

			if(empty($GLOBALS['WS_PLUGIN__']['s2member']['o']['level'.$args->level.'_mailchimp_list_ids']))
				return FALSE; // No list configured at this level.

			if(!class_exists('NC_MCAPI')) // Include the MailChimp API Class here.
				include_once dirname(dirname(__FILE__)).'/externals/mailchimp/nc-mcapi.inc.php';
			$mcapi = new NC_MCAPI($GLOBALS['WS_PLUGIN__']['s2member']['o']['mailchimp_api_key'], TRUE);

			$args->fname       = !$args->fname ? ucwords(strstr($args->email, '@', TRUE)) : $args->fname;
			$args->lname       = !$args->lname ? '-' : $args->lname; // Default last name to `-` because MC requires this.
			$args->name        = $args->fname || $args->lname ? trim($args->fname.' '.$args->lname) : ucwords(strstr($args->email, '@', TRUE));
			$mc_level_list_ids = $GLOBALS['WS_PLUGIN__']['s2member']['o']['level'.$args->level.'_mailchimp_list_ids'];

			foreach(preg_split('/['."\r\n\t".';,]+/', $mc_level_list_ids) as $_mc_list)
			{
				$_mc = array(
					'args'           => $args,
					'function'       => __FUNCTION__,
					'list'           => trim($_mc_list),
					'list_id'        => trim($_mc_list),
					'api_method'     => 'listSubscribe',
					'api_properties' => $mcapi,
				);
				if(!$_mc['list']) continue; // List missing.

				if(strpos($_mc['list'], '::') !== FALSE) // Contains Interest Groups?
				{
					list($_mc['list_id'], $_mc['interest_groups_title'], $_mc['interest_groups']) = preg_split('/\:\:/', $_mc['list'], 3);

					if(($_mc['interest_groups_title'] = trim($_mc['interest_groups_title'])))
						if(($_mc['interest_groups'] = $_mc['interest_groups'] ? preg_split('/\|/', trim($_mc['interest_groups']), NULL, PREG_SPLIT_NO_EMPTY) : array()))
							$_mc['interest_groups'] = array('GROUPINGS' => array(array('name' => $_mc['interest_groups_title'], 'groups' => implode(',', $_mc['interest_groups']))));

					if(!$_mc['list_id']) continue; // List ID is missing now; after parsing interest groups.
				}
				else $_mc['list_id'] = $_mc['list']; // Else, it's just a List ID.

				$_mc['merge_array'] = array('MERGE1' => $args->fname, 'MERGE2' => $args->lname, 'OPTIN_IP' => $args->ip, 'OPTIN_TIME' => date('Y-m-d H:i:s'));
				$_mc['merge_array'] = !empty($_mc['interest_groups']) ? array_merge($_mc['merge_array'], $_mc['interest_groups']) : $_mc['merge_array'];
				$_mc['merge_array'] = apply_filters('ws_plugin__s2member_mailchimp_array', $_mc['merge_array'], get_defined_vars()); // Deprecated!
				// Filter: `ws_plugin__s2member_mailchimp_array` deprecated in v110523. Please use Filter: `ws_plugin__s2member_mailchimp_merge_array`.

				if($_mc['api_response'] = $mcapi->{$_mc['api_method']}($_mc['list_id'], $args->email, // See: `http://apidocs.mailchimp.com/` for full details.
					($_mc['api_merge_array'] = apply_filters('ws_plugin__s2member_mailchimp_merge_array', $_mc['merge_array'], get_defined_vars())), // Configured merge array above.
					($_mc['api_email_type'] = apply_filters('ws_plugin__s2member_mailchimp_email_type', 'html', get_defined_vars())), // Type of email to receive (i.e. html,text,mobile).
					($_mc['api_double_optin'] = apply_filters('ws_plugin__s2member_mailchimp_double_optin', $args->double_opt_in, get_defined_vars())), // Abuse of this may cause account suspension.
					($_mc['api_update_existing'] = apply_filters('ws_plugin__s2member_mailchimp_update_existing', TRUE, get_defined_vars())), // Existing subscribers should be updated with this?
					($_mc['api_replace_interests'] = apply_filters('ws_plugin__s2member_mailchimp_replace_interests', TRUE, get_defined_vars())), // Replace interest groups? (only if provided).
					($_mc['api_send_welcome'] = apply_filters('ws_plugin__s2member_mailchimp_send_welcome', FALSE, get_defined_vars())))
				) $_mc['api_success'] = $success = TRUE; // Flag this as `TRUE`; assists with return value below.

				c_ws_plugin__s2member_utils_logs::log_entry('mailchimp-api', $_mc);
			}
			unset($_mc_list, $_mc); // Housekeeping.

			return !empty($success); // If one suceeds.
		}

		/**
		 * Unsubscribe.
		 *
		 * @since 141004
		 * @package s2Member\List_Servers
		 *
		 * @param array $args Input arguments.
		 *
		 * @return bool True if successful.
		 */
		public static function unsubscribe($args)
		{
			if(!($args = self::validate_args($args)))
				return FALSE; // Invalid args.
		}

		/**
		 * Transition.
		 *
		 * @since 141004
		 * @package s2Member\List_Servers
		 *
		 * @param array $old_args Input arguments.
		 * @param array $new_args Input arguments.
		 *
		 * @return bool True if successful.
		 */
		public static function transition($old_args, $new_args)
		{
			if(!($old_args = self::validate_args($old_args)))
				return FALSE; // Invalid args.

			if(!($new_args = self::validate_args($new_args)))
				return FALSE; // Invalid args.
		}
	}
}