<?php
/**
*
* @package National Flags
* @copyright (c) 2015 Rich McGirr(RMcGirr83)
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

namespace rmcgirr83\nationalflags\core;

use Symfony\Component\HttpFoundation\Response;

class nationalflags
{

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \phpbb\cache\service */
	protected $cache;

	/** @var \phpbb\db\driver\driver */
	protected $db;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/**
	 * The database table the flags are stored in
	 *
	 * @var string
	 */
	protected $flags_table;

	/** @var \phpbb\extension\manager "Extension Manager" */
	protected $ext_manager;

	/** @var \phpbb\path_helper */
	protected $path_helper;

	/**
	 * Constructor
	 *
	 * @param \phpbb\config\config			$config				Config object
	 * @param \phpbb\controller\helper		$helper				Controller helper object
	 * @param \phpbb\cache\service			$cache				Cache object
	 * @param \phpbb\db\driver\driver		$db					Database object
	 * @param \phpbb\template\template		$template			Template object
	 * @param \phpbb\user					$user				User object
	 * @param string						$flags_table		Name of the table used to store flag data
	 * @param \phpbb\extension\manager		$ext_manager		Extension manager object
	 * @param \phpbb\path_helper			$path_helper		Path helper object
	 */
	public function __construct(
			\phpbb\config\config $config,
			\phpbb\controller\helper $helper,
			\phpbb\cache\service $cache,
			\phpbb\db\driver\driver_interface $db,
			\phpbb\template\template $template,
			\phpbb\user $user,
			$flags_table,
			\phpbb\extension\manager $ext_manager,
			\phpbb\path_helper $path_helper)
	{
		$this->config = $config;
		$this->helper = $helper;
		$this->cache = $cache;
		$this->db = $db;
		$this->template = $template;
		$this->user = $user;
		$this->flags_table = $flags_table;
		$this->ext_manager	 = $ext_manager;
		$this->path_helper	 = $path_helper;

		$this->ext_path = $this->ext_manager->get_extension_path('rmcgirr83/nationalflags', true);
		$this->ext_path_web = $this->path_helper->update_web_root_path($this->ext_path);
	}

	/**
	 * Get user flag
	 *
	 * @return string flag
	 */

	public function get_user_flag($flag_id = false)
	{
		$flags = $this->get_flag_cache();

		if ($flag_id)
		{
			$flag = '<img class="flag_image" src="' . $this->ext_path_web . 'flags/' . $flags[$flag_id]['flag_image'] . '" alt="' . $flags[$flag_id]['flag_name'] . '" title="' . $flags[$flag_id]['flag_name'] . '" />';

			return $flag;
		}
		return false;
	}
	/**
	 * cache_flags
	 *
	 * Build the cache of the flags
	 *
	 * @return null
	 */

	public function cache_flags()
	{
		if (($this->cache->get('_user_flags')) === false)
		{
			$sql = 'SELECT *
				FROM ' . $this->flags_table . '
			ORDER BY flag_id';
			$result = $this->db->sql_query($sql);

			$user_flags = array();
			while ($row = $this->db->sql_fetchrow($result))
			{
				$user_flags[$row['flag_id']] = array(
					'flag_id'		=> $row['flag_id'],
					'flag_name'		=> $row['flag_name'],
					'flag_image'	=> $row['flag_image'],
					'flag_default'	=> $row['flag_default'],
				);
			}
			$this->db->sql_freeresult($result);

			// cache this data for ever, can only change in ACP
			$this->cache->put('_user_flags', $user_flags);
		}
	}

	/**
	 * Get list_flags
	 *
	 * @param int $flag_id
	 * @return string flag_options
	 */

	public function list_flags($flag_id)
	{
		$sql = 'SELECT *
			FROM ' . $this->flags_table . '
		ORDER BY flag_name';
		$result = $this->db->sql_query($sql);

		$flag_options = '<option value="0">' . $this->user->lang['FLAG_EXPLAIN'] . '</option>';
		while ($row = $this->db->sql_fetchrow($result))
		{
			$selected = ($row['flag_id'] == $flag_id) ? ' selected="selected"' : ($row['flag_default'] ? ' selected="selected"' : '');
			$flag_options .= '<option value="' . $row['flag_id'] . '" ' . $selected . '>' . $row['flag_name'] . '</option>';
		}
		$this->db->sql_freeresult($result);

		return $flag_options;
	}

	/**
	 * Get top_flags
	 * displayed on the index page
	 */
	public function top_flags()
	{

		// If setting in ACP is set to not allow guests and bots to view the flags
		if (!$this->config['flags_display_to_guests'] && ($this->user->data['is_bot'] || $this->user->data['user_id'] == ANONYMOUS))
		{
			return;
		}
		// grab all the flags
		$sql_array = array(
			'SELECT'	=> 'user_flag, COUNT(user_flag) AS fnum',
			'FROM'		=> array(USERS_TABLE => 'u'),
			'WHERE'		=> $this->db->sql_in_set('user_type', array(USER_NORMAL, USER_FOUNDER)) . ' AND user_flag > 0',
			'GROUP_BY'	=> 'user_flag',
			'ORDER_BY'	=> 'fnum DESC',
		);

		// we limit the number of flags to display to the number set in the ACP settings
		$result = $this->db->sql_query_limit($this->db->sql_build_query('SELECT', $sql_array), $this->config['flags_num_display']);

		$count = 0;
		$flags = $this->get_flag_cache();

		while ($row = $this->db->sql_fetchrow($result))
		{
			++$count;
			$this->template->assign_block_vars('flag', array(
				'FLAG' 			=> $this->get_user_flag($row['user_flag']),
				'FLAG_USERS'	=> $this->user->lang('FLAG_USERS', (int) $row['fnum']),
				'U_FLAG'		=> $this->helper->route('rmcgirr83_nationalflags_getflags', array('flag_id' => $flags[$row['user_flag']]['flag_id'])),
			));
		}
		$this->db->sql_freeresult($result);

		if ($count)
		{
			$this->template->assign_vars(array(
				'U_FLAGS'		=> $this->helper->route('rmcgirr83_nationalflags_display'),
				'S_FLAGS'	=> true,
			));
		}
	}

	/**
	 * Display flag on change in ucp
	 * Ajax function
	 * @param $flag_id
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function getFlag($flag_id)
	{
		if (empty($flag_id))
		{
			if ($this->config['flags_required'])
			{
				return new Response($this->user->lang['MUST_CHOOSE_FLAG']);
			}
			else
			{
				return new Response($this->user->lang['NO_SUCH_FLAG']);
			}
		}

		$flags = $this->get_flag_cache();

		$flag_img = $this->ext_path . 'flags/' . $flags[$flag_id]['flag_image'];
		$flag_img = str_replace('./', generate_board_url() . '/', $flag_img); //fix paths

		$flag_name = $flags[$flag_id]['flag_name'];

		$return = '<img class="flag_image" src="' . $flag_img . '" alt="' . $flag_name . '" title="' . $flag_name . '" />';

		return new Response($return);
	}

	/**
	 * Get the cache of the flags
	 *
	 * @return string flag_cache
	 * @access public
	 */
	public function get_flag_cache()
	{
		return $this->cache->get('_user_flags');
	}
}
