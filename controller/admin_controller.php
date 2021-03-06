<?php
/**
*
* National Flags extension for the phpBB Forum Software package.
*
* @copyright (c) 2015 Rich McGirr (RMcGirr83)
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace rmcgirr83\nationalflags\controller;

/**
* Admin controller
*/
class admin_controller
{
	/** @var \phpbb\cache\service */
	protected $cache;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\pagination */
	protected $pagination;

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\log\log */
	protected $log;

	/** @var \phpbb\extension\manager "Extension Manager" */
	protected $ext_manager;

	/** @var \phpbb\path_helper */
	protected $path_helper;

	/** @var string phpBB root path */
	protected $root_path;

	/** @var string phpEx */
	protected $php_ext;

	/**
	 * The database table the flags are stored in
	 *
	 * @var string
	 */
	protected $flags_table;

	/* @var \rmcgirr83\nationalflags\core\nationalflags */
	protected $functions;

	/** @var string Custom form action */
	protected $u_action;

	/**
	* Constructor
	*
	* @param \phpbb\cache\service					$cache				Cache object
	* @param \phpbb\config\config					$config				Config object
	* @param \phpbb\db\driver\driver_interface		$db					Database object
	* @param \phpbb\pagination						$pagination			Pagination object
	* @param \phpbb\controller\helper           	$helper     	    Controller helper object
	* @param \phpbb\request\request					$request			Request object
	* @param \phpbb\template\template				$template			Template object
	* @param \phpbb\user							$user				User object
	* @param \phpbb\log								$log				Log object
	* @param \phpbb\extension\manager				$ext_manager		Extension manager object
	* @param \phpbb\path_helper						$path_helper		Path helper object
	* @param string                             	$root_path      	phpBB root path
	* @param string                             	$php_ext        	phpEx
	* @param string									$flags_table		Name of the table used to store flag data
	* @param \rmcgirr83\nationalflags\core\nationalflags	$functions	Functions for the extension
	* @return \rmcgirr83\nationalflags\controller\admin_controller
	* @access public
	*/
	public function __construct(
			\phpbb\cache\service $cache,
			\phpbb\config\config $config,
			\phpbb\db\driver\driver_interface $db,
			\phpbb\pagination $pagination,
			\phpbb\controller\helper $helper,
			\phpbb\request\request $request,
			\phpbb\template\template $template,
			\phpbb\user $user,
			\phpbb\log\log $log,
			\phpbb\extension\manager $ext_manager,
			\phpbb\path_helper $path_helper,
			$root_path,
			$php_ext,
			$flags_table,
			\rmcgirr83\nationalflags\core\nationalflags $functions)
	{
		$this->cache = $cache;
		$this->config = $config;
		$this->db = $db;
		$this->pagination = $pagination;
		$this->helper = $helper;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->log = $log;
		$this->ext_manager	 = $ext_manager;
		$this->path_helper	 = $path_helper;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
		$this->flags_table = $flags_table;
		$this->functions = $functions;

		$this->ext_path = $this->ext_manager->get_extension_path('rmcgirr83/nationalflags', true);
		$this->ext_path_web = $this->path_helper->update_web_root_path($this->ext_path);
	}

	/**
	 * Display the options a user can configure for this extension
	 *
	 * @return null
	 * @access public
	 */
	public function display_options()
	{
		// Create a form key for preventing CSRF attacks
		add_form_key('nationalflags_settings');

		// Is the form being submitted to us?
		if ($this->request->is_set_post('submit'))
		{
			// Test if the submitted form is valid
			if (!check_form_key('nationalflags_settings'))
			{
				trigger_error($this->user->lang['FORM_INVALID'] . adm_back_link($this->u_action));
			}

			if (!function_exists('validate_data'))
			{
				include($this->root_path . 'includes/functions_user.' . $this->php_ext);
			}

			$check_row = array('flags_num_display' => $this->request->variable('flags_num_display', 0));
			$validate_row = array('flags_num_display' => array('num', false, 1, 1000));
			$error = validate_data($check_row, $validate_row);
			// Replace "error" strings with their real, localised form
			$error = array_map(array($this->user, 'lang'), $error);

			if (!sizeof($error))
			{
				// Set the options the user configured
				$this->set_options();

				// Add option settings change action to the admin log
				$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'FLAG_CONFIG_SAVED');

				// Option settings have been updated and logged
				// Confirm this to the user and provide link back to previous page
				trigger_error($this->user->lang('FLAG_SETTINGS_CHANGED') . adm_back_link($this->u_action));
			}
		}

		// Set output vars for display in the template
		$this->template->assign_vars(array(
			'ERROR'				=> isset($error) ? ((sizeof($error)) ? implode('<br />', $error) : '') : '',
			'FLAGS_VERSION' 	=> $this->config['nationalflags_version'],
			'FLAGS_NUM_DISPLAY'	=> $this->config['flags_num_display'],
			'FLAGS_DISPLAY_INDEX'	=> $this->config['flags_display_index'] ? true : false,
			'ALLOW_FLAGS'		=> $this->config['allow_flags'] ? true : false,
			'FLAGS_REQUIRED'	=> $this->config['flags_required'] ? true : false,
			'FLAGS_DISPLAY_MSG'	=> $this->config['flags_display_msg'] ? true : false,
			'FLAGS_DISPLAY_TO_GUESTS'	=> $this->config['flags_display_to_guests'] ? true : false,

			'S_FLAGS'			=> true,

			'U_ACTION'			=> $this->u_action,
		));
	}

	/**
	 * Set the options a user can configure
	 *
	 * @return null
	 * @access protected
	 */
	protected function set_options()
	{
		$this->config->set('flags_num_display', $this->request->variable('flags_num_display', 0));
		$this->config->set('allow_flags', $this->request->variable('allow_flags', 0));
		$this->config->set('flags_required', $this->request->variable('flags_required', 0));
		$this->config->set('flags_display_msg', $this->request->variable('flags_display_msg', 0));
		$this->config->set('flags_display_index', $this->request->variable('flags_display_index', 0));
		$this->config->set('flags_display_to_guests', $this->request->variable('flags_display_to_guests', 0));
	}

	/**
	 * Display the flags
	 *
	 * @return null
	 * @access public
	 */
	public function display_flags()
	{
		$start = $this->request->variable('start', 0);
		$pagination_url = $this->u_action;

		$this->user->add_lang_ext('rmcgirr83/nationalflags', 'common');

		$sql = 'SELECT f.*, COUNT(u.user_flag) as user_count
			FROM ' . $this->flags_table . ' f
				LEFT JOIN ' . USERS_TABLE . " u on f.flag_id = u.user_flag
			GROUP BY f.flag_id
			ORDER BY f.flag_name ASC";
		$result = $this->db->sql_query_limit($sql, $this->config['topics_per_page'], $start);

		// for counting of all the flags
		// used for pagination
		$result2 = $this->db->sql_query($sql);
		$row2 = $this->db->sql_fetchrowset($result2);
		$total_count = (int) sizeof($row2);
		$this->db->sql_freeresult($result2);
		unset($row2);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$user_count = $this->user->lang('FLAG_USERS', (int) $row['user_count']);

			$this->template->assign_block_vars('flags', array(
				'FLAG_NAME'		=> $row['flag_name'],
				'FLAG_IMG'		=> $this->ext_path_web . 'flags/' . strtolower($row['flag_image']),
				'FLAG_ID'		=> $row['flag_id'],
				'USER_COUNT'	=> $user_count,
				'U_FLAG'		=> $this->helper->route('rmcgirr83_nationalflags_getflags', array('flag_id' => $row['flag_id'])),
				'U_EDIT'		=> $this->u_action . "&amp;flag_id={$row['flag_id']}&amp;action=edit",
				'U_DELETE'		=> $this->u_action . "&amp;flag_id={$row['flag_id']}&amp;action=delete",)
			);
		}
		$this->db->sql_freeresult($result);

		$start = $this->pagination->validate_start($start, $this->config['topics_per_page'], $total_count);
		$this->pagination->generate_template_pagination($pagination_url, 'pagination', 'start', $total_count, $this->config['topics_per_page'], $start);

		$this->template->assign_vars(array(
			'TOTAL_FLAGS'	=> $total_count,
			'S_FLAGS'	=> true,
		));
	}

	/**
	 * Add a flag
	 *
	 * @return null
	 * @access public
	 */
	public function add_flag()
	{
		// Add form key
		add_form_key('add_flag');

		$errors = array();

		$flag_row = array(
			'flag_name'			=> ucfirst(utf8_normalize_nfc($this->request->variable('flag_name', '', true))),
			'flag_image'		=> $this->request->variable('flag_image', ''),
		);

		if ($this->request->is_set_post('submit'))
		{
			$errors = $this->check_flag($flag_row['flag_image'], $flag_row['flag_name'], $errors, 'add_flag');

			if (!sizeof($errors))
			{
				$sql = 'INSERT INTO ' . $this->flags_table . ' ' . $this->db->sql_build_array('INSERT', $flag_row);
				$this->db->sql_query($sql);

				$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_FLAG_ADD', time(), array($flag_row['flag_name']));

				$this->cache->destroy('_user_flags');
				// cache this data for ever, can only change in ACP
				$this->functions->cache_flags();

				trigger_error($this->user->lang['MSG_FLAG_ADDED'] . adm_back_link($this->u_action));
			}
		}

		$this->template->assign_vars(array(
			'U_ACTION'		=> $this->u_action . '&amp;action=add',
			'U_BACK'		=> $this->u_action,
			'FLAG_NAME'		=> $flag_row['flag_name'],
			'FLAG_IMAGE'	=> $flag_row['flag_image'],
			'ERROR_MSG'		=> (sizeof($errors)) ? implode('<br />', $errors) : '',

			'S_ADD_FLAG'	=> true,
			'S_ERROR'		=> (sizeof($errors)) ? true : false,
		));
	}

	/**
	 * Edit a flag
	 *
	 * @param int $flag_id The flag identifier to edit
	 * @return null
	 * @access public
	 */
	public function edit_flag($flag_id)
	{
		// Add form key
		add_form_key('edit_flag');

		$errors = array();

		$flag_row = array(
			'flag_name'			=> utf8_normalize_nfc($this->request->variable('flag_name', '', true)),
			'flag_image'		=> $this->request->variable('flag_image', ''),
		);

		if ($this->request->is_set_post('submit'))
		{
			$errors = $this->check_flag($flag_row['flag_image'], $flag_row['flag_name'], $errors, 'edit_flag');

			if (!sizeof($errors))
			{
				$sql = 'UPDATE ' . $this->flags_table . '
					SET ' . $this->db->sql_build_array('UPDATE', $flag_row) . '
					WHERE flag_id = ' . (int) $flag_id;
				$this->db->sql_query($sql);

				$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_FLAG_EDIT', time(), array($flag_row['flag_name']));

				$this->cache->destroy('_user_flags');
				$this->functions->cache_flags();

				trigger_error($this->user->lang['MSG_FLAG_EDITED'] . adm_back_link($this->u_action));
			}
		}

		$sql = 'SELECT flag_id, flag_name, flag_image
			FROM ' . $this->flags_table . '
			WHERE flag_id =' . (int) $flag_id;
		$result = $this->db->sql_query($sql);
		$flag_row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$flag_row)
		{
			trigger_error($this->user->lang['FLAG_ERROR_NOT_EXIST'] . adm_back_link($this->u_action . '&amp;mode=manage'), E_USER_WARNING);
		}

		$this->template->assign_vars(array(
			'L_TITLE'		=> $this->user->lang['FLAG_EDIT'],
			'U_ACTION'		=> $this->u_action . "&amp;flag_id=$flag_id&amp;action=edit",
			'U_BACK'		=> $this->u_action . '&amp;mode=manage',
			'ERROR_MSG'		=> (sizeof($errors)) ? implode('<br />', $errors) : '',

			'FLAG_NAME'		=> $flag_row['flag_name'],
			'FLAG_IMAGE'	=> $flag_row['flag_image'],
			'FLAG_ID'		=> $flag_row['flag_id'],

			'S_ADD_FLAG'	=> true,
			'S_ERROR'		=> (sizeof($errors)) ? true : false,
			)
		);
	}

	/**
	 * Delete a flag
	 *
	 * @param int $flag_id The flag identifier to delete
	 * @return null
	 * @access public
	 */
	public function delete_flag($flag_id)
	{
		if (confirm_box(true))
		{
			// Grab the flag name for the log...
			$sql = 'SELECT flag_name, flag_image
				FROM ' . $this->flags_table . '
				WHERE flag_id = ' .(int) $flag_id;
			$result = $this->db->sql_query($sql);
			$flag_row = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			// Delete the flag...
			$sql = 'DELETE FROM ' . $this->flags_table . '
				WHERE flag_id = ' . (int) $flag_id;
			$this->db->sql_query($sql);

			// Reset the flag for users
			$sql = 'UPDATE ' . USERS_TABLE . '
				SET user_flag = 0
				WHERE user_flag = ' . (int) $flag_id;
			$this->db->sql_query($sql);

			$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_FLAGS_DELETED', time(), array($flag_row['flag_name']));

			$this->cache->destroy('_user_flags');
			$this->functions->cache_flags();

			trigger_error($this->user->lang['MSG_FLAGS_DELETED'] . adm_back_link($this->u_action . "&amp;mode=manage"));
		}
		else
		{
			// display a count of users who have this flag
			$sql = 'SELECT COUNT(user_flag) AS flag_count
				FROM ' . USERS_TABLE . '
				WHERE user_flag = ' . (int) $flag_id;
			$result = $this->db->sql_query($sql);
			$row = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			$message = $this->user->lang['MSG_CONFIRM'];
			if (!empty($row['flag_count']))
			{
				$message .= $row['flag_count'] <> 1 ? $this->user->lang('MSG_FLAGS_CONFIRM_DELETE', $row['flag_count']) : $this->user->lang('MSG_FLAG_CONFIRM_DELETE', $row['flag_count']);
			}
			confirm_box(false, $message, build_hidden_fields(array(
				'id'		=> (int) $flag_id,
				'mode'		=> 'manage',
				'action'	=> 'delete'))
			);
			// Use a redirect to take the user back to the previous page
			// if the user chose not delete the flag from the confirmation page.
			redirect("{$this->u_action}");
		}
	}

	/* check flag
	*
	* a function to run flag validation on
	* @param string	$form_key	The forum key add_flag/edit_flag
	* @param string	$flag_image	The flag image
	* @param string	$flag_name	The flag name
	* @param array	$errors		The possible generated errors
	* @return array
	* @access private
	*/
	private function check_flag($flag_image, $flag_name, $errors, $form_key = '')
	{
		if (!check_form_key($form_key))
		{
			$errors[] = $this->user->lang['FORM_INVALID'];
		}

		if (empty($flag_name))
		{
			$errors[] = $this->user->lang['FLAG_ERROR_NO_FLAG_NAME'];
		}

		if (empty($flag_image))
		{
			$errors[] = $this->user->lang['FLAG_ERROR_NO_FLAG_IMG'];
		}

		if ($form_key == 'add_flag')
		{
			//we don't want two flags with the same name...right?
			$sql = 'SELECT flag_name
				FROM ' . $this->flags_table . "
				WHERE upper(flag_name) = '" . $this->db->sql_escape(strtoupper($flag_name)) . "'";
			$result = $this->db->sql_query($sql);

			if ($this->db->sql_fetchrow($result))
			{
				$errors[] = $this->user->lang['FLAG_NAME_EXISTS'];
			}
			$this->db->sql_freeresult($result);
		}

		return $errors;
	}

	/**
	 * Set page url
	 *
	 * @param string $u_action Custom form action
	 * @return null
	 * @access public
	 */
	public function set_page_url($u_action)
	{
		$this->u_action = $u_action;
	}
}
