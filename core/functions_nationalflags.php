<?php
/**
*
* @package National Flags
* @copyright (c) 2015 Rich McGirr(RMcGirr83)
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

namespace rmcgirr83\nationalflags\core;

class functions_nationalflags
{

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\cache\service */
	protected $cache;

	/** @var \phpbb\db\driver\driver */
	protected $db;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/**
	* The database table the rules are stored in
	*
	* @var string
	*/
	protected $flags_table;

	/**
	* the path to the flags directory
	*
	*@var string
	*/
	protected $flags_path;

	/** @var string phpBB root path */
	protected $phpbb_root_path;


	public function __construct(\phpbb\config\config $config, \phpbb\cache\service $cache, \phpbb\db\driver\driver_interface $db, \phpbb\template\template $template, \phpbb\user $user, $flags_table, $flags_path, $phpbb_root_path)
	{
		$this->config = $config;
		$this->cache = $cache;
		$this->db = $db;
		$this->template = $template;
		$this->user = $user;
		$this->flags_table = $flags_table;
		$this->flags_path = $flags_path;
		$this->root_path = $phpbb_root_path;
	}

	/**
	 * Get user flag
	 *
	 * @param int $row User's flag
	 * @return string flag
	 */

	public function get_user_flag($flag_id = false)
	{
		// check for the cache build
		if (($user_flags = $this->cache->get('_user_flags')) === false)
		{
			$user_flags = $this->query_flags();
		}

		if ($flag_id)
		{
			$flag = '<img src="' . $this->root_path . $this->flags_path . $user_flags[$flag_id]['flag_image'] . '" alt="'. htmlspecialchars($user_flags[$flag_id]['flag_name']) . '" title="'. htmlspecialchars($user_flags[$flag_id]['flag_name']) . '" />';

			return $flag;
		}
	}
	/**
	 * Get query flags
	 *
	 * Build the cache of the flags
	 *
	 * @return null
	 */

	public function query_flags()
	{
		$user_flags = array();

		$sql = 'SELECT flag_id, flag_name, flag_image
			FROM ' . $this->flags_table . '
		ORDER BY flag_id';
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$user_flags[$row['flag_id']] = array(
				'flag_id'		=> $row['flag_id'],
				'flag_name'		=> $row['flag_name'],
				'flag_image'	=> $row['flag_image'],
			);
		}
		$this->db->sql_freeresult($result);

		// cache this data for ever, can only change in ACP
		$this->cache->put('_user_flags', $user_flags);
	}

	/**
	 * Get list_all_flags
	 *
	 * @param int $flag_id
	 * @return string flag_options
	 */

	public function list_all_flags($flag_id)
	{
		$sql = 'SELECT flag_id, flag_name, flag_image
			FROM ' . $this->flags_table . '
		ORDER BY flag_name';
		$result = $this->db->sql_query($sql);

		$flag_options = '<option value="0">' . $this->user->lang['FLAG_EXPLAIN'] . '</option>';
		while ($row = $this->db->sql_fetchrow($result))
		{
			$selected = ($row['flag_id'] == $flag_id) ? ' selected="selected"' : '';
			$flag_options .= '<option value="' . $row['flag_id'] . '" ' . $selected . '>' . $row['flag_name'] . '</option>';
		}
		$this->db->sql_freeresult($result);

		return $flag_options;
	}

	/**
	 * Get top_flags
	 */
	public function top_flags()
	{
		$sql = 'SELECT user_flag, COUNT(user_flag) AS fnum
			FROM ' . USERS_TABLE . '
		WHERE user_flag > 0
		GROUP BY user_flag
		ORDER BY fnum DESC';
		$result = $this->db->sql_query_limit($sql, $this->config['flags_how_many']);

		$count = 0;
		while ($row = $this->db->sql_fetchrow($result))
		{
			++$count;

			$template->assign_block_vars('flag', array(
				'FLAG' 			=> get_user_flag($row['user_flag']),
				'L_FLAG_USERS'	=> $row['fnum'] == 1 ? sprintf($user->lang['FLAG_USER'], $row['fnum']) : sprintf($user->lang['FLAG_USERS'], $row['fnum']),
			));
		}
		$this->db->sql_freeresult($result);

		if($count)
		{
			$this->template->assign_vars(array(
				'S_FLAGS_FOUND'	=> true,
			));
		}
	}
}