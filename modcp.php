<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'modcp.php');

$templatelist = "modcp_reports,modcp_reports_report,modcp_reports_multipage,modcp_reports_allreport,modcp_reports_allreports,modcp_modlogs_multipage,modcp_announcements_delete,modcp_announcements_edit";
$templatelist .= ",modcp_reports_allnoreports,modcp_reports_noreports,modcp_banning,modcp_banning_ban,modcp_announcements_announcement_global,modcp_no_announcements_forum,modcp_modqueue_threads_thread";
$templatelist .= ",modcp_banning_multipage,modcp_banning_nobanned,modcp_modqueue_threads_empty,modcp_modqueue_masscontrols,modcp_modqueue_threads,modcp_modqueue_posts_post,modcp_modqueue_posts_empty";
$templatelist .= ",modcp_nav,modcp_modlogs_noresults,modcp_modlogs_nologs,modcp,modcp_modqueue_posts,modcp_modqueue_attachments_attachment,modcp_modqueue_attachments_empty,modcp_modqueue_attachments,modcp_editprofile_suspensions_info";
$templatelist .= ",modcp_no_announcements_global,modcp_announcements_global,modcp_announcements_forum,modcp_announcements,modcp_editprofile_select_option,modcp_editprofile_select,modcp_finduser_noresults";
$templatelist .= ",codebuttons,smilieinsert,modcp_announcements_new,modcp_modqueue_empty,forumjump_bit,forumjump_special,modcp_warninglogs_warning_revoked,modcp_warninglogs_warning,modcp_ipsearch_result";
$templatelist .= ",modcp_modlogs,modcp_finduser_user,modcp_finduser,usercp_profile_customfield,usercp_profile_profilefields,modcp_ipsearch_noresults,modcp_ipsearch_results,modcp_ipsearch_misc_info";
$templatelist .= ",modcp_editprofile,modcp_ipsearch,modcp_banuser_addusername,modcp_banuser,modcp_warninglogs_nologs,modcp_banuser_editusername,modcp_lastattachment,modcp_lastpost,modcp_lastthread";
$templatelist .= ",modcp_warninglogs,modcp_modlogs_result,modcp_editprofile_signature,forumjump_advanced,smilieinsert_getmore,smilieinsert_smilie,smilieinsert_smilie_empty,modcp_announcements_forum_nomod,modcp_announcements_announcement,multipage_prevpage";
$templatelist .= ",multipage_start,multipage_page_current,multipage_page,multipage_end,multipage_nextpage,multipage,modcp_editprofile_away";
$templatelist .= ",postbit_online,postbit_avatar,postbit_find,postbit_pm,postbit_email,postbit_author_user,announcement_edit,announcement_quickdelete,postbit,previewpost";

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/functions_upload.php";
require_once MYBB_ROOT."inc/functions_modcp.php";
require_once MYBB_ROOT."inc/class_parser.php";

$parser = new postParser;

// Set up the array of ban times.
$bantimes = fetch_ban_times();

// Load global language phrases
$lang->load("modcp");
$lang->load("announcements");

if($mybb->user['uid'] == 0 || $mybb->usergroup['canmodcp'] != 1)
{
	error_no_permission();
}

$errors = '';
// SQL for fetching items only related to forums this user moderates
$moderated_forums = array();
if($mybb->usergroup['issupermod'] != 1)
{
	$query = $db->simple_select("moderators", "*", "(id='{$mybb->user['uid']}' AND isgroup = '0') OR (id='{$mybb->user['usergroup']}' AND isgroup = '1')");

	$flist = null;
	while($forum = $db->fetch_array($query))
	{
		$flist .= ",'{$forum['fid']}'";

		$children = get_child_list($forum['fid']);
		if(!empty($children))
		{
			$flist .= ",'".implode("','", $children)."'";
		}
		$moderated_forums[] = $forum['fid'];
	}
	if($flist)
	{
		$tflist = " AND t.fid IN (0{$flist})";
		$flist = " AND fid IN (0{$flist})";
	}
}
else
{
	$flist = $tflist = '';
}

// Retrieve a list of unviewable forums
$unviewableforums = get_unviewable_forums();

if($unviewableforums && !is_super_admin($mybb->user['uid']))
{
	$flist .= " AND fid NOT IN ({$unviewableforums})";
	$tflist .= " AND t.fid NOT IN ({$unviewableforums})";

	$unviewableforums = str_replace("'", '', $unviewableforums);
	$unviewableforums = explode(',', $unviewableforums);
}
else
{
	$unviewableforums = array();
}

if(!isset($collapsedimg['modcpforums']))
{
	$collapsedimg['modcpforums'] = '';
}

if(!isset($collapsed['modcpforums_e']))
{
	$collapsed['modcpforums_e'] = '';
}

if(!isset($collapsedimg['modcpusers']))
{
	$collapsedimg['modcpusers'] = '';
}

if(!isset($collapsed['modcpusers_e']))
{
	$collapsed['modcpusers_e'] = '';
}

// Fetch the Mod CP menu
eval("\$modcp_nav = \"".$templates->get("modcp_nav")."\";");

$plugins->run_hooks("modcp_start");

// Make navigation
add_breadcrumb($lang->nav_modcp, "modcp.php");

$mybb->input['action'] = $mybb->get_input('action');
if($mybb->input['action'] == "do_reports")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$mybb->input['reports'] = $mybb->get_input('reports', 2);
	if(empty($mybb->input['reports']))
	{
		error($lang->error_noselected_reports);
	}

	$sql = '1=1';
	if(empty($mybb->input['allbox']))
	{
		$mybb->input['reports'] = array_map("intval", $mybb->input['reports']);
		$rids = implode("','", $mybb->input['reports']);

		$sql = "rid IN ('0','{$rids}')";
	}

	$plugins->run_hooks("modcp_do_reports");

	$db->update_query("reportedposts", array('reportstatus' => 1), "{$sql}{$flist}");
	$cache->update_reportedposts();

	$page = $mybb->get_input('page', 1);

	redirect("modcp.php?action=reports&page={$page}", $lang->redirect_reportsmarked);
}

if($mybb->input['action'] == "reports")
{
	$lang->load('report');
	add_breadcrumb($lang->mcp_nav_report_center, "modcp.php?action=reports");

	$perpage = $mybb->settings['threadsperpage'];
	if(!$perpage)
	{
		$perpage = 20;
	}

	// Multipage
	$where = '';
	if($mybb->usergroup['cancp'] || $mybb->usergroup['issupermod'])
	{
		$query = $db->simple_select("reportedposts", "COUNT(rid) AS count", "reportstatus ='0'");
		$report_count = $db->fetch_field($query, "count");
	}
	else
	{
		$query = $db->simple_select('reportedposts', 'fid', "reportstatus='0'");

		$report_count = 0;
		while($fid = $db->fetch_field($query, 'fid'))
		{
			if(is_moderator($fid))
			{
				++$report_count;
			}
		}
		$where = str_replace('t.fid', 'r.fid', $tflist);
		unset($fid);
	}

	$page = $mybb->get_input('page', 1);

	$postcount = (int)$report_count;
	$pages = $postcount / $perpage;
	$pages = ceil($pages);

	if($page > $pages || $page <= 0)
	{
		$page = 1;
	}

	if($page && $page > 0)
	{
		$start = ($page-1) * $perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}

	$multipage = $reportspages = '';
	if($postcount > $perpage)
	{
		$multipage = multipage($postcount, $perpage, $page, "modcp.php?action=reports");
		eval("\$reportspages = \"".$templates->get("modcp_reports_multipage")."\";");
	}

	$plugins->run_hooks("modcp_reports_start");

	// Reports
	$reports = '';
	$query = $db->query("
		SELECT r.*, u.username
		FROM ".TABLE_PREFIX."reportedposts r
		LEFT JOIN ".TABLE_PREFIX."users u ON (r.uid = u.uid)
		WHERE r.reportstatus = '0'{$where}
		ORDER BY r.reports DESC
		LIMIT {$start}, {$perpage}
	");

	if(!$db->num_rows($query))
	{
		// No unread reports
		eval("\$reports = \"".$templates->get("modcp_reports_noreports")."\";");
	}
	else
	{
		$reportedposts = $cache->read("reportedposts");
		$reportcache = $usercache = $postcache = array();

		while($report = $db->fetch_array($query))
		{
			if($report['type'] == 'profile' || $report['type'] == 'reputation')
			{
				// Profile UID is in PID
				if(!isset($usercache[$report['pid']]))
				{
					$usercache[$report['pid']] = $report['pid'];
				}

				// Reputation comment? The offender is the TID
				if($report['type'] == 'reputation')
				{
					if(!isset($usercache[$report['tid']]))
					{
						$usercache[$report['tid']] = $report['tid'];
					}
					if(!isset($usercache[$report['fid']]))
					{
						// The user who was offended
						$usercache[$report['fid']] = $report['fid'];
					}
				}
			}
			else if(!$report['type'] || $report['type'] == 'post')
			{
				// This (should) be a post
				$postcache[$report['pid']] = $report['pid'];
			}

			// Lastpost info - is it missing (pre-1.8)?
			$lastposter = $report['uid'];
			if(!$report['lastreport'])
			{
				// Last reporter is our first reporter
				$report['lastreport'] = $report['dateline'];
			}

			if($report['reporters'])
			{
				$reporters = unserialize($report['reporters']);

				if(is_array($reporters))
				{
					$lastposter = end($reporters);
				}
			}

			if(!isset($usercache[$lastposter]))
			{
				$usercache[$lastposter] = $lastposter;
			}

			$report['lastreporter'] = $lastposter;
			$reportcache[] = $report;
		}

		// Report Center gets messy
		// Find information about our users (because we don't log it when they file a report)
		if(!empty($usercache))
		{
			$sql = implode(',', array_keys($usercache));
			$query = $db->simple_select("users", "uid, username", "uid IN ({$sql})");

			while($user = $db->fetch_array($query))
			{
				$usercache[$user['uid']] = $user;
			}
		}

		// Messy * 2
		// Find out post information for our reported posts
		if(!empty($postcache))
		{
			$sql = implode(',', array_keys($postcache));
			$query = $db->query("
				SELECT p.pid, p.uid, p.username, p.tid, t.subject
				FROM ".TABLE_PREFIX."posts p
				LEFT JOIN ".TABLE_PREFIX."threads t ON (p.tid = t.tid)
				WHERE p.pid IN ({$sql})
			");

			while($post = $db->fetch_array($query))
			{
				$postcache[$post['pid']] = $post;
			}
		}

		// Now that we have all of the information needed, display the reports
		foreach($reportcache as $report)
		{
			$trow = alt_trow();

			if(!$report['type'])
			{
				// Assume a post
				$report['type'] = 'post';
			}

			// Report Information
			$report_data = array();
			$string = "report_info_{$report['type']}";

			switch($report['type'])
			{
				case 'post':
					$post = get_post_link($report['pid'])."#pid{$report['pid']}";
					$user = build_profile_link($postcache[$report['pid']]['username'], $postcache[$report['pid']]['uid']);
					$report_data['content'] = $lang->sprintf($lang->$string, $post, $user);

					$thread_link = get_thread_link($postcache[$report['pid']]['tid']);
					$thread_subject = htmlspecialchars_uni($postcache[$report['pid']]['subject']);
					$report_data['content'] .= $lang->sprintf($lang->report_info_post_thread, $thread_link, $thread_subject);

					break;
				case 'profile':
					$user = build_profile_link($usercache[$report['pid']]['username'], $usercache[$report['pid']]['uid']);
					$report_data['content'] = $lang->sprintf($lang->report_info_profile, $user);
					break;
				case 'reputation':
					$reputation_link = "reputation.php?uid={$usercache[$report['tid']]['uid']}#rid{$report['pid']}";
					$bad_user = build_profile_link($usercache[$report['tid']]['username'], $usercache[$report['tid']]['uid']);
					$report_data['content'] = $lang->sprintf($lang->report_info_reputation, $reputation_link, $bad_user);

					$good_user = build_profile_link($usercache[$report['fid']]['username'], $usercache[$report['fid']]['uid']);
					$report_data['content'] .= $lang->sprintf($lang->report_info_rep_profile, $good_user);
					break;
			}

			// Report reason and comment
			$report_data['comment'] = $lang->na;
			$report_string = "report_reason_{$report['reason']}";

			if(isset($lang->$report_string))
			{
				$report_data['comment'] = $lang->$report_string;
			}
			else if(!empty($report['reason']))
			{
				$report_data['comment'] = htmlspecialchars_uni($report['reason']);
			}

			$report_reports = 1;
			if($report['reports'])
			{
				$report_data['reports'] = my_number_format($report['reports']);
			}

			if($report['lastreporter'])
			{
				$lastreport_date = my_date('relative', $report['lastreport']);
				$lastreport_user = build_profile_link($usercache[$report['lastreporter']]['username'], $report['lastreporter']);

				$report_data['lastreporter'] = $lang->sprintf($lang->report_info_lastreporter, $lastreport_date, $lastreport_user);
			}

			$plugins->run_hooks("modcp_reports_report");
			eval("\$reports .= \"".$templates->get("modcp_reports_report")."\";");
		}
	}

	$plugins->run_hooks("modcp_reports_end");

	eval("\$reportedposts = \"".$templates->get("modcp_reports")."\";");
	output_page($reportedposts);
}

if($mybb->input['action'] == "allreports")
{
	$lang->load('report');

	add_breadcrumb($lang->report_center, "modcp.php?action=reports");
	add_breadcrumb($lang->all_reports, "modcp.php?action=allreports");

	if(!$mybb->settings['threadsperpage'])
	{
		$mybb->settings['threadsperpage'] = 20;
	}

	// Figure out if we need to display multiple pages.
	$perpage = $mybb->settings['threadsperpage'];
	if($mybb->get_input('page') != "last")
	{
		$page = $mybb->get_input('page', 1);
	}

	$query = $db->simple_select("reportedposts", "COUNT(rid) AS count");
	$warnings = $db->fetch_field($query, "count");

	if(isset($mybb->input['rid']))
	{
		$mybb->input['rid'] = $mybb->get_input('rid', 1);
		$query = $db->simple_select("reportedposts", "COUNT(rid) AS count", "rid <= '".$mybb->input['rid']."'");
		$result = $db->fetch_field($query, "count");
		if(($result % $perpage) == 0)
		{
			$page = $result / $perpage;
		}
		else
		{
			$page = intval($result / $perpage) + 1;
		}
	}
	$postcount = intval($warnings);
	$pages = $postcount / $perpage;
	$pages = ceil($pages);

	if($mybb->get_input('page') == "last")
	{
		$page = $pages;
	}

	if($page > $pages || $page <= 0)
	{
		$page = 1;
	}

	if($page)
	{
		$start = ($page-1) * $perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}
	$upper = $start+$perpage;

	$multipage = multipage($postcount, $perpage, $page, "modcp.php?action=allreports");
	$allreportspages = '';
	if($postcount > $perpage)
	{
		eval("\$allreportspages = \"".$templates->get("modcp_reports_multipage")."\";");
	}

	$plugins->run_hooks("modcp_allreports_start");

	$query = $db->query("
		SELECT r.*, u.username, up.username AS postusername, up.uid AS postuid, t.subject AS threadsubject, pr.username AS profileusername
		FROM ".TABLE_PREFIX."reportedposts r
		LEFT JOIN ".TABLE_PREFIX."posts p ON (r.pid=p.pid)
		LEFT JOIN ".TABLE_PREFIX."threads t ON (p.tid=t.tid)
		LEFT JOIN ".TABLE_PREFIX."users u ON (r.uid=u.uid)
		LEFT JOIN ".TABLE_PREFIX."users up ON (p.uid=up.uid)
		LEFT JOIN ".TABLE_PREFIX."users pr ON (pr.uid=r.pid)
		ORDER BY r.dateline DESC
		LIMIT {$start}, {$perpage}
	");

	$allreports = '';
	if(!$db->num_rows($query))
	{
		eval("\$allreports = \"".$templates->get("modcp_reports_allnoreports")."\";");
	}
	else
	{
		while($report = $db->fetch_array($query))
		{
			$trow = alt_trow();

			if($report['type'] == 'post')
			{
				$post = get_post_link($report['pid'])."#pid{$report['pid']}";
				$user = build_profile_link($report['postusername'], $report['postuid']);
				$report_data['content'] = $lang->sprintf($lang->report_info_post, $post, $user);

				$thread_link = get_thread_link($report['tid']);
				$thread_subject = htmlspecialchars_uni($report['threadsubject']);
				$report_data['content'] .= $lang->sprintf($lang->report_info_post_thread, $thread_link, $thread_subject);
			}
			else if($report['type'] == 'profile')
			{
				$user = build_profile_link($report['profileusername'], $report['pid']);
				$report_data['content'] = $lang->sprintf($lang->report_info_profile, $user);
			}
			else if($report['type'] == 'reputation')
			{
				$user = build_profile_link($report['profileusername'], $report['fid']);
				$reputation_link = "reputation.php?uid={$report['fid']}#rid{$report['pid']}";
				$report_data['content'] = $lang->sprintf($lang->report_info_reputation, $reputation_link, $user);
			}

			// Report reason and comment
			$report_data['comment'] = $lang->na;
			$report_string = "report_reason_{$report['reason']}";

			$report['reporterlink'] = get_profile_link($report['uid']);

			if(isset($lang->$report_string))
			{
				$report_data['comment'] = $lang->$report_string;
			}
			else if(!empty($report['reason']))
			{
				$report_data['comment'] = htmlspecialchars_uni($report['reason']);
			}

			$report_data['reports'] = my_number_format($report['reports']);
			$report_data['time'] = my_date('relative', $report['dateline']);

			$plugins->run_hooks("modcp_allreports_report");
			eval("\$allreports .= \"".$templates->get("modcp_reports_allreport")."\";");
		}
	}

	$plugins->run_hooks("modcp_allreports_end");

	eval("\$allreportedposts = \"".$templates->get("modcp_reports_allreports")."\";");
	output_page($allreportedposts);
}

if($mybb->input['action'] == "modlogs")
{
	add_breadcrumb($lang->mcp_nav_modlogs, "modcp.php?action=modlogs");

	$perpage = $mybb->get_input('perpage', 1);
	if(!$perpage || $perpage <= 0)
	{
		$perpage = $mybb->settings['threadsperpage'];
	}

	$where = '';

	// Searching for entries by a particular user
	if($mybb->get_input('uid', 1))
	{
		$where .= " AND l.uid='".$mybb->get_input('uid', 1)."'";
	}

	// Searching for entries in a specific forum
	if($mybb->get_input('fid', 1))
	{
		$where .= " AND t.fid='".$mybb->get_input('fid', 1)."'";
	}

	$mybb->input['sortby'] = $mybb->get_input('sortby');

	// Order?
	switch($mybb->input['sortby'])
	{
		case "username":
			$sortby = "u.username";
			break;
		case "forum":
			$sortby = "f.name";
			break;
		case "thread":
			$sortby = "t.subject";
			break;
		default:
			$sortby = "l.dateline";
	}
	$order = $mybb->get_input('order');
	if($order != "asc")
	{
		$order = "desc";
	}

	$plugins->run_hooks("modcp_modlogs_start");

	$query = $db->query("
		SELECT COUNT(l.dateline) AS count
		FROM ".TABLE_PREFIX."moderatorlog l
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid)
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=l.tid)
		WHERE 1=1 {$where}{$tflist}
	");
	$rescount = $db->fetch_field($query, "count");

	// Figure out if we need to display multiple pages.
	if($mybb->get_input('page') != "last")
	{
		$page = $mybb->get_input('page', 1);
	}

	$postcount = intval($rescount);
	$pages = $postcount / $perpage;
	$pages = ceil($pages);

	if($mybb->get_input('page') == "last")
	{
		$page = $pages;
	}

	if($page > $pages || $page <= 0)
	{
		$page = 1;
	}

	if($page)
	{
		$start = ($page-1) * $perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}

	$page_url = 'modcp.php?action=modlogs&amp;perpage='.$perpage;
	foreach(array('uid', 'fid') as $field)
	{
		$mybb->input[$field] = $mybb->get_input($field, 1);
		if(!empty($mybb->input[$field]))
		{
			$page_url .= "&amp;{$field}=".$mybb->input[$field];
		}
	}
	foreach(array('sortby', 'order') as $field)
	{
		$mybb->input[$field] = htmlspecialchars_uni($mybb->get_input($field));
		if(!empty($mybb->input[$field]))
		{
			$page_url .= "&amp;{$field}=".$mybb->input[$field];
		}
	}

	$multipage = multipage($postcount, $perpage, $page, $page_url);
	$resultspages = '';
	if($postcount > $perpage)
	{
		eval("\$resultspages = \"".$templates->get("modcp_modlogs_multipage")."\";");
	}
	$query = $db->query("
		SELECT l.*, u.username, u.usergroup, u.displaygroup, t.subject AS tsubject, f.name AS fname, p.subject AS psubject
		FROM ".TABLE_PREFIX."moderatorlog l
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid)
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=l.tid)
		LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=l.fid)
		LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=l.pid)
		WHERE 1=1 {$where}{$tflist}
		ORDER BY {$sortby} {$order}
		LIMIT {$start}, {$perpage}
	");
	$results = '';
	while($logitem = $db->fetch_array($query))
	{
		$information = '';
		$logitem['action'] = htmlspecialchars_uni($logitem['action']);
		$log_date = my_date('relative', $logitem['dateline']);
		$trow = alt_trow();
		$username = format_name($logitem['username'], $logitem['usergroup'], $logitem['displaygroup']);
		$logitem['profilelink'] = build_profile_link($username, $logitem['uid']);
		$logitem['ipaddress'] = my_inet_ntop($db->unescape_binary($logitem['ipaddress']));
		if($logitem['tsubject'])
		{
			$information = "<strong>{$lang->thread}</strong> <a href=\"".get_thread_link($logitem['tid'])."\" target=\"_blank\">".htmlspecialchars_uni($logitem['tsubject'])."</a><br />";
		}
		if($logitem['fname'])
		{
			$information .= "<strong>{$lang->forum}</strong> <a href=\"".get_forum_link($logitem['fid'])."\" target=\"_blank\">{$logitem['fname']}</a><br />";
		}
		if($logitem['psubject'])
		{
			$information .= "<strong>{$lang->post}</strong> <a href=\"".get_post_link($logitem['pid'])."#pid{$logitem['pid']}\">".htmlspecialchars_uni($logitem['psubject'])."</a>";
		}

		// Edited a user?
		if(!$logitem['tsubject'] || !$logitem['fname'] || !$logitem['psubject'])
		{
			$data = unserialize($logitem['data']);
			if(!empty($data['uid']))
			{
				$information = $lang->sprintf($lang->edited_user_info, htmlspecialchars_uni($data['username']), get_profile_link($data['uid']));
			}
		}

		eval("\$results .= \"".$templates->get("modcp_modlogs_result")."\";");
	}

	if(!$results)
	{
		eval("\$results = \"".$templates->get("modcp_modlogs_noresults")."\";");
	}

	$plugins->run_hooks("modcp_modlogs_filter");

	// Fetch filter options
	$sortbysel = array('username' => '', 'forum' => '', 'thread' => '', 'dateline' => '');
	$sortbysel[$mybb->input['sortby']] = "selected=\"selected\"";
	$ordersel = array('asc' => '', 'desc' => '');
	$ordersel[$order] = "selected=\"selected\"";
	$user_options = '';
	$query = $db->query("
		SELECT DISTINCT l.uid, u.username
		FROM ".TABLE_PREFIX."moderatorlog l
		LEFT JOIN ".TABLE_PREFIX."users u ON (l.uid=u.uid)
		ORDER BY u.username ASC
	");
	while($user = $db->fetch_array($query))
	{
		// Deleted Users
		if(!$user['username'])
		{
			$user['username'] = $lang->na_deleted;
		}

		$selected = '';
		if($mybb->get_input('uid', 1) == $user['uid'])
		{
			$selected = " selected=\"selected\"";
		}
		$user_options .= "<option value=\"{$user['uid']}\"{$selected}>".htmlspecialchars_uni($user['username'])."</option>\n";
	}

	$forum_select = build_forum_jump("", $mybb->get_input('fid', 1), 1, '', 0, true, '', "fid");

	eval("\$modlogs = \"".$templates->get("modcp_modlogs")."\";");
	output_page($modlogs);
}

if($mybb->input['action'] == "do_delete_announcement")
{
	verify_post_check($mybb->get_input('my_post_key'));

	$aid = $mybb->get_input('aid');
	$query = $db->simple_select("announcements", "aid, subject, fid", "aid='{$aid}'");
	$announcement = $db->fetch_array($query);

	if(!$announcement)
	{
		error($lang->error_invalid_announcement);
	}
	if(($mybb->usergroup['issupermod'] != 1 && $announcement['fid'] == -1) || ($announcement['fid'] != -1 && !is_moderator($announcement['fid'])) || ($unviewableforums && in_array($announcement['fid'], $unviewableforums)))
	{
		error_no_permission();
	}

	$plugins->run_hooks("modcp_do_delete_announcement");

	$db->delete_query("announcements", "aid='{$aid}'");
	$cache->update_forumsdisplay();

	redirect("modcp.php?action=announcements", $lang->redirect_delete_announcement);
}

if($mybb->input['action'] == "delete_announcement")
{
	$aid = $mybb->get_input('aid');
	$query = $db->simple_select("announcements", "aid, subject, fid", "aid='{$aid}'");

	$announcement = $db->fetch_array($query);
	$announcement['subject'] = htmlspecialchars_uni($announcement['subject']);

	if(!$announcement)
	{
		error($lang->error_invalid_announcement);
	}

	if(($mybb->usergroup['issupermod'] != 1 && $announcement['fid'] == -1) || ($announcement['fid'] != -1 && !is_moderator($announcement['fid'])) || ($unviewableforums && in_array($announcement['fid'], $unviewableforums)))
	{
		error_no_permission();
	}

	$plugins->run_hooks("modcp_delete_announcement");

	eval("\$announcements = \"".$templates->get("modcp_announcements_delete")."\";");
	output_page($announcements);
}

if($mybb->input['action'] == "do_new_announcement")
{
	verify_post_check($mybb->get_input('my_post_key'));

	$announcement_fid = $mybb->get_input('fid', 1);
	if(($mybb->usergroup['issupermod'] != 1 && $announcement_fid == -1) || ($announcement_fid != -1 && !is_moderator($announcement_fid)) || ($unviewableforums && in_array($announcement['fid'], $unviewableforums)))
	{
		error_no_permission();
	}

	$errors = array();

	$mybb->input['title'] = $mybb->get_input('title');
	if(!trim($mybb->input['title']))
	{
		$errors[] = $lang->error_missing_title;
	}

	$mybb->input['message'] = $mybb->get_input('message');
	if(!trim($mybb->input['message']))
	{
		$errors[] = $lang->error_missing_message;
	}

	if(!$announcement_fid)
	{
		$errors[] = $lang->error_missing_forum;
	}

	$mybb->input['starttime_time'] = $mybb->get_input('starttime_time');
	$mybb->input['endtime_time'] = $mybb->get_input('endtime_time');
	$startdate = @explode(" ", $mybb->input['starttime_time']);
	$startdate = @explode(":", $startdate[0]);
	$enddate = @explode(" ", $mybb->input['endtime_time']);
	$enddate = @explode(":", $enddate[0]);

	if(stristr($mybb->input['starttime_time'], "pm"))
	{
		$startdate[0] = 12+$startdate[0];
		if($startdate[0] >= 24)
		{
			$startdate[0] = "00";
		}
	}

	if(stristr($mybb->input['endtime_time'], "pm"))
	{
		$enddate[0] = 12+$enddate[0];
		if($enddate[0] >= 24)
		{
			$enddate[0] = "00";
		}
	}

	$mybb->input['starttime_month'] = $mybb->get_input('starttime_month');
	$months = array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12');
	if(!in_array($mybb->input['starttime_month'], $months))
	{
		$mybb->input['starttime_month'] = '01';
	}

	$startdate = gmmktime(intval($startdate[0]), intval($startdate[1]), 0, (int)$mybb->input['starttime_month'], $mybb->get_input('starttime_day', 1), $mybb->get_input('starttime_year', 1));

	if($startdate < 0 || $startdate == false)
	{
		$errors[] = $lang->error_invalid_start_date;
	}

	if($mybb->get_input('endtime_type', 1) == 2)
	{
		$enddate = '0';
		$mybb->input['endtime_month'] = '01';
	}
	else
	{
		$mybb->input['endtime_month'] = $mybb->get_input('endtime_month');
		if(!in_array($mybb->input['endtime_month'], $months))
		{
			$mybb->input['endtime_month'] = '01';
		}
		$enddate = gmmktime(intval($enddate[0]), intval($enddate[1]), 0, (int)$mybb->input['endtime_month'], $mybb->get_input('endtime_day', 1), $mybb->get_input('endtime_year', 1));
		if($enddate < 0 || $enddate == false)
		{
			$errors[] = $lang->error_invalid_end_date;
		}
		elseif($enddate < $startdate)
		{
			$errors[] = $lang->error_end_before_start;
		}
	}

	if($mybb->get_input('allowhtml', 1) == 1)
	{
		$allowhtml = 1;
	}
	else
	{
		$allowhtml = 0;
	}
	if($mybb->get_input('allowmycode', 1) == 1)
	{
		$allowmycode = 1;
	}
	else
	{
		$allowmycode = 0;
	}
	if($mybb->get_input('allowsmilies', 1) == 1)
	{
		$allowsmilies = 1;
	}
	else
	{
		$allowsmilies = 0;
	}

	$plugins->run_hooks("modcp_do_new_announcement_start");

	if(!$errors)
	{
		if(isset($mybb->input['preview']))
		{
			$preview = array();
			$mybb->input['action'] = 'new_announcement';
		}
		else
		{
			$insert_announcement = array(
				'fid' => $announcement_fid,
				'uid' => $mybb->user['uid'],
				'subject' => $db->escape_string($mybb->input['title']),
				'message' => $db->escape_string($mybb->input['message']),
				'startdate' => $startdate,
				'enddate' => $enddate,
				'allowhtml' => $allowhtml,
				'allowmycode' => $allowmycode,
				'allowsmilies' => $allowsmilies
			);

			$aid = $db->insert_query("announcements", $insert_announcement);

			$plugins->run_hooks("modcp_do_new_announcement_end");

			$cache->update_forumsdisplay();
			redirect("modcp.php?action=announcements", $lang->redirect_add_announcement);
		}
	}
	else
	{
		$mybb->input['action'] = 'new_announcement';
	}
}

if($mybb->input['action'] == "new_announcement")
{
	add_breadcrumb($lang->mcp_nav_announcements, "modcp.php?action=announcements");
	add_breadcrumb($lang->add_announcement, "modcp.php?action=new_announcements");

	$announcement_fid = $mybb->get_input('fid', 1);

	if(($mybb->usergroup['issupermod'] != 1 && $announcement_fid == -1) || ($announcement_fid != -1 && !is_moderator($announcement_fid)) || ($unviewableforums && in_array($announcement['fid'], $unviewableforums)))
	{
		error_no_permission();
	}

	// Deal with inline errors
	if(!empty($errors) || isset($preview))
	{
		if(!empty($errors))
		{
			$errors = inline_error($errors);
		}
		else
		{
			$errors = '';
		}

		// Set $announcement to input stuff
		$announcement['subject'] = $mybb->input['title'];
		$announcement['message'] = $mybb->input['message'];
		$announcement['allowhtml'] = $allowhtml;
		$announcement['allowmycode'] = $allowmycode;
		$announcement['allowsmilies'] = $allowsmilies;

		$startmonth = $mybb->input['starttime_month'];
		$startdateyear = htmlspecialchars_uni($mybb->input['starttime_year']);
		$startday = $mybb->get_input('starttime_day', 1);
		$starttime_time = htmlspecialchars_uni($mybb->input['starttime_time']);
		$endmonth = $mybb->input['endtime_month'];
		$enddateyear = htmlspecialchars_uni($mybb->input['endtime_year']);
		$endday = $mybb->get_input('endtime_day', 1);
		$endtime_time = htmlspecialchars_uni($mybb->input['endtime_time']);
	}
	else
	{
		// Note: dates are in GMT timezone
		$starttime_time = gmdate("g:i a", TIME_NOW);
		$endtime_time = gmdate("g:i a", TIME_NOW);
		$startday = $endday = gmdate("j", TIME_NOW);
		$startmonth = $endmonth = gmdate("m", TIME_NOW);
		$startdateyear = gmdate("Y", TIME_NOW);

		$announcement = array(
			'subject' => '',
			'message' => '',
			'allowhtml' => 1,
			'allowmycode' => 1,
			'allowsmilies' => 1
			);

		$enddateyear = $startdateyear+1;
	}

	// Generate form elements
	$startdateday = $enddateday = '';
	for($i = 1; $i <= 31; ++$i)
	{
		if($startday == $i)
		{
			$startdateday .= "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		}
		else
		{
			$startdateday .= "<option value=\"$i\">$i</option>\n";
		}

		if($endday == $i)
		{
			$enddateday .= "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		}
		else
		{
			$enddateday .= "<option value=\"$i\">$i</option>\n";
		}
	}

	$startmonthsel = $endmonthsel = array();
	foreach(array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12') as $month)
	{
		$startmonthsel[$month] = '';
		$endmonthsel[$month] = '';
	}
	$startmonthsel[$startmonth] = "selected=\"selected\"";
	$endmonthsel[$endmonth] = "selected=\"selected\"";

	$startdatemonth = $enddatemonth = '';

	$startdatemonth .= "<option value=\"01\" {$startmonthsel['01']}>{$lang->january}</option>\n";
	$enddatemonth .= "<option value=\"01\" {$endmonthsel['01']}>{$lang->january}</option>\n";
	$startdatemonth .= "<option value=\"02\" {$startmonthsel['02']}>{$lang->february}</option>\n";
	$enddatemonth .= "<option value=\"02\" {$endmonthsel['02']}>{$lang->february}</option>\n";
	$startdatemonth .= "<option value=\"03\" {$startmonthsel['03']}>{$lang->march}</option>\n";
	$enddatemonth .= "<option value=\"03\" {$endmonthsel['03']}>{$lang->march}</option>\n";
	$startdatemonth .= "<option value=\"04\" {$startmonthsel['04']}>{$lang->april}</option>\n";
	$enddatemonth .= "<option value=\"04\" {$endmonthsel['04']}>{$lang->april}</option>\n";
	$startdatemonth .= "<option value=\"05\" {$startmonthsel['05']}>{$lang->may}</option>\n";
	$enddatemonth .= "<option value=\"05\" {$endmonthsel['05']}>{$lang->may}</option>\n";
	$startdatemonth .= "<option value=\"06\" {$startmonthsel['06']}>{$lang->june}</option>\n";
	$enddatemonth .= "<option value=\"06\" {$endmonthsel['06']}>{$lang->june}</option>\n";
	$startdatemonth .= "<option value=\"07\" {$startmonthsel['07']}>{$lang->july}</option>\n";
	$enddatemonth .= "<option value=\"07\" {$endmonthsel['07']}>{$lang->july}</option>\n";
	$startdatemonth .= "<option value=\"08\" {$startmonthsel['08']}>{$lang->august}</option>\n";
	$enddatemonth .= "<option value=\"08\" {$endmonthsel['08']}>{$lang->august}</option>\n";
	$startdatemonth .= "<option value=\"09\" {$startmonthsel['09']}>{$lang->september}</option>\n";
	$enddatemonth .= "<option value=\"09\" {$endmonthsel['09']}>{$lang->september}</option>\n";
	$startdatemonth .= "<option value=\"10\" {$startmonthsel['10']}>{$lang->october}</option>\n";
	$enddatemonth .= "<option value=\"10\" {$endmonthsel['10']}>{$lang->october}</option>\n";
	$startdatemonth .= "<option value=\"11\" {$startmonthsel['11']}>{$lang->november}</option>\n";
	$enddatemonth .= "<option value=\"11\" {$endmonthsel['11']}>{$lang->november}</option>\n";
	$startdatemonth .= "<option value=\"12\" {$startmonthsel['12']}>{$lang->december}</option>\n";
	$enddatemonth .= "<option value=\"12\" {$endmonthsel['12']}>{$lang->december}</option>\n";

	$title = htmlspecialchars_uni($announcement['subject']);
	$message = htmlspecialchars_uni($announcement['message']);

	$html_sel = $mycode_sel = $smilies_sel = array('yes' => '', 'no' => '');
	if($announcement['allowhtml'])
	{
		$html_sel['yes'] = ' checked="checked"';
	}
	else
	{
		$html_sel['no'] = ' checked="checked"';
	}

	if($announcement['allowmycode'])
	{
		$mycode_sel['yes'] = ' checked="checked"';
	}
	else
	{
		$mycode_sel['no'] = ' checked="checked"';
	}

	if($announcement['allowsmilies'])
	{
		$smilies_sel['yes'] = ' checked="checked"';
	}
	else
	{
		$smilies_sel['no'] = ' checked="checked"';
	}

	$end_type_sel = array('infinite' => '', 'finite' => '');
	if(!isset($mybb->input['endtime_type']) || $mybb->input['endtime_type'] == 2)
	{
		$end_type_sel['infinite'] = ' checked="checked"';
	}
	else
	{
		$end_type_sel['finite'] = ' checked="checked"';
	}

	// MyCode editor
	$codebuttons = build_mycode_inserter();
	$smilieinserter = build_clickable_smilies();

	if(isset($preview))
	{
		$announcementarray = array(
			'aid' => 0,
			'fid' => $announcement_fid,
			'uid' => $mybb->user['uid'],
			'subject' => $mybb->input['title'],
			'message' => $mybb->input['message'],
			'allowhtml' => intval($mybb->input['allowhtml']),
			'allowmycode' => intval($mybb->input['allowmycode']),
			'allowsmilies' => intval($mybb->input['allowsmilies']),
			'dateline' => TIME_NOW,
			'userusername' => $mybb->user['username'],
		);

		$array = $mybb->user;
		foreach($array as $key => $element)
		{
			$announcementarray[$key] = $element;
		}

		// Gather usergroup data from the cache
		// Field => Array Key
		$data_key = array(
			'title' => 'grouptitle',
			'usertitle' => 'groupusertitle',
			'stars' => 'groupstars',
			'starimage' => 'groupstarimage',
			'image' => 'groupimage',
			'namestyle' => 'namestyle',
			'usereputationsystem' => 'usereputationsystem'
		);

		foreach($data_key as $field => $key)
		{
			$announcementarray[$key] = $groupscache[$announcementarray['usergroup']][$field];
		}

		require_once MYBB_ROOT."inc/functions_post.php";
		$postbit = build_postbit($announcementarray, 1);
		eval("\$preview = \"".$templates->get("previewpost")."\";");
	}
	else
		$preview = '';

	$plugins->run_hooks("modcp_new_announcement");

	eval("\$announcements = \"".$templates->get("modcp_announcements_new")."\";");
	output_page($announcements);
}

if($mybb->input['action'] == "do_edit_announcement")
{
	verify_post_check($mybb->get_input('my_post_key'));

	// Get the announcement
	$aid = $mybb->get_input('aid', 1);
	$query = $db->simple_select("announcements", "*", "aid='{$aid}'");
	$announcement = $db->fetch_array($query);

	// Check that it exists
	if(!$announcement)
	{
		error($lang->error_invalid_announcement);
	}

	// Mod has permissions to edit this announcement
	if(($mybb->usergroup['issupermod'] != 1 && $announcement['fid'] == -1) || ($announcement['fid'] != -1 && !is_moderator($announcement['fid'])) || ($unviewableforums && in_array($announcement['fid'], $unviewableforums)))
	{
		error_no_permission();
	}

	$errors = array();

	// Basic error checking
	$mybb->input['title'] = $mybb->get_input('title');
	if(!trim($mybb->input['title']))
	{
		$errors[] = $lang->error_missing_title;
	}

	$mybb->input['message'] = $mybb->get_input('message');
	if(!trim($mybb->input['message']))
	{
		$errors[] = $lang->error_missing_message;
	}

	$mybb->input['starttime_time'] = $mybb->get_input('starttime_time');
	$mybb->input['endtime_time'] = $mybb->get_input('endtime_time');
	$startdate = @explode(" ", $mybb->input['starttime_time']);
	$startdate = @explode(":", $startdate[0]);
	$enddate = @explode(" ", $mybb->input['endtime_time']);
	$enddate = @explode(":", $enddate[0]);

	if(stristr($mybb->input['starttime_time'], "pm"))
	{
		$startdate[0] = 12+$startdate[0];
		if($startdate[0] >= 24)
		{
			$startdate[0] = "00";
		}
	}

	if(stristr($mybb->input['endtime_time'], "pm"))
	{
		$enddate[0] = 12+$enddate[0];
		if($enddate[0] >= 24)
		{
			$enddate[0] = "00";
		}
	}

	$mybb->input['starttime_month'] = $mybb->get_input('starttime_month');
	$months = array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12');
	if(!in_array($mybb->input['starttime_month'], $months))
	{
		$mybb->input['starttime_month'] = '01';
	}

	$startdate = gmmktime(intval($startdate[0]), intval($startdate[1]), 0, (int)$mybb->input['starttime_month'], $mybb->get_input('starttime_day', 1), $mybb->get_input('starttime_year', 1));
	if($startdate < 0 || $startdate == false)
	{
		$errors[] = $lang->error_invalid_start_date;
	}

	if($mybb->get_input('endtime_type', 1) == "2")
	{
		$enddate = '0';
		$mybb->input['endtime_month'] = '01';
	}
	else
	{
		$mybb->input['endtime_month'] = $mybb->get_input('endtime_month');
		if(!in_array($mybb->input['endtime_month'], $months))
		{
			$mybb->input['endtime_month'] = '01';
		}
		$enddate = gmmktime(intval($enddate[0]), intval($enddate[1]), 0, (int)$mybb->input['endtime_month'], $mybb->get_input('endtime_day', 1), $mybb->get_input('endtime_year', 1));
		if($enddate < 0 || $enddate == false)
		{
			$errors[] = $lang->error_invalid_end_date;
		}
		elseif($enddate < $startdate)
		{
			$errors[] = $lang->error_end_before_start;
		}
	}

	if($mybb->get_input('allowhtml', 1) == 1)
	{
		$allowhtml = 1;
	}
	else
	{
		$allowhtml = 0;
	}
	if($mybb->get_input('allowmycode', 1) == 1)
	{
		$allowmycode = 1;
	}
	else
	{
		$allowmycode = 0;
	}
	if($mybb->get_input('allowsmilies', 1) == 1)
	{
		$allowsmilies = 1;
	}
	else
	{
		$allowsmilies = 0;
	}

	$plugins->run_hooks("modcp_do_edit_announcement_start");

	// Proceed to update if no errors
	if(!$errors)
	{
		if(isset($mybb->input['preview']))
		{
			$preview = array();
			$mybb->input['action'] = 'edit_announcement';
		}
		else
		{
			$update_announcement = array(
				'uid' => $mybb->user['uid'],
				'subject' => $db->escape_string($mybb->input['title']),
				'message' => $db->escape_string($mybb->input['message']),
				'startdate' => $startdate,
				'enddate' => $enddate,
				'allowhtml' => $allowhtml,
				'allowmycode' => $allowmycode,
				'allowsmilies' => $allowsmilies
			);

			$db->update_query("announcements", $update_announcement, "aid='{$aid}'");

			$plugins->run_hooks("modcp_do_edit_announcement_end");

			$cache->update_forumsdisplay();
			redirect("modcp.php?action=announcements", $lang->redirect_edit_announcement);
		}
	}
	else
	{
		$mybb->input['action'] = 'edit_announcement';
	}
}

if($mybb->input['action'] == "edit_announcement")
{
	$aid = intval($mybb->input['aid']);

	add_breadcrumb($lang->mcp_nav_announcements, "modcp.php?action=announcements");
	add_breadcrumb($lang->edit_announcement, "modcp.php?action=edit_announcements&amp;aid={$aid}");

	// Get announcement
	if(!isset($announcement))
	{
		$query = $db->simple_select("announcements", "*", "aid='{$aid}'");
		$announcement = $db->fetch_array($query);
	}

	if(!$announcement)
	{
		error($lang->error_invalid_announcement);
	}
	if(($mybb->usergroup['issupermod'] != 1 && $announcement['fid'] == -1) || ($announcement['fid'] != -1 && !is_moderator($announcement['fid'])) || ($unviewableforums && in_array($announcement['fid'], $unviewableforums)))
	{
		error_no_permission();
	}

	if(!$announcement['startdate'])
	{
		// No start date? Make it now.
		$announcement['startdate'] = TIME_NOW;
	}

	$makeshift_end = false;
	if(!$announcement['enddate'])
	{
		$makeshift_end = true;
		$makeshift_time = TIME_NOW;
		if($announcement['startdate'])
		{
			$makeshift_time = $announcement['startdate'];
		}

		// No end date? Make it a year from now.
		$announcement['enddate'] = $makeshift_time + (60 * 60 * 24 * 366);
	}

	// Deal with inline errors
	if(!empty($errors) || isset($preview))
	{
		if(!empty($errors))
		{
			$errors = inline_error($errors);
		}
		else
		{
			$errors = '';
		}

		// Set $announcement to input stuff
		$announcement['subject'] = $mybb->input['title'];
		$announcement['message'] = $mybb->input['message'];
		$announcement['allowhtml'] = $allowhtml;
		$announcement['allowmycode'] = $allowmycode;
		$announcement['allowsmilies'] = $allowsmilies;

		$startmonth = $mybb->input['starttime_month'];
		$startdateyear = htmlspecialchars_uni($mybb->input['starttime_year']);
		$startday = $mybb->get_input('starttime_day', 1);
		$starttime_time = htmlspecialchars_uni($mybb->input['starttime_time']);
		$endmonth = $mybb->input['endtime_month'];
		$enddateyear = htmlspecialchars_uni($mybb->input['endtime_year']);
		$endday = $mybb->get_input('endtime_day', 1);
		$endtime_time = htmlspecialchars_uni($mybb->input['endtime_time']);

		$errored = true;
	}
	else
	{
		// Note: dates are in GMT timezone
		$starttime_time = gmdate('g:i a', $announcement['startdate']);
		$endtime_time = gmdate('g:i a', $announcement['enddate']);

		$startday = gmdate('j', $announcement['startdate']);
		$endday = gmdate('j', $announcement['enddate']);

		$startmonth = gmdate('m', $announcement['startdate']);
		$endmonth = gmdate('m', $announcement['enddate']);

		$startdateyear = gmdate('Y', $announcement['startdate']);
		$enddateyear = gmdate('Y', $announcement['enddate']);

		$errored = false;
	}

	// Generate form elements
	$startdateday = $enddateday = '';
	for($i = 1; $i <= 31; ++$i)
	{
		if($startday == $i)
		{
			$startdateday .= "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		}
		else
		{
			$startdateday .= "<option value=\"$i\">$i</option>\n";
		}

		if($endday == $i)
		{
			$enddateday .= "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		}
		else
		{
			$enddateday .= "<option value=\"$i\">$i</option>\n";
		}
	}

	$startmonthsel = $endmonthsel = array();
	foreach(array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12') as $month)
	{
		$startmonthsel[$month] = '';
		$endmonthsel[$month] = '';
	}
	$startmonthsel[$startmonth] = "selected=\"selected\"";
	$endmonthsel[$endmonth] = "selected=\"selected\"";

	$startdatemonth = $enddatemonth = '';

	$startdatemonth .= "<option value=\"01\" {$startmonthsel['01']}>{$lang->january}</option>\n";
	$enddatemonth .= "<option value=\"01\" {$endmonthsel['01']}>{$lang->january}</option>\n";
	$startdatemonth .= "<option value=\"02\" {$startmonthsel['02']}>{$lang->february}</option>\n";
	$enddatemonth .= "<option value=\"02\" {$endmonthsel['02']}>{$lang->february}</option>\n";
	$startdatemonth .= "<option value=\"03\" {$startmonthsel['03']}>{$lang->march}</option>\n";
	$enddatemonth .= "<option value=\"03\" {$endmonthsel['03']}>{$lang->march}</option>\n";
	$startdatemonth .= "<option value=\"04\" {$startmonthsel['04']}>{$lang->april}</option>\n";
	$enddatemonth .= "<option value=\"04\" {$endmonthsel['04']}>{$lang->april}</option>\n";
	$startdatemonth .= "<option value=\"05\" {$startmonthsel['05']}>{$lang->may}</option>\n";
	$enddatemonth .= "<option value=\"05\" {$endmonthsel['05']}>{$lang->may}</option>\n";
	$startdatemonth .= "<option value=\"06\" {$startmonthsel['06']}>{$lang->june}</option>\n";
	$enddatemonth .= "<option value=\"06\" {$endmonthsel['06']}>{$lang->june}</option>\n";
	$startdatemonth .= "<option value=\"07\" {$startmonthsel['07']}>{$lang->july}</option>\n";
	$enddatemonth .= "<option value=\"07\" {$endmonthsel['07']}>{$lang->july}</option>\n";
	$startdatemonth .= "<option value=\"08\" {$startmonthsel['08']}>{$lang->august}</option>\n";
	$enddatemonth .= "<option value=\"08\" {$endmonthsel['08']}>{$lang->august}</option>\n";
	$startdatemonth .= "<option value=\"09\" {$startmonthsel['09']}>{$lang->september}</option>\n";
	$enddatemonth .= "<option value=\"09\" {$endmonthsel['09']}>{$lang->september}</option>\n";
	$startdatemonth .= "<option value=\"10\" {$startmonthsel['10']}>{$lang->october}</option>\n";
	$enddatemonth .= "<option value=\"10\" {$endmonthsel['10']}>{$lang->october}</option>\n";
	$startdatemonth .= "<option value=\"11\" {$startmonthsel['11']}>{$lang->november}</option>\n";
	$enddatemonth .= "<option value=\"11\" {$endmonthsel['11']}>{$lang->november}</option>\n";
	$startdatemonth .= "<option value=\"12\" {$startmonthsel['12']}>{$lang->december}</option>\n";
	$enddatemonth .= "<option value=\"12\" {$endmonthsel['12']}>{$lang->december}</option>\n";

	$title = htmlspecialchars_uni($announcement['subject']);
	$message = htmlspecialchars_uni($announcement['message']);

	$html_sel = $mycode_sel = $smilies_sel = array('yes' => '', 'no' => '');
	if($announcement['allowhtml'])
	{
		$html_sel['yes'] = ' checked="checked"';
	}
	else
	{
		$html_sel['no'] = ' checked="checked"';
	}

	if($announcement['allowmycode'])
	{
		$mycode_sel['yes'] = ' checked="checked"';
	}
	else
	{
		$mycode_sel['no'] = ' checked="checked"';
	}

	if($announcement['allowsmilies'])
	{
		$smilies_sel['yes'] = ' checked="checked"';
	}
	else
	{
		$smilies_sel['no'] = ' checked="checked"';
	}

	$end_type_sel = array('infinite' => '', 'finite' => '');
	if(($errored && $mybb->get_input('endtime_type', 1) == 2) || (!$errored && intval($announcement['enddate']) == 0) || $makeshift_end == true)
	{
		$end_type_sel['infinite'] = ' checked="checked"';
	}
	else
	{
		$end_type_sel['finite'] = ' checked="checked"';
	}

	// MyCode editor
	$codebuttons = build_mycode_inserter();
	$smilieinserter = build_clickable_smilies();

	if(isset($preview))
	{
		$announcementarray = array(
			'aid' => $announcement['aid'],
			'fid' => $announcement['fid'],
			'uid' => $mybb->user['uid'],
			'subject' => $mybb->input['title'],
			'message' => $mybb->input['message'],
			'allowhtml' => intval($mybb->input['allowhtml']),
			'allowmycode' => intval($mybb->input['allowmycode']),
			'allowsmilies' => intval($mybb->input['allowsmilies']),
			'dateline' => TIME_NOW,
			'userusername' => $mybb->user['username'],
		);

		$array = $mybb->user;
		foreach($array as $key => $element)
		{
			$announcementarray[$key] = $element;
		}

		// Gather usergroup data from the cache
		// Field => Array Key
		$data_key = array(
			'title' => 'grouptitle',
			'usertitle' => 'groupusertitle',
			'stars' => 'groupstars',
			'starimage' => 'groupstarimage',
			'image' => 'groupimage',
			'namestyle' => 'namestyle',
			'usereputationsystem' => 'usereputationsystem'
		);

		foreach($data_key as $field => $key)
		{
			$announcementarray[$key] = $groupscache[$announcementarray['usergroup']][$field];
		}

		require_once MYBB_ROOT."inc/functions_post.php";
		$postbit = build_postbit($announcementarray, 3);
		eval("\$preview = \"".$templates->get("previewpost")."\";");
	}
	else
	{
		$preview = '';
	}

	$plugins->run_hooks("modcp_edit_announcement");

	eval("\$announcements = \"".$templates->get("modcp_announcements_edit")."\";");
	output_page($announcements);
}

if($mybb->input['action'] == "announcements")
{
	add_breadcrumb($lang->mcp_nav_announcements, "modcp.php?action=announcements");

	// Fetch announcements into their proper arrays
	$query = $db->simple_select("announcements", "aid, fid, subject, enddate");
	$announcements = $global_announcements = array();
	while($announcement = $db->fetch_array($query))
	{
		if($announcement['fid'] == -1)
		{
			$global_announcements[$announcement['aid']] = $announcement;
			continue;
		}
		$announcements[$announcement['fid']][$announcement['aid']] = $announcement;
	}

	$announcements_global = '';
	if($mybb->usergroup['issupermod'] == 1)
	{
		if($global_announcements && $mybb->usergroup['issupermod'] == 1)
		{
			// Get the global announcements
			foreach($global_announcements as $aid => $announcement)
			{
				$trow = alt_trow();
				if($announcement['startdate'] > TIME_NOW || ($announcement['enddate'] < TIME_NOW && $announcement['enddate'] != 0))
				{
					$icon = "<img src=\"{$theme['imgdir']}/minioff.png\" alt=\"({$lang->expired})\" title=\"{$lang->expired_announcement}\"  style=\"vertical-align: middle;\" /> ";
				}
				else
				{
					$icon = "<img src=\"{$theme['imgdir']}/minion.png\" alt=\"({$lang->active})\" title=\"{$lang->active_announcement}\"  style=\"vertical-align: middle;\" /> ";
				}

				$subject = htmlspecialchars_uni($announcement['subject']);

				eval("\$announcements_global .= \"".$templates->get("modcp_announcements_announcement_global")."\";");
			}
		}
		else
		{
			// No global announcements
			eval("\$announcements_global = \"".$templates->get("modcp_no_announcements_global")."\";");
		}
		eval("\$announcements_global = \"".$templates->get("modcp_announcements_global")."\";");
	}

	$announcements_forum = '';
	fetch_forum_announcements();

	if(!$announcements_forum)
	{
		eval("\$announcements_forum = \"".$templates->get("modcp_no_announcements_forum")."\";");
	}

	$plugins->run_hooks("modcp_announcements");

	eval("\$announcements = \"".$templates->get("modcp_announcements")."\";");
	output_page($announcements);
}

if($mybb->input['action'] == "do_modqueue")
{
	require_once MYBB_ROOT."inc/class_moderation.php";
	$moderation = new Moderation;

	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("modcp_do_modqueue_start");

	$mybb->input['threads'] = $mybb->get_input('threads', 2);
	$mybb->input['posts'] = $mybb->get_input('posts', 2);
	$mybb->input['attachments'] = $mybb->get_input('attachments', 2);
	if(!empty($mybb->input['threads']))
	{
		$mybb->input['threads'] = array_map("intval", array_keys($mybb->input['threads']));
		$threads_to_approve = $threads_to_delete = array();
		// Fetch threads
		$query = $db->simple_select("threads", "tid", "tid IN (".implode(",", $mybb->input['threads'])."){$flist}");
		while($thread = $db->fetch_array($query))
		{
			if(!isset($mybb->input['threads'][$thread['tid']]))
			{
				continue;
			}
			$action = $mybb->input['threads'][$thread['tid']];
			if($action == "approve")
			{
				$threads_to_approve[] = $thread['tid'];
			}
			else if($action == "delete")
			{
				$threads_to_delete[] = $thread['tid'];
			}
		}
		if(!empty($threads_to_approve))
		{
			$moderation->approve_threads($threads_to_approve);
			log_moderator_action(array('tids' => $threads_to_approve), $lang->multi_approve_threads);
		}
		if(!empty($threads_to_delete))
		{
			if($mybb->settings['soft_delete'] == 1)
			{
				$moderation->self_delete_threads($threads_to_delete);
				log_moderator_action(array('tids' => $threads_to_delete), $lang->multi_soft_delete_threads);
			}
			else
			{
				foreach($threads_to_delete as $tid)
				{
						$moderation->delete_thread($tid);
				}
				log_moderator_action(array('tids' => $threads_to_delete), $lang->multi_delete_threads);
			}
		}

		$plugins->run_hooks("modcp_do_modqueue_end");

		redirect("modcp.php?action=modqueue", $lang->redirect_threadsmoderated);
	}
	else if(!empty($mybb->input['posts']))
	{
		$mybb->input['posts'] = array_map("intval", array_keys($mybb->input['posts']));
		// Fetch posts
		$posts_to_approve = $posts_to_delete = array();
		$query = $db->simple_select("posts", "pid", "pid IN (".implode(",", $mybb->input['posts'])."){$flist}");
		while($post = $db->fetch_array($query))
		{
			if(!isset($mybb->input['posts'][$post['pid']]))
			{
				continue;
			}
			$action = $mybb->input['posts'][$post['pid']];
			if($action == "approve")
			{
				$posts_to_approve[] = $post['pid'];
			}
			else if($action == "delete" && $mybb->settings['soft_delete'] != 1)
			{
				$moderation->delete_post($post['pid']);
			}
			else if($action == "delete")
			{
				$posts_to_delete[] = $post['pid'];
			}
		}
		if(!empty($posts_to_approve))
		{
			$moderation->approve_posts($posts_to_approve);
			log_moderator_action(array('pids' => $posts_to_approve), $lang->multi_approve_posts);
		}
		if(!empty($posts_to_delete))
		{
			if($mybb->settings['soft_delete'] == 1)
			{
				$moderation->self_delete_posts($posts_to_delete);
				log_moderator_action(array('pids' => $posts_to_delete), $lang->multi_soft_delete_posts);
			}
			else
			{
				log_moderator_action(array('pids' => $posts_to_delete), $lang->multi_delete_posts);
			}
		}

		$plugins->run_hooks("modcp_do_modqueue_end");

		redirect("modcp.php?action=modqueue&type=posts", $lang->redirect_postsmoderated);
	}
	else if(!empty($mybb->input['attachments']))
	{
		$mybb->input['attachments'] = array_map("intval", array_keys($mybb->input['attachments']));
		$query = $db->query("
			SELECT a.pid, a.aid
			FROM  ".TABLE_PREFIX."attachments a
			LEFT JOIN ".TABLE_PREFIX."posts p ON (a.pid=p.pid)
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			WHERE aid IN (".implode(",", $mybb->input['attachments'])."){$tflist}
		");
		while($attachment = $db->fetch_array($query))
		{
			if(!isset($mybb->input['attachments'][$attachment['aid']]))
			{
				continue;
			}
			$action = $mybb->input['attachments'][$attachment['aid']];
			if($action == "approve")
			{
				$db->update_query("attachments", array("visible" => 1), "aid='{$attachment['aid']}'");
			}
			else if($action == "delete")
			{
				remove_attachment($attachment['pid'], '', $attachment['aid']);
			}
		}

		$plugins->run_hooks("modcp_do_modqueue_end");

		redirect("modcp.php?action=modqueue&type=attachments", $lang->redirect_attachmentsmoderated);
	}
}

if($mybb->input['action'] == "modqueue")
{
	$mybb->input['type'] = $mybb->get_input('type');
	$threadqueue = $postqueue = $attachmentqueue = '';
	if($mybb->input['type'] == "threads" || !$mybb->input['type'])
	{
		$forum_cache = $cache->read("forums");

		$query = $db->simple_select("threads", "COUNT(tid) AS unapprovedthreads", "visible=0 {$flist}");
		$unapproved_threads = $db->fetch_field($query, "unapprovedthreads");

		// Figure out if we need to display multiple pages.
		if($mybb->get_input('page') != "last")
		{
			$page = $mybb->get_input('page', 1);
		}

		$perpage = $mybb->settings['threadsperpage'];
		$pages = $unapproved_threads / $perpage;
		$pages = ceil($pages);

		if($mybb->get_input('page') == "last")
		{
			$page = $pages;
		}

		if($page > $pages || $page <= 0)
		{
			$page = 1;
		}

		if($page)
		{
			$start = ($page-1) * $perpage;
		}
		else
		{
			$start = 0;
			$page = 1;
		}

		$multipage = multipage($unapproved_threads, $perpage, $page, "modcp.php?action=modqueue&type=threads");

		$query = $db->query("
			SELECT t.tid, t.dateline, t.fid, t.subject, t.username AS threadusername, p.message AS postmessage, u.username AS username, t.uid
			FROM ".TABLE_PREFIX."threads t
			LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=t.firstpost)
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=t.uid)
			WHERE t.visible='0' {$tflist}
			ORDER BY t.lastpost DESC
			LIMIT {$start}, {$perpage}
		");
		$threads = '';
		while($thread = $db->fetch_array($query))
		{
			$altbg = alt_trow();
			$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
			$thread['threadlink'] = get_thread_link($thread['tid']);
			$thread['forumlink'] = get_forum_link($thread['fid']);
			$forum_name = $forum_cache[$thread['fid']]['name'];
			$threaddate = my_date('relative', $thread['dateline']);

			if($thread['username'] == "")
			{
				if($thread['threadusername'] != "")
				{
					$profile_link = $thread['threadusername'];
				}
				else
				{
					$profile_link = $lang->guest;
				}
			}
			else
			{
				$profile_link = build_profile_link($thread['username'], $thread['uid']);
			}

			$thread['postmessage'] = nl2br(htmlspecialchars_uni($thread['postmessage']));
			$forum = "<strong>{$lang->meta_forum} <a href=\"{$thread['forumlink']}\">{$forum_name}</a></strong>";
			eval("\$threads .= \"".$templates->get("modcp_modqueue_threads_thread")."\";");
		}

		if(!$threads && $mybb->input['type'] == "threads")
		{
			eval("\$threads = \"".$templates->get("modcp_modqueue_threads_empty")."\";");
		}

		if($threads)
		{
			add_breadcrumb($lang->mcp_nav_modqueue_threads, "modcp.php?action=modqueue&amp;type=threads");

			$plugins->run_hooks("modcp_modqueue_threads_end");

			eval("\$mass_controls = \"".$templates->get("modcp_modqueue_masscontrols")."\";");
			eval("\$threadqueue = \"".$templates->get("modcp_modqueue_threads")."\";");
			output_page($threadqueue);
		}
		$type = 'threads';
	}

	if($mybb->input['type'] == "posts" || (!$mybb->input['type'] && !$threadqueue))
	{
		$forum_cache = $cache->read("forums");

		$query = $db->query("
			SELECT COUNT(pid) AS unapprovedposts
			FROM  ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			WHERE p.visible='0' {$tflist} AND t.firstpost != p.pid
		");
		$unapproved_posts = $db->fetch_field($query, "unapprovedposts");

		// Figure out if we need to display multiple pages.
		if($mybb->get_input('page') != "last")
		{
			$page = $mybb->get_input('page', 1);
		}

		$perpage = $mybb->settings['postsperpage'];
		$pages = $unapproved_posts / $perpage;
		$pages = ceil($pages);

		if($mybb->get_input('page') == "last")
		{
			$page = $pages;
		}

		if($page > $pages || $page <= 0)
		{
			$page = 1;
		}

		if($page)
		{
			$start = ($page-1) * $perpage;
		}
		else
		{
			$start = 0;
			$page = 1;
		}

		$multipage = multipage($unapproved_posts, $perpage, $page, "modcp.php?action=modqueue&amp;type=posts");

		$query = $db->query("
			SELECT p.pid, p.subject, p.message, p.username AS postusername, t.subject AS threadsubject, t.tid, u.username, p.uid, t.fid, p.dateline
			FROM  ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
			WHERE p.visible='0' {$tflist} AND t.firstpost != p.pid
			ORDER BY p.dateline DESC
			LIMIT {$start}, {$perpage}
		");
		$posts = '';
		while($post = $db->fetch_array($query))
		{
			$altbg = alt_trow();
			$post['threadsubject'] = htmlspecialchars_uni($parser->parse_badwords($post['threadsubject']));
			$post['threadlink'] = get_thread_link($post['tid']);
			$post['forumlink'] = get_forum_link($post['fid']);
			$post['postlink'] = get_post_link($post['pid'], $post['tid']);
			$forum_name = $forum_cache[$post['fid']]['name'];
			$postdate = my_date('relative', $post['dateline']);

			if($post['username'] == "")
			{
				if($post['postusername'] != "")
				{
					$profile_link = $post['postusername'];
				}
				else
				{
					$profile_link = $lang->guest;
				}
			}
			else
			{
				$profile_link = build_profile_link($post['username'], $post['uid']);
			}

			$thread = "<strong>{$lang->meta_thread} <a href=\"{$post['threadlink']}\">{$post['threadsubject']}</a></strong>";
			$forum = "<strong>{$lang->meta_forum} <a href=\"{$post['forumlink']}\">{$forum_name}</a></strong><br />";
			$post['message'] = nl2br(htmlspecialchars_uni($post['message']));
			eval("\$posts .= \"".$templates->get("modcp_modqueue_posts_post")."\";");
		}

		if(!$posts && $mybb->input['type'] == "posts")
		{
			eval("\$posts = \"".$templates->get("modcp_modqueue_posts_empty")."\";");
		}

		if($posts)
		{
			add_breadcrumb($lang->mcp_nav_modqueue_posts, "modcp.php?action=modqueue&amp;type=posts");

			$plugins->run_hooks("modcp_modqueue_posts_end");

			eval("\$mass_controls = \"".$templates->get("modcp_modqueue_masscontrols")."\";");
			eval("\$postqueue = \"".$templates->get("modcp_modqueue_posts")."\";");
			output_page($postqueue);
		}
	}

	if($mybb->input['type'] == "attachments" || (!$mybb->input['type'] && !$postqueue && !$threadqueue))
	{
		$query = $db->query("
			SELECT COUNT(aid) AS unapprovedattachments
			FROM  ".TABLE_PREFIX."attachments a
			LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=a.pid)
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			WHERE a.visible='0' {$tflist}
		");
		$unapproved_attachments = $db->fetch_field($query, "unapprovedattachments");

		// Figure out if we need to display multiple pages.
		if($mybb->get_input('page') != "last")
		{
			$page = $mybb->get_input('page', 1);
		}

		$perpage = $mybb->settings['postsperpage'];
		$pages = $unapproved_attachments / $perpage;
		$pages = ceil($pages);

		if($mybb->get_input('page') == "last")
		{
			$page = $pages;
		}

		if($page > $pages || $page <= 0)
		{
			$page = 1;
		}

		if($page)
		{
			$start = ($page-1) * $perpage;
		}
		else
		{
			$start = 0;
			$page = 1;
		}

		$multipage = multipage($unapproved_attachments, $perpage, $page, "modcp.php?action=modqueue&amp;type=attachments");

		$query = $db->query("
			SELECT a.*, p.subject AS postsubject, p.dateline, p.uid, u.username, t.tid, t.subject AS threadsubject
			FROM  ".TABLE_PREFIX."attachments a
			LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=a.pid)
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
			WHERE a.visible='0'
			ORDER BY a.dateuploaded DESC
			LIMIT {$start}, {$perpage}
		");
		$attachments = '';
		while($attachment = $db->fetch_array($query))
		{
			$altbg = alt_trow();

			if(!$attachment['dateuploaded'])
			{
				$attachment['dateuploaded'] = $attachment['dateline'];
			}

			$attachdate = my_date('relative', $attachment['dateuploaded']);

			$attachment['postsubject'] = htmlspecialchars_uni($attachment['postsubject']);
			$attachment['filename'] = htmlspecialchars_uni($attachment['filename']);
			$attachment['threadsubject'] = htmlspecialchars_uni($attachment['threadsubject']);
			$attachment['filesize'] = get_friendly_size($attachment['filesize']);

			$link = get_post_link($attachment['pid'], $attachment['tid']) . "#pid{$attachment['pid']}";
			$thread_link = get_thread_link($attachment['tid']);
			$profile_link = build_profile_link($attachment['username'], $attachment['uid']);

			eval("\$attachments .= \"".$templates->get("modcp_modqueue_attachments_attachment")."\";");
		}

		if(!$attachments && $mybb->input['type'] == "attachments")
		{
			eval("\$attachments = \"".$templates->get("modcp_modqueue_attachments_empty")."\";");
		}

		if($attachments)
		{
			add_breadcrumb($lang->mcp_nav_modqueue_attachments, "modcp.php?action=modqueue&amp;type=attachments");

			$plugins->run_hooks("modcp_modqueue_attachments_end");

			eval("\$mass_controls = \"".$templates->get("modcp_modqueue_masscontrols")."\";");
			eval("\$attachmentqueue = \"".$templates->get("modcp_modqueue_attachments")."\";");
			output_page($attachmentqueue);
		}
	}

	// Still nothing? All queues are empty! :-D
	if(!$threadqueue && !$postqueue && !$attachmentqueue)
	{
		add_breadcrumb($lang->mcp_nav_modqueue, "modcp.php?action=modqueue");

		$plugins->run_hooks("modcp_modqueue_end");

		eval("\$queue = \"".$templates->get("modcp_modqueue_empty")."\";");
		output_page($queue);
	}
}

if($mybb->input['action'] == "do_editprofile")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	$user = get_user($mybb->input['uid']);
	if(!$user)
	{
		error($lang->invalid_user);
	}

	// Check if the current user has permission to edit this user
	if(!modcp_can_manage_user($user['uid']))
	{
		error_no_permission();
	}

	$plugins->run_hooks("modcp_do_editprofile_start");

	if($mybb->get_input('away', 1) == 1 && $mybb->settings['allowaway'] != 0)
	{
		$awaydate = TIME_NOW;
		if(!empty($mybb->input['awayday']))
		{
			// If the user has indicated that they will return on a specific day, but not month or year, assume it is current month and year
			if(!$mybb->get_input('awaymonth', 1))
			{
				$mybb->input['awaymonth'] = my_date('n', $awaydate);
			}
			if(!$mybb->get_input('awayyear', 1))
			{
				$mybb->input['awayyear'] = my_date('Y', $awaydate);
			}

			$return_month = intval(substr($mybb->get_input('awaymonth'), 0, 2));
			$return_day = intval(substr($mybb->get_input('awayday'), 0, 2));
			$return_year = min(intval($mybb->get_input('awayyear')), 9999);

			// Check if return date is after the away date.
			$returntimestamp = gmmktime(0, 0, 0, $return_month, $return_day, $return_year);
			$awaytimestamp = gmmktime(0, 0, 0, my_date('n', $awaydate), my_date('j', $awaydate), my_date('Y', $awaydate));
			if($return_year < my_date('Y', $awaydate) || ($returntimestamp < $awaytimestamp && $return_year == my_date('Y', $awaydate)))
			{
				error($lang->error_modcp_return_date_past);
			}

			$returndate = "{$return_day}-{$return_month}-{$return_year}";
		}
		else
		{
			$returndate = "";
		}
		$away = array(
			"away" => 1,
			"date" => $awaydate,
			"returndate" => $returndate,
			"awayreason" => $mybb->get_input('awayreason')
		);
	}
	else
	{
		$away = array(
			"away" => 0,
			"date" => '',
			"returndate" => '',
			"awayreason" => ''
		);
	}

	// Set up user handler.
	require_once MYBB_ROOT."inc/datahandlers/user.php";
	$userhandler = new UserDataHandler('update');

	// Set the data for the new user.
	$updated_user = array(
		"uid" => $user['uid'],
		"profile_fields" => $mybb->get_input('profile_fields', 2),
		"profile_fields_editable" => true,
		"website" => $mybb->get_input('website'),
		"icq" => $mybb->get_input('icq'),
		"aim" => $mybb->get_input('aim'),
		"yahoo" => $mybb->get_input('yahoo'),
		"msn" => $mybb->get_input('msn'),
		"signature" => $mybb->get_input('signature'),
		"usernotes" => $mybb->get_input('usernotes'),
		"away" => $away
	);

	$updated_user['birthday'] = array(
		"day" => $mybb->get_input('birthday_day', 1),
		"month" => $mybb->get_input('birthday_month', 1),
		"year" => $mybb->get_input('birthday_year', 1)
	);

	if(!empty($mybb->input['usertitle']))
	{
		$updated_user['usertitle'] = $mybb->get_input('usertitle');
	}
	else if(!empty($mybb->input['reverttitle']))
	{
		$updated_user['usertitle'] = '';
	}

	if(!empty($mybb->input['remove_avatar']))
	{
		$updated_user['avatarurl'] = '';
	}

	// Set the data of the user in the datahandler.
	$userhandler->set_data($updated_user);
	$errors = '';

	// Validate the user and get any errors that might have occurred.
	if(!$userhandler->validate_user())
	{
		$errors = $userhandler->get_friendly_errors();
		$mybb->input['action'] = "editprofile";
	}
	else
	{
		// Are we removing an avatar from this user?
		if(!empty($mybb->input['remove_avatar']))
		{
			$extra_user_updates = array(
				"avatar" => "",
				"avatardimensions" => "",
				"avatartype" => ""
			);
			remove_avatars($user['uid']);
		}

		// Moderator "Options" (suspend signature, suspend/moderate posting)
		$moderator_options = array(
			1 => array(
				"action" => "suspendsignature", // The moderator action we're performing
				"period" => "action_period", // The time period we've selected from the dropdown box
				"time" => "action_time", // The time we've entered
				"update_field" => "suspendsignature", // The field in the database to update if true
				"update_length" => "suspendsigtime" // The length of suspension field in the database
			),
			2 => array(
				"action" => "moderateposting",
				"period" => "modpost_period",
				"time" => "modpost_time",
				"update_field" => "moderateposts",
				"update_length" => "moderationtime"
			),
			3 => array(
				"action" => "suspendposting",
				"period" => "suspost_period",
				"time" => "suspost_time",
				"update_field" => "suspendposting",
				"update_length" => "suspensiontime"
			)
		);

		require_once MYBB_ROOT."inc/functions_warnings.php";
		foreach($moderator_options as $option)
		{
			$mybb->input[$option['time']] = $mybb->get_input($option['time'], 1);
			$mybb->input[$option['period']] = $mybb->get_input($option['period']);
			if(empty($mybb->input[$option['action']]))
			{
				if($user[$option['update_field']] == 1)
				{
					// We're revoking the suspension
					$extra_user_updates[$option['update_field']] = 0;
					$extra_user_updates[$option['update_length']] = 0;
				}

				// Skip this option if we haven't selected it
				continue;
			}

			else
			{
				if($mybb->input[$option['time']] == 0 && $mybb->input[$option['period']] != "never" && $user[$option['update_field']] != 1)
				{
					// User has selected a type of ban, but not entered a valid time frame
					$string = $option['action']."_error";
					$errors[] = $lang->$string;
				}

				if(!is_array($errors))
				{
					$suspend_length = fetch_time_length(intval($mybb->input[$option['time']]), $mybb->input[$option['period']]);

					if($user[$option['update_field']] == 1 && ($mybb->input[$option['time']] || $mybb->input[$option['period']] == "never"))
					{
						// We already have a suspension, but entered a new time
						if($suspend_length == "-1")
						{
							// Permanent ban on action
							$extra_user_updates[$option['update_length']] = 0;
						}
						elseif($suspend_length && $suspend_length != "-1")
						{
							// Temporary ban on action
							$extra_user_updates[$option['update_length']] = TIME_NOW + $suspend_length;
						}
					}
					elseif(!$user[$option['update_field']])
					{
						// New suspension for this user... bad user!
						$extra_user_updates[$option['update_field']] = 1;
						if($suspend_length == "-1")
						{
							$extra_user_updates[$option['update_length']] = 0;
						}
						else
						{
							$extra_user_updates[$option['update_length']] = TIME_NOW + $suspend_length;
						}
					}
				}
			}
		}

		// Those with javascript turned off will be able to select both - cheeky!
		// Check to make sure we're not moderating AND suspending posting
		if(isset($extra_user_updates) && $extra_user_updates['moderateposts'] && $extra_user_updates['suspendposting'])
		{
			$errors[] = $lang->suspendmoderate_error;
		}

		if(is_array($errors))
		{
			$mybb->input['action'] = "editprofile";
		}
		else
		{
			$plugins->run_hooks("modcp_do_editprofile_update");

			// Continue with the update if there is no errors
			$user_info = $userhandler->update_user();
			if(!empty($extra_user_updates))
			{
				$db->update_query("users", $extra_user_updates, "uid='{$user['uid']}'");
			}
			log_moderator_action(array("uid" => $user['uid'], "username" => $user['username']), $lang->edited_user);

			$plugins->run_hooks("modcp_do_editprofile_end");

			redirect("modcp.php?action=finduser", $lang->redirect_user_updated);
		}
	}
}

if($mybb->input['action'] == "editprofile")
{
	add_breadcrumb($lang->mcp_nav_editprofile, "modcp.php?action=editprofile");

	$user = get_user($mybb->get_input('uid', 1));
	if(!$user)
	{
		error($lang->invalid_user);
	}

	// Check if the current user has permission to edit this user
	if(!modcp_can_manage_user($user['uid']))
	{
		error_no_permission();
	}

	if($user['website'] == "" || $user['website'] == "http://")
	{
		$user['website'] = "http://";
	}

	if($user['icq'] != "0")
	{
		$user['icq'] = intval($user['icq']);
	}

	if(!$errors)
	{
		$mybb->input = array_merge($user, $mybb->input);
		$birthday = explode('-', $user['birthday']);
		if(!isset($birthday[1]))
		{
			$birthday[1] = '';
		}
		if(!isset($birthday[2]))
		{
			$birthday[2] = '';
		}
		list($mybb->input['birthday_day'], $mybb->input['birthday_month'], $mybb->input['birthday_year']) = $birthday;
	}
	else
	{
		$errors = inline_error($errors);
	}

	// Sanitize all input
	foreach(array('usertitle', 'website', 'icq', 'aim', 'yahoo', 'msn', 'signature', 'birthday_day', 'birthday_month', 'birthday_year') as $field)
	{
		$mybb->input[$field] = htmlspecialchars_uni($mybb->get_input($field));
	}

	// Custom user title, check to see if we have a default group title
	if(!$user['displaygroup'])
	{
		$user['displaygroup'] = $user['usergroup'];
	}

	$displaygroupfields = array('usertitle');
	$display_group = usergroup_displaygroup($user['displaygroup']);

	if(!empty($display_group['usertitle']))
	{
		$defaulttitle = $display_group['usertitle'];
	}
	else
	{
		// Go for post count title if a group default isn't set
		$usertitles = $cache->read('usertitles');

		foreach($usertitles as $title)
		{
			if($title['posts'] <= $mybb->user['postnum'])
			{
				$defaulttitle = $title['title'];
			}
		}
	}

	if(empty($user['usertitle']))
	{
		$lang->current_custom_usertitle = '';
	}

	$bdaydaysel = '';
	for($i = 1; $i <= 31; ++$i)
	{
		if($mybb->input['birthday_day'] == $i)
		{
			$bdaydaysel .= "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		}
		else
		{
			$bdaydaysel .= "<option value=\"$i\">$i</option>\n";
		}
	}
	$bdaymonthsel = array();
	foreach(range(1, 12) as $month)
	{
		$bdaymonthsel[$month] = '';
	}
	$bdaymonthsel[$mybb->input['birthday_month']] = 'selected="selected"';

	if($mybb->settings['allowaway'] != 0)
	{
		$awaycheck = array('', '');
		if($errors)
		{
			if($user['away'] == 1)
			{
				$awaycheck[1] = "checked=\"checked\"";
			}
			else
			{
				$awaycheck[0] = "checked=\"checked\"";
			}
			$returndate = array();
			$returndate[0] = $mybb->get_input('awayday');
			$returndate[1] = $mybb->get_input('awaymonth');
			$returndate[2] = $mybb->get_input('awayyear', 1);
			$user['awayreason'] = htmlspecialchars_uni($mybb->get_input('awayreason'));
		}
		else
		{
			$user['awayreason'] = htmlspecialchars_uni($user['awayreason']);
			if($user['away'] == 1)
			{
				$awaydate = my_date($mybb->settings['dateformat'], $user['awaydate']);
				$awaycheck[1] = "checked=\"checked\"";
				$awaynotice = $lang->sprintf($lang->away_notice_away, $awaydate);
			}
			else
			{
				$awaynotice = $lang->away_notice;
				$awaycheck[0] = "checked=\"checked\"";
			}
			$returndate = explode("-", $user['returndate']);
		}
		$returndatesel = '';
		for($i = 1; $i <= 31; ++$i)
		{
			if($returndate[0] == $i)
			{
				$returndatesel .= "<option value=\"$i\" selected=\"selected\">$i</option>\n";
			}
			else
			{
				$returndatesel .= "<option value=\"$i\">$i</option>\n";
			}
		}
		$returndatemonthsel = array();
		foreach(range(1, 12) as $month)
		{
			$returndatemonthsel[$month] = '';
		}
		if(isset($returndate[1]))
		{
			$returndatemonthsel[$returndate[1]] = " selected=\"selected\"";
		}

		if(!isset($returndate[2]))
		{
			$returndate[2] = '';
		}

		eval("\$awaysection = \"".$templates->get("usercp_profile_away")."\";");
	}

	$plugins->run_hooks("modcp_editprofile_start");

	// Fetch profile fields
	$query = $db->simple_select("userfields", "*", "ufid='{$user['uid']}'");
	$user_fields = $db->fetch_array($query);

	$requiredfields = '';
	$customfields = '';
	$mybb->input['profile_fields'] = $mybb->get_input('profile_fields', 2);
	$query = $db->simple_select("profilefields", "*", "", array('order_by' => 'disporder'));
	while($profilefield = $db->fetch_array($query))
	{
		$profilefield['type'] = htmlspecialchars_uni($profilefield['type']);
		$profilefield['description'] = htmlspecialchars_uni($profilefield['description']);
		$thing = explode("\n", $profilefield['type'], "2");
		$type = $thing[0];
		if(isset($thing[1]))
		{
			$options = $thing[1];
		}
		else
		{
			$options = '';
		}
		$field = "fid{$profilefield['fid']}";
		$select = '';
		if($errors)
		{
			if(isset($mybb->input['profile_fields'][$field]))
			{
				$userfield = $mybb->input['profile_fields'][$field];
			}
			else
			{
				$userfield = '';
			}
		}
		else
		{
			$userfield = $user_fields[$field];
		}
		$code = '';
		if($type == "multiselect")
		{
			if($errors)
			{
				$useropts = $userfield;
			}
			else
			{
				$useropts = explode("\n", $userfield);
			}
			if(is_array($useropts))
			{
				foreach($useropts as $key => $val)
				{
					$seloptions[$val] = $val;
				}
			}
			$expoptions = explode("\n", $options);
			if(is_array($expoptions))
			{
				foreach($expoptions as $key => $val)
				{
					$val = trim($val);
					$val = str_replace("\n", "\\n", $val);

					$sel = "";
					if($val == $seloptions[$val])
					{
						$sel = " selected=\"selected\"";
					}
					$select .= "<option value=\"$val\"$sel>$val</option>\n";
				}
				if(!$profilefield['length'])
				{
					$profilefield['length'] = 3;
				}
				$code = "<select name=\"profile_fields[$field][]\" size=\"{$profilefield['length']}\" multiple=\"multiple\">$select</select>";
			}
		}
		elseif($type == "select")
		{
			$expoptions = explode("\n", $options);
			if(is_array($expoptions))
			{
				foreach($expoptions as $key => $val)
				{
					$val = trim($val);
					$val = str_replace("\n", "\\n", $val);
					$sel = "";
					if($val == $userfield)
					{
						$sel = " selected=\"selected\"";
					}
					$select .= "<option value=\"$val\"$sel>$val</option>";
				}
				if(!$profilefield['length'])
				{
					$profilefield['length'] = 1;
				}
				$code = "<select name=\"profile_fields[$field]\" size=\"{$profilefield['length']}\">$select</select>";
			}
		}
		elseif($type == "radio")
		{
			$expoptions = explode("\n", $options);
			if(is_array($expoptions))
			{
				foreach($expoptions as $key => $val)
				{
					$checked = "";
					if($val == $userfield)
					{
						$checked = " checked=\"checked\"";
					}
					$code .= "<input type=\"radio\" class=\"radio\" name=\"profile_fields[$field]\" value=\"$val\"$checked /> <span class=\"smalltext\">$val</span><br />";
				}
			}
		}
		elseif($type == "checkbox")
		{
			if($errors)
			{
				$useropts = $userfield;
			}
			else
			{
				$useropts = explode("\n", $userfield);
			}
			if(is_array($useropts))
			{
				foreach($useropts as $key => $val)
				{
					$seloptions[$val] = $val;
				}
			}
			$expoptions = explode("\n", $options);
			if(is_array($expoptions))
			{
				foreach($expoptions as $key => $val)
				{
					$checked = "";
					if($val == $seloptions[$val])
					{
						$checked = " checked=\"checked\"";
					}
					$code .= "<input type=\"checkbox\" class=\"checkbox\" name=\"profile_fields[$field][]\" value=\"$val\"$checked /> <span class=\"smalltext\">$val</span><br />";
				}
			}
		}
		elseif($type == "textarea")
		{
			$value = htmlspecialchars_uni($userfield);
			$code = "<textarea name=\"profile_fields[$field]\" rows=\"6\" cols=\"30\" style=\"width: 95%\">$value</textarea>";
		}
		else
		{
			$value = htmlspecialchars_uni($userfield);
			$maxlength = "";
			if($profilefield['maxlength'] > 0)
			{
				$maxlength = " maxlength=\"{$profilefield['maxlength']}\"";
			}
			$code = "<input type=\"text\" name=\"profile_fields[$field]\" class=\"textbox\" size=\"{$profilefield['length']}\"{$maxlength} value=\"$value\" />";
		}
		if($profilefield['required'] == 1)
		{
			eval("\$requiredfields .= \"".$templates->get("usercp_profile_customfield")."\";");
		}
		else
		{
			eval("\$customfields .= \"".$templates->get("usercp_profile_customfield")."\";");
		}
		$altbg = alt_trow();
		$code = "";
		$select = "";
		$val = "";
		$options = "";
		$expoptions = "";
		$useropts = "";
		$seloptions = "";
	}
	if($customfields)
	{
		eval("\$customfields = \"".$templates->get("usercp_profile_profilefields")."\";");
	}

	$lang->edit_profile = $lang->sprintf($lang->edit_profile, $user['username']);
	$profile_link = build_profile_link(format_name($user['username'], $user['usergroup'], $user['displaygroup']), $user['uid']);

	$codebuttons = build_mycode_inserter("signature");

	// Do we mark the suspend signature box?
	if($user['suspendsignature'] || ($mybb->get_input('suspendsignature', 1) && !empty($errors)))
	{
		$checked = 1;
		$checked_item = "checked=\"checked\"";
	}
	else
	{
		$checked = 0;
		$checked_item = '';
	}

	// Do we mark the moderate posts box?
	if($user['moderateposts'] || ($mybb->get_input('moderateposting', 1) && !empty($errors)))
	{
		$modpost_check = 1;
		$modpost_checked = "checked=\"checked\"";
	}
	else
	{
		$modpost_check = 0;
		$modpost_checked = '';
	}

	// Do we mark the suspend posts box?
	if($user['suspendposting'] || ($mybb->get_input('suspendposting', 1) && !empty($errors)))
	{
		$suspost_check = 1;
		$suspost_checked = "checked=\"checked\"";
	}
	else
	{
		$suspost_check = 0;
		$suspost_checked = '';
	}

	$moderator_options = array(
		1 => array(
			"action" => "suspendsignature", // The input action for this option
			"option" => "suspendsignature", // The field in the database that this option relates to
			"time" => "action_time", // The time we've entered
			"length" => "suspendsigtime", // The length of suspension field in the database
			"select_option" => "action" // The name of the select box of this option
		),
		2 => array(
			"action" => "moderateposting",
			"option" => "moderateposts",
			"time" => "modpost_time",
			"length" => "moderationtime",
			"select_option" => "modpost"
		),
		3 => array(
			"action" => "suspendposting",
			"option" => "suspendposting",
			"time" => "suspost_time",
			"length" => "suspensiontime",
			"select_option" => "suspost"
		)
	);

	$periods = array(
		"hours" => $lang->expire_hours,
		"days" => $lang->expire_days,
		"weeks" => $lang->expire_weeks,
		"months" => $lang->expire_months,
		"never" => $lang->expire_permanent
	);

	$suspendsignature_info = $moderateposts_info = $suspendposting_info = '';
	$action_options = $modpost_options = $suspost_options = '';
	foreach($moderator_options as $option)
	{
		$mybb->input[$option['time']] = $mybb->get_input($option['time'], 1);
		// Display the suspension info, if this user has this option suspended
		if($user[$option['option']])
		{
			if($user[$option['length']] == 0)
			{
				// User has a permanent ban
				$string = $option['option']."_perm";
				$suspension_info = $lang->$string;
			}
			else
			{
				// User has a temporary (or limited) ban
				$string = $option['option']."_for";
				$for_date = my_date('relative', $user[$option['length']], '', 2);
				$suspension_info = $lang->sprintf($lang->$string, $for_date);
			}

			switch($option['option'])
			{
				case "suspendsignature":
					eval("\$suspendsignature_info = \"".$templates->get("modcp_editprofile_suspensions_info")."\";");
					break;
				case "moderateposts":
					eval("\$moderateposts_info = \"".$templates->get("modcp_editprofile_suspensions_info")."\";");
					break;
				case "suspendposting":
					eval("\$suspendposting_info = \"".$templates->get("modcp_editprofile_suspensions_info")."\";");
					break;
			}
		}

		// Generate the boxes for this option
		$selection_options = '';
		foreach($periods as $key => $value)
		{
			$string = $option['select_option']."_period";
			if($mybb->get_input($string) == $key)
			{
				$selected = "selected=\"selected\"";
			}
			else
			{
				$selected = '';
			}

			eval("\$selection_options .= \"".$templates->get("modcp_editprofile_select_option")."\";");
		}

		$select_name = $option['select_option']."_period";
		switch($option['option'])
		{
			case "suspendsignature":
				eval("\$action_options = \"".$templates->get("modcp_editprofile_select")."\";");
				break;
			case "moderateposts":
				eval("\$modpost_options = \"".$templates->get("modcp_editprofile_select")."\";");
				break;
			case "suspendposting":
				eval("\$suspost_options = \"".$templates->get("modcp_editprofile_select")."\";");
				break;
		}
	}

	eval("\$suspend_signature = \"".$templates->get("modcp_editprofile_signature")."\";");

	if(!isset($newtitle))
	{
		$newtitle = '';
	}

	$plugins->run_hooks("modcp_editprofile_end");

	eval("\$edituser = \"".$templates->get("modcp_editprofile")."\";");
	output_page($edituser);
}

if($mybb->input['action'] == "finduser")
{
	add_breadcrumb($lang->mcp_nav_users, "modcp.php?action=finduser");

	$perpage = $mybb->get_input('perpage', 1);
	if(!$perpage || $perpage <= 0)
	{
		$perpage = $mybb->settings['threadsperpage'];
	}
	$where = '';

	if(isset($mybb->input['username']))
	{
		$where = " AND LOWER(username) LIKE '%".my_strtolower($db->escape_string_like($mybb->get_input('username')))."%'";
	}

	// Sort order & direction
	switch($mybb->get_input('sortby'))
	{
		case "lastvisit":
			$sortby = "lastvisit";
			break;
		case "postnum":
			$sortby = "postnum";
			break;
		case "username":
			$sortby = "username";
			break;
		default:
			$sortby = "regdate";
	}
	$sortbysel = array('lastvisit' => '', 'postnum' => '', 'username' => '', 'regdate' => '');
	$sortbysel[$mybb->get_input('sortby')] = " selected=\"selected\"";
	$order = $mybb->get_input('order');
	if($order != "asc")
	{
		$order = "desc";
	}
	$ordersel = array('asc' => '', 'desc' => '');
	$ordersel[$order] = " selected=\"selected\"";

	$query = $db->simple_select("users", "COUNT(uid) AS count", "1=1 {$where}");
	$user_count = $db->fetch_field($query, "count");

	// Figure out if we need to display multiple pages.
	if($mybb->get_input('page') != "last")
	{
		$page = $mybb->get_input('page');
	}

	$pages = $user_count / $perpage;
	$pages = ceil($pages);

	if($mybb->get_input('page') == "last")
	{
		$page = $pages;
	}

	if($page > $pages || $page <= 0)
	{
		$page = 1;
	}
	if($page)
	{
		$start = ($page-1) * $perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}

	$page_url = 'modcp.php?action=finduser';
	foreach(array('username', 'sortby', 'order') as $field)
	{
		$mybb->input[$field] = htmlspecialchars_uni($mybb->get_input($field));
		if(!empty($mybb->input[$field]))
		{
			$page_url .= "&amp;{$field}=".$mybb->input[$field];
		}
	}

	$multipage = multipage($user_count, $perpage, $page, $page_url);

	$usergroups_cache = $cache->read("usergroups");

	$plugins->run_hooks("modcp_finduser_start");

	// Fetch out results
	$query = $db->simple_select("users", "*", "1=1 {$where}", array("order_by" => $sortby, "order_dir" => $order, "limit" => $perpage, "limit_start" => $start));
	$users = '';
	while($user = $db->fetch_array($query))
	{
		$alt_row = alt_trow();
		$user['username'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
		$user['postnum'] = my_number_format($user['postnum']);
		$regdate = my_date('relative', $user['regdate']);

		if($user['invisible'] == 1 && $mybb->usergroup['canviewwolinvis'] != 1 && $user['uid'] != $mybb->user['uid'])
		{
			$lastdate = $lang->lastvisit_never;

			if($user['lastvisit'])
			{
				// We have had at least some active time, hide it instead
				$lastdate = $lang->lastvisit_hidden;
			}
		}
		else
		{
			$lastdate = my_date('relative', $user['lastvisit']);
		}

		$usergroup = $usergroups_cache[$user['usergroup']]['title'];
		eval("\$users .= \"".$templates->get("modcp_finduser_user")."\";");
	}

	// No results?
	if(!$users)
	{
		eval("\$users = \"".$templates->get("modcp_finduser_noresults")."\";");
	}

	$plugins->run_hooks("modcp_finduser_end");

	eval("\$finduser = \"".$templates->get("modcp_finduser")."\";");
	output_page($finduser);
}

if($mybb->input['action'] == "warninglogs")
{
	add_breadcrumb($lang->mcp_nav_warninglogs, "modcp.php?action=warninglogs");

	// Filter options
	$where_sql = '';
	$mybb->input['filter'] = $mybb->get_input('filter', 2);
	$mybb->input['search'] = $mybb->get_input('search', 2);
	if(!empty($mybb->input['filter']['username']))
	{
		$search['username'] = $db->escape_string($mybb->input['filter']['username']);
		$query = $db->simple_select("users", "uid", "username='{$search['username']}'");
		$mybb->input['filter']['uid'] = $db->fetch_field($query, "uid");
		$mybb->input['filter']['username'] = htmlspecialchars_uni($mybb->input['filter']['username']);
	}
	else
	{
		$mybb->input['filter']['username'] = '';
	}
	if(!empty($mybb->input['filter']['uid']))
	{
		$search['uid'] = intval($mybb->input['filter']['uid']);
		$where_sql .= " AND w.uid='{$search['uid']}'";
		if(!isset($mybb->input['search']['username']))
		{
			$user = get_user($mybb->input['search']['uid']);
			$mybb->input['search']['username'] = htmlspecialchars_uni($user['username']);
		}
	}
	else
	{
		$mybb->input['filter']['uid'] = '';
	}
	if(!empty($mybb->input['filter']['mod_username']))
	{
		$search['mod_username'] = $db->escape_string($mybb->input['filter']['mod_username']);
		$query = $db->simple_select("users", "uid", "username='{$search['mod_username']}'");
		$mybb->input['filter']['mod_uid'] = $db->fetch_field($query, "uid");
		$mybb->input['filter']['mod_username'] = htmlspecialchars_uni($mybb->input['filter']['mod_username']);
	}
	else
	{
		$mybb->input['filter']['mod_username'] = '';
	}
	if(!empty($mybb->input['filter']['mod_uid']))
	{
		$search['mod_uid'] = intval($mybb->input['filter']['mod_uid']);
		$where_sql .= " AND w.issuedby='{$search['mod_uid']}'";
		if(!isset($mybb->input['search']['mod_username']))
		{
			$mod_user = get_user($mybb->input['search']['uid']);
			$mybb->input['search']['mod_username'] = htmlspecialchars_uni($mod_user['username']);
		}
	}
	else
	{
		$mybb->input['filter']['mod_uid'] = '';
	}
	if(!empty($mybb->input['filter']['reason']))
	{
		$search['reason'] = $db->escape_string_like($mybb->input['filter']['reason']);
		$where_sql .= " AND (w.notes LIKE '%{$search['reason']}%' OR t.title LIKE '%{$search['reason']}%' OR w.title LIKE '%{$search['reason']}%')";
		$mybb->input['filter']['reason'] = htmlspecialchars_uni($mybb->input['filter']['reason']);
	}
	else
	{
		$mybb->input['filter']['reason'] = '';
	}
	$sortbysel = array('username' => '', 'expires' => '', 'issuedby' => '', 'dateline' => '');
	if(!isset($mybb->input['filter']['sortby']))
	{
		$mybb->input['filter']['sortby'] = '';
	}
	switch($mybb->input['filter']['sortby'])
	{
		case "username":
			$sortby = "u.username";
			$sortbysel['username'] = ' selected="selected"';
			break;
		case "expires":
			$sortby = "w.expires";
			$sortbysel['expires'] = ' selected="selected"';
			break;
		case "issuedby":
			$sortby = "i.username";
			$sortbysel['issuedby'] = ' selected="selected"';
			break;
		default: // "dateline"
			$sortby = "w.dateline";
			$sortbysel['dateline'] = ' selected="selected"';
	}
	if(!isset($mybb->input['filter']['order']))
	{
		$mybb->input['filter']['order'] = '';
	}
	$order = $mybb->input['filter']['order'];
	$ordersel = array('asc' => '', 'desc' => '');
	if($order != "asc")
	{
		$order = "desc";
		$ordersel['desc'] = ' selected="selected"';
	}
	else
	{
		$ordersel['asc'] = ' selected="selected"';
	}

	$plugins->run_hooks("modcp_warninglogs_start");

	// Pagination stuff
	$sql = "
		SELECT COUNT(wid) as count
		FROM
			".TABLE_PREFIX."warnings w
			LEFT JOIN ".TABLE_PREFIX."warningtypes t ON (w.tid=t.tid)
		WHERE 1=1
			{$where_sql}
	";
	$query = $db->query($sql);
	$total_warnings = $db->fetch_field($query, 'count');
	$page = $mybb->get_input('page', 1);
	if($page <= 0)
	{
		$page = 1;
	}
	$per_page = 20;
	if(isset($mybb->input['filter']['per_page']) && intval($mybb->input['filter']['per_page']) > 0)
	{
		$per_page = intval($mybb->input['filter']['per_page']);
	}
	$start = ($page-1) * $per_page;
	// Build the base URL for pagination links
	$url = 'modcp.php?action=warninglogs';
	if(is_array($mybb->input['filter']) && count($mybb->input['filter']))
	{
		foreach($mybb->input['filter'] as $field => $value)
		{
			$value = urlencode($value);
			$url .= "&amp;filter[{$field}]={$value}";
		}
	}
	$multipage = multipage($total_warnings, $per_page, $page, $url);

	// The actual query
	$sql = "
		SELECT
			w.wid, w.title as custom_title, w.points, w.dateline, w.issuedby, w.expires, w.expired, w.daterevoked, w.revokedby,
			t.title,
			u.uid, u.username, u.usergroup, u.displaygroup,
			i.uid as mod_uid, i.username as mod_username, i.usergroup as mod_usergroup, i.displaygroup as mod_displaygroup
		FROM ".TABLE_PREFIX."warnings w
			LEFT JOIN ".TABLE_PREFIX."users u ON (w.uid=u.uid)
			LEFT JOIN ".TABLE_PREFIX."warningtypes t ON (w.tid=t.tid)
			LEFT JOIN ".TABLE_PREFIX."users i ON (i.uid=w.issuedby)
		WHERE 1=1
			{$where_sql}
		ORDER BY {$sortby} {$order}
		LIMIT {$start}, {$per_page}
	";
	$query = $db->query($sql);


	$warning_list = '';
	while($row = $db->fetch_array($query))
	{
		$trow = alt_trow();
		$username = format_name($row['username'], $row['usergroup'], $row['displaygroup']);
		$username_link = build_profile_link($username, $row['uid']);
		$mod_username = format_name($row['mod_username'], $row['mod_usergroup'], $row['mod_displaygroup']);
		$mod_username_link = build_profile_link($mod_username, $row['mod_uid']);
		$issued_date = my_date($mybb->settings['dateformat'], $row['dateline']).' '.my_date($mybb->settings['timeformat'], $row['dateline']);
		$revoked_text = '';
		if($row['daterevoked'] > 0)
		{
			$revoked_date = my_date('relative', $row['daterevoked']);
			eval("\$revoked_text = \"".$templates->get("modcp_warninglogs_warning_revoked")."\";");
		}
		if($row['expires'] > 0)
		{
			$expire_date = my_date('relative', $row['expires'], '', 2);
		}
		else
		{
			$expire_date = $lang->never;
		}
		$title = $row['title'];
		if(empty($row['title']))
		{
			$title = $row['custom_title'];
		}
		$title = htmlspecialchars_uni($title);
		if($row['points'] >= 0)
		{
			$points = '+'.$row['points'];
		}

		eval("\$warning_list .= \"".$templates->get("modcp_warninglogs_warning")."\";");
	}

	if(!$warning_list)
	{
		eval("\$warning_list = \"".$templates->get("modcp_warninglogs_nologs")."\";");
	}

	$plugins->run_hooks("modcp_warninglogs_end");

	eval("\$warninglogs = \"".$templates->get("modcp_warninglogs")."\";");
	output_page($warninglogs);
}

if($mybb->input['action'] == "ipsearch")
{
	add_breadcrumb($lang->mcp_nav_ipsearch, "modcp.php?action=ipsearch");

	$mybb->input['ipaddress'] = $mybb->get_input('ipaddress');
	if($mybb->input['ipaddress'])
	{
		if(!is_array($groupscache))
		{
			$groupscache = $cache->read("usergroups");
		}

		$ipaddressvalue = htmlspecialchars_uni($mybb->input['ipaddress']);

		$ip_range = fetch_ip_range($mybb->input['ipaddress']);

		$post_results = $user_results = 0;

		// Searching post IP addresses
		if(isset($mybb->input['search_posts']))
		{
			if($ip_range)
			{
				if(!is_array($ip_range))
				{
					$post_ip_sql = "ipaddress=".$db->escape_binary($ip_range);
				}
				else
				{
					$post_ip_sql = "ipaddress BETWEEN ".$db->escape_binary($ip_range[0])." AND ".$db->escape_binary($ip_range[1]);
				}
			}

			$plugins->run_hooks("modcp_ipsearch_posts_start");

			if($post_ip_sql)
			{
				$query = $db->query("
					SELECT COUNT(pid) AS count
					FROM ".TABLE_PREFIX."posts
					WHERE {$post_ip_sql}
				");

				$post_results = $db->fetch_field($query, "count");
			}
		}

		// Searching user IP addresses
		if(isset($mybb->input['search_users']))
		{
			if($ip_range)
			{
				if(!is_array($ip_range))
				{
					$user_ip_sql = "regip=".$db->escape_binary($ip_range)." OR lastip=".$db->escape_binary($ip_range);
				}
				else
				{
					$user_ip_sql = "regip BETWEEN ".$db->escape_binary($ip_range[0])." AND ".$db->escape_binary($ip_range[1])." OR lastip BETWEEN ".$db->escape_binary($ip_range[0])." AND ".$db->escape_binary($ip_range[1]);
				}
			}

			$plugins->run_hooks("modcp_ipsearch_users_start");

			if($user_ip_sql)
			{
				$query = $db->query("
					SELECT COUNT(uid) AS count
					FROM ".TABLE_PREFIX."users
					WHERE {$user_ip_sql}
				");

				$user_results = $db->fetch_field($query, "count");
			}
		}

		$total_results = $post_results+$user_results;

		if(!$total_results)
		{
			$total_results = 1;
		}

		// Now we have the result counts, paginate
		$perpage = $mybb->get_input('perpage', 1);
		if(!$perpage || $perpage <= 0)
		{
			$perpage = $mybb->settings['threadsperpage'];
		}

		// Figure out if we need to display multiple pages.
		if($mybb->get_input('page') != "last")
		{
			$page = $mybb->get_input('page', 1);
		}

		$pages = $total_results / $perpage;
		$pages = ceil($pages);

		if($mybb->get_input('page') == "last")
		{
			$page = $pages;
		}

		if($page > $pages || $page <= 0)
		{
			$page = 1;
		}

		if($page)
		{
			$start = ($page-1) * $perpage;
		}
		else
		{
			$start = 0;
			$page = 1;
		}

		$page_url = "modcp.php?action=ipsearch&amp;perpage={$perpage}";
		foreach(array('ipaddress', 'search_users', 'search_posts') as $input)
		{
			if(!empty($mybb->input[$input]))
			{
				$page_url .= "&amp;{$input}=".htmlspecialchars_uni($mybb->input[$input]);
			}
		}
		$multipage = multipage($total_results, $perpage, $page, $page_url);

		$post_limit = $perpage;
		$results = '';
		if(isset($mybb->input['search_users']) && $user_results && $start <= $user_results)
		{
			$query = $db->query("
				SELECT username, uid, regip, lastip
				FROM ".TABLE_PREFIX."users
				WHERE {$user_ip_sql}
				ORDER BY regdate DESC
				LIMIT {$start}, {$perpage}
			");
			while($ipaddress = $db->fetch_array($query))
			{
				$result = false;
				$profile_link = build_profile_link($ipaddress['username'], $ipaddress['uid']);
				$trow = alt_trow();
				$ip = false;
				if(is_array($ip_range))
				{
					if(strcmp($ip_range[0], $ipaddress['regip']) >= 0 && strcmp($ip_range[1], $ipaddress['regip']) <= 0)
					{
						$subject = "<strong>{$lang->ipresult_regip}</strong> {$profile_link}";
						$ip = my_inet_ntop($db->unescape_binary($ipaddress['regip']));
					}
					elseif(strcmp($ip_range[0], $ipaddress['lastip']) >= 0 && strcmp($ip_range[1], $ipaddress['lastip']) <= 0)
					{
						$subject = "<strong>{$lang->ipresult_lastip}</strong> {$profile_link}";
						$ip = my_inet_ntop($db->unescape_binary($ipaddress['lastip']));
					}
				}
				elseif($ipaddress['regip'] == $ip_range)
				{
					$subject = "<strong>{$lang->ipresult_regip}</strong> {$profile_link}";
					$ip = my_inet_ntop($db->unescape_binary($ipaddress['regip']));
				}
				elseif($ipaddress['lastip'] == $ip_range)
				{
					$subject = "<strong>{$lang->ipresult_lastip}</strong> {$profile_link}";
					$ip = my_inet_ntop($db->unescape_binary($ipaddress['lastip']));
				}
				if($ip)
				{
					eval("\$results .= \"".$templates->get("modcp_ipsearch_result")."\";");
					$result = true;
				}
				if($result)
				{
					--$post_limit;
				}
			}
		}
		$post_start = 0;
		if($total_results > $user_results && $post_limit)
		{
			$post_start = $start-$user_results;
			if($post_start < 0)
			{
				$post_start = 0;
			}
		}
		if(isset($mybb->input['search_posts']) && $post_results && (!isset($mybb->input['search_users']) || (isset($mybb->input['search_users']) && $post_limit > 0)))
		{
			$ipaddresses = $tids = $uids = array();
			$query = $db->query("
				SELECT username AS postusername, uid, subject, pid, tid, ipaddress
				FROM ".TABLE_PREFIX."posts
				WHERE {$post_ip_sql}
				ORDER BY dateline DESC
				LIMIT {$post_start}, {$post_limit}
			");
			while($ipaddress = $db->fetch_array($query))
			{
				$tids[$ipaddress['tid']] = $ipaddress['pid'];
				$uids[$ipaddress['uid']] = $ipaddress['pid'];
				$ipaddresses[$ipaddress['pid']] = $ipaddress;
			}

			if(!empty($ipaddresses))
			{
				$query = $db->simple_select("threads", "subject, tid", "tid IN(".implode(',', array_keys($tids)).")");
				while($thread = $db->fetch_array($query))
				{
					$ipaddresses[$tids[$thread['tid']]]['threadsubject'] = $thread['subject'];
				}
				unset($tids);

				$query = $db->simple_select("users", "username, uid", "uid IN(".implode(',', array_keys($uids)).")");
				while($user = $db->fetch_array($query))
				{
					$ipaddresses[$uids[$user['uid']]]['username'] = $user['username'];
				}
				unset($uids);

				foreach($ipaddresses as $ipaddress)
				{
					$ip = my_inet_ntop($db->unescape_binary($ipaddress['ipaddress']));
					if(!$ipaddress['username']) $ipaddress['username'] = $ipaddress['postusername']; // Guest username support
					$trow = alt_trow();
					if(!$ipaddress['subject'])
					{
						$ipaddress['subject'] = "RE: {$ipaddress['threadsubject']}";
					}
					$subject = "<strong>{$lang->ipresult_post}</strong> <a href=\"".get_post_link($ipaddress['pid'], $ipaddress['tid'])."\">".htmlspecialchars_uni($ipaddress['subject'])."</a> {$lang->by} ".build_profile_link($ipaddress['username'], $ipaddress['uid']);
					eval("\$results .= \"".$templates->get("modcp_ipsearch_result")."\";");
				}
			}
		}

		if(!$results)
		{
			eval("\$results = \"".$templates->get("modcp_ipsearch_noresults")."\";");
		}

		if($ipaddressvalue)
		{
			$lang->ipsearch_results = $lang->sprintf($lang->ipsearch_results, $ipaddressvalue);
		}
		else
		{
			$lang->ipsearch_results = $lang->ipsearch;
		}

		if(!strstr($mybb->input['ipaddress'], "*") && !strstr($mybb->input['ipaddress'], "/"))
		{
			$misc_info_link = "<div class=\"float_right\">(<a href=\"modcp.php?action=iplookup&ipaddress=".htmlspecialchars_uni($mybb->input['ipaddress'])."\" onclick=\"MyBB.popupWindow('{$mybb->settings['bburl']}/modcp.php?action=iplookup&ipaddress=".urlencode($mybb->input['ipaddress'])."', 'iplookup', 500, 250); return false;\">{$lang->info_on_ip}</a>)</div>";
		}

		eval("\$ipsearch_results = \"".$templates->get("modcp_ipsearch_results")."\";");
	}

	// Fetch filter options
	if(!$mybb->input['ipaddress'])
	{
		$mybb->input['search_posts'] = 1;
		$mybb->input['search_users'] = 1;
	}
	$usersearchselect = $postsearchselect = '';
	if(isset($mybb->input['search_posts']))
	{
		$postsearchselect = "checked=\"checked\"";
	}
	if(isset($mybb->input['search_users']))
	{
		$usersearchselect = "checked=\"checked\"";
	}

	$plugins->run_hooks("modcp_ipsearch_end");

	eval("\$ipsearch = \"".$templates->get("modcp_ipsearch")."\";");
	output_page($ipsearch);
}

if($mybb->input['action'] == "iplookup")
{
	$mybb->input['ipaddress'] = $mybb->get_input('ipaddress');
	$lang->ipaddress_misc_info = $lang->sprintf($lang->ipaddress_misc_info, htmlspecialchars_uni($mybb->input['ipaddress']));
	$ipaddress_location = $lang->na;
	$ipaddress_host_name = $lang->na;
	$modcp_ipsearch_misc_info = '';
	if(!strstr($mybb->input['ipaddress'], "*"))
	{
		// Return GeoIP information if it is available to us
		if(function_exists('geoip_record_by_name'))
		{
			$ip_record = @geoip_record_by_name($mybb->input['ipaddress']);
			if($ip_record)
			{
				$ipaddress_location = htmlspecialchars_uni(utf8_encode($ip_record['country_name']));
				if($ip_record['city'])
				{
					$ipaddress_location .= $lang->comma.htmlspecialchars_uni(utf8_encode($ip_record['city']));
				}
			}
		}

		$ipaddress_host_name = htmlspecialchars_uni(@gethostbyaddr($mybb->input['ipaddress']));

		// gethostbyaddr returns the same ip on failure
		if($ipaddress_host_name == $mybb->input['ipaddress'])
		{
			$ipaddress_host_name = $lang->na;
		}
	}

	$plugins->run_hooks("modcp_iplookup_end");

	eval("\$iplookup = \"".$templates->get('modcp_ipsearch_misc_info')."\";");
	output_page($iplookup);
}

if($mybb->input['action'] == "banning")
{
	add_breadcrumb($lang->mcp_nav_banning, "modcp.php?action=banning");

	if(!$mybb->settings['threadsperpage'])
	{
		$mybb->settings['threadsperpage'] = 20;
	}

	// Figure out if we need to display multiple pages.
	$perpage = $mybb->settings['threadsperpage'];
	if($mybb->get_input('page') != "last")
	{
		$page = $mybb->get_input('page', 1);
	}

	$query = $db->simple_select("banned", "COUNT(uid) AS count");
	$banned_count = $db->fetch_field($query, "count");

	$postcount = intval($banned_count);
	$pages = $postcount / $perpage;
	$pages = ceil($pages);

	if($mybb->get_input('page') == "last")
	{
		$page = $pages;
	}

	if($page > $pages || $page <= 0)
	{
		$page = 1;
	}

	if($page)
	{
		$start = ($page-1) * $perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}
	$upper = $start+$perpage;

	$multipage = multipage($postcount, $perpage, $page, "modcp.php?action=banning");
	$allbannedpages = '';
	if($postcount > $perpage)
	{
		eval("\$allbannedpages = \"".$templates->get("modcp_banning_multipage")."\";");
	}

	$plugins->run_hooks("modcp_banning_start");

	$query = $db->query("
		SELECT b.*, a.username AS adminuser, u.username
		FROM ".TABLE_PREFIX."banned b
		LEFT JOIN ".TABLE_PREFIX."users u ON (b.uid=u.uid)
		LEFT JOIN ".TABLE_PREFIX."users a ON (b.admin=a.uid)
		ORDER BY dateline DESC
		LIMIT {$start}, {$perpage}
	");

	// Get the banned users
	$bannedusers = '';
	while($banned = $db->fetch_array($query))
	{
		$profile_link = build_profile_link($banned['username'], $banned['uid']);

		// Only show the edit & lift links if current user created ban, or is super mod/admin
		$edit_link = '';
		if($mybb->user['uid'] == $banned['admin'] || !$banned['adminuser'] || $mybb->usergroup['issupermod'] == 1 || $mybb->usergroup['cancp'] == 1)
		{
			$edit_link = "<br /><span class=\"smalltext\"><a href=\"modcp.php?action=banuser&amp;uid={$banned['uid']}\">{$lang->edit_ban}</a> | <a href=\"modcp.php?action=liftban&amp;uid={$banned['uid']}&amp;my_post_key={$mybb->post_code}\">{$lang->lift_ban}</a></span>";
		}

		$admin_profile = build_profile_link($banned['adminuser'], $banned['admin']);

		$trow = alt_trow();

		if($banned['reason'])
		{
			$banned['reason'] = htmlspecialchars_uni($parser->parse_badwords($banned['reason']));
			$banned['reason'] = my_wordwrap($banned['reason']);
		}
		else
		{
			$banned['reason'] = $lang->na;
		}

		if($banned['lifted'] == 'perm' || $banned['lifted'] == '' || $banned['bantime'] == 'perm' || $banned['bantime'] == '---')
		{
			$banlength = $lang->permanent;
			$timeremaining = $lang->na;
		}
		else
		{
			$banlength = $bantimes[$banned['bantime']];
			$remaining = $banned['lifted']-TIME_NOW;

			$timeremaining = nice_time($remaining, array('short' => 1, 'seconds' => false))."";

			if($remaining < 3600)
			{
				$timeremaining = "<span style=\"color: red;\">({$timeremaining} {$lang->ban_remaining})</span>";
			}
			else if($remaining < 86400)
			{
				$timeremaining = "<span style=\"color: maroon;\">({$timeremaining} {$lang->ban_remaining})</span>";
			}
			else if($remaining < 604800)
			{
				$timeremaining = "<span style=\"color: green;\">({$timeremaining} {$lang->ban_remaining})</span>";
			}
			else
			{
				$timeremaining = "({$timeremaining} {$lang->ban_remaining})";
			}
		}

		eval("\$bannedusers .= \"".$templates->get("modcp_banning_ban")."\";");
	}

	if(!$bannedusers)
	{
		eval("\$bannedusers = \"".$templates->get("modcp_banning_nobanned")."\";");
	}

	$plugins->run_hooks("modcp_banning");

	eval("\$bannedpage = \"".$templates->get("modcp_banning")."\";");
	output_page($bannedpage);
}

if($mybb->input['action'] == "liftban")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$query = $db->simple_select("banned", "*", "uid='".$mybb->get_input('uid', 1)."'");
	$ban = $db->fetch_array($query);

	if(!$ban)
	{
		error($lang->error_invalidban);
	}

	// Permission to edit this ban?
	if($mybb->user['uid'] != $ban['admin'] && $mybb->usergroup['issupermod'] != 1 && $mybb->usergroup['cancp'] != 1)
	{
		error_no_permission();
	}

	$plugins->run_hooks("modcp_liftban_start");

	$query = $db->simple_select("users", "username", "uid = '{$ban['uid']}'");
	$username = $db->fetch_field($query, "username");

	$updated_group = array(
		'usergroup' => $ban['oldgroup'],
		'additionalgroups' => $ban['oldadditionalgroups'],
		'displaygroup' => $ban['olddisplaygroup']
	);
	$db->update_query("users", $updated_group, "uid='{$ban['uid']}'");
	$db->delete_query("banned", "uid='{$ban['uid']}'");

	$cache->update_banned();
	$cache->update_moderators();
	log_moderator_action(array("uid" => $ban['uid'], "username" => $username), $lang->lifted_ban);

	$plugins->run_hooks("modcp_liftban_end");

	redirect("modcp.php?action=banning", $lang->redirect_banlifted);
}

if($mybb->input['action'] == "do_banuser" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	// Editing an existing ban
	if($mybb->get_input('uid', 1))
	{
		// Get the users info from their uid
		$query = $db->query("
			SELECT b.*, u.uid, u.usergroup, u.additionalgroups, u.displaygroup
			FROM ".TABLE_PREFIX."banned b
			LEFT JOIN ".TABLE_PREFIX."users u ON (b.uid=u.uid)
			WHERE b.uid='{$mybb->input['uid']}'
		");
		$user = $db->fetch_array($query);
		if(!$user)
		{
			error($lang->error_invalidban);
		}

		// Permission to edit this ban?
		if($mybb->user['uid'] != $user['admin'] && $mybb->usergroup['issupermod'] != 1 && $mybb->usergroup['cancp'] != 1)
		{
			error_no_permission();
		}
	}
	// Creating a new ban
	else
	{
		// Get the users info from their Username
		$query = $db->simple_select("users", "uid, username, usergroup, additionalgroups, displaygroup", "username = '".$db->escape_string($mybb->input['username'])."'", array('limit' => 1));
		$user = $db->fetch_array($query);
		if(!$user)
		{
			$errors[] = $lang->invalid_username;
		}
	}

	if($user['uid'] == $mybb->user['uid'])
	{
		$errors[] = $lang->error_cannotbanself;
	}

	// Have permissions to ban this user?
	if(!modcp_can_manage_user($user['uid']))
	{
		$errors[] = $lang->error_cannotbanuser;
	}

	// Check for an incoming reason
	if(empty($mybb->input['banreason']))
	{
		$errors[] = $lang->error_nobanreason;
	}

	// Check banned group
	$query = $db->simple_select("usergroups", "gid", "isbannedgroup=1 AND gid='".$mybb->get_input('usergroup', 1)."'");
	if(!$db->fetch_field($query, "gid"))
	{
		$errors[] = $lang->error_nobangroup;
	}

	// If this is a new ban, we check the user isn't already part of a banned group
	if(!$mybb->get_input('uid') && $user['uid'])
	{
		$query = $db->simple_select("banned", "uid", "uid='{$user['uid']}'");
		if($db->fetch_field($query, "uid"))
		{
			$errors[] = $lang->error_useralreadybanned;
		}
	}

	$plugins->run_hooks("modcp_do_banuser_start");

	// Still no errors? Ban the user
	if(!$errors)
	{
		// Ban the user
		if($mybb->get_input('liftafter') == '---')
		{
			$lifted = 0;
		}
		else
		{
			if(!isset($user['dateline']))
			{
				$user['dateline'] = 0;
			}
			$lifted = ban_date2timestamp($mybb->get_input('liftafter'), $user['dateline']);
		}

		$banreason = my_substr($mybb->get_input('banreason'), 0, 255);

		if($mybb->get_input('uid', 1))
		{
			$username_select = $db->simple_select('users', 'username', "uid='".$mybb->get_input('uid', 1)."'");
			$user['username'] = $db->fetch_field($username_select, 'username');
			$update_array = array(
				'gid' => $mybb->get_input('usergroup', 1),
				'admin' => $mybb->get_input('uid', 1),
				'dateline' => TIME_NOW,
				'bantime' => $db->escape_string($mybb->get_input('liftafter')),
				'lifted' => $db->escape_string($lifted),
				'reason' => $db->escape_string($banreason)
			);

			$db->update_query('banned', $update_array, "uid='{$user['uid']}'");
		}
		else
		{
			$insert_array = array(
				'uid' => $user['uid'],
				'gid' => $mybb->get_input('usergroup', 1),
				'oldgroup' => $user['usergroup'],
				'oldadditionalgroups' => $user['additionalgroups'],
				'olddisplaygroup' => $user['displaygroup'],
				'admin' => intval($mybb->user['uid']),
				'dateline' => TIME_NOW,
				'bantime' => $db->escape_string($mybb->get_input('liftafter')),
				'lifted' => $db->escape_string($lifted),
				'reason' => $db->escape_string($banreason)
			);

			$db->insert_query('banned', $insert_array);
		}

		// Move the user to the banned group
		$update_array = array(
			'usergroup' => $mybb->get_input('usergroup', 1),
			'displaygroup' => 0,
			'additionalgroups' => '',
		);
		$db->update_query('users', $update_array, "uid = {$user['uid']}");

		$cache->update_banned();

		// Log edit or add ban
		if($mybb->get_input('uid', 1))
		{
			log_moderator_action(array("uid" => $user['uid'], "username" => $user['username']), $lang->edited_user_ban);
		}
		else
		{
			log_moderator_action(array("uid" => $user['uid'], "username" => $user['username']), $lang->banned_user);
		}

		$plugins->run_hooks("modcp_do_banuser_end");

		if($mybb->get_input('uid', 1))
		{
			redirect("modcp.php?action=banning", $lang->redirect_banuser_updated);
		}
		else
		{
			redirect("modcp.php?action=banning", $lang->redirect_banuser);
		}
	}
	// Otherwise has errors, throw back to ban page
	else
	{
		$mybb->input['action'] = "banuser";
	}
}

if($mybb->input['action'] == "banuser")
{
	add_breadcrumb($lang->mcp_nav_banning, "modcp.php?action=banning");

	$mybb->input['uid'] = $mybb->get_input('uid', 1);
	if($mybb->input['uid'])
	{
		add_breadcrumb($lang->mcp_nav_ban_user);
	}
	else
	{
		add_breadcrumb($lang->mcp_nav_editing_ban);
	}

	$plugins->run_hooks("modcp_banuser_start");

	$banuser_username = '';
	$banreason = '';

	// If incoming user ID, we are editing a ban
	if($mybb->input['uid'])
	{
		$query = $db->query("
			SELECT b.*, u.username, u.uid
			FROM ".TABLE_PREFIX."banned b
			LEFT JOIN ".TABLE_PREFIX."users u ON (b.uid=u.uid)
			WHERE b.uid='{$mybb->input['uid']}'
		");
		$banned = $db->fetch_array($query);
		if($banned['username'])
		{
			$username = htmlspecialchars_uni($banned['username']);
			$banreason = htmlspecialchars_uni($banned['reason']);
			$uid = $mybb->input['uid'];
			$user = get_user($banned['uid']);
			$lang->ban_user = $lang->edit_ban; // Swap over lang variables
			eval("\$banuser_username = \"".$templates->get("modcp_banuser_editusername")."\";");
		}
	}

	// New ban!
	if(!$banuser_username)
	{
		if($mybb->input['uid'])
		{
			$user = get_user($mybb->input['uid']);
			$username = $user['username'];
		}
		else
		{
			$username = htmlspecialchars_uni($mybb->get_input('username'));
		}
		eval("\$banuser_username = \"".$templates->get("modcp_banuser_addusername")."\";");
	}

	// Coming back to this page from an error?
	if($errors)
	{
		$errors = inline_error($errors);
		$banned = array(
			"bantime" => $mybb->get_input('liftafter'),
			"reason" => $mybb->get_input('reason'),
			"gid" => $mybb->get_input('gid', 1)
		);
		$banreason = htmlspecialchars_uni($mybb->get_input('banreason'));
	}

	// Generate the banned times dropdown
	$liftlist = '';
	foreach($bantimes as $time => $title)
	{
		$liftlist .= "<option value=\"{$time}\"";
		if(isset($banned['bantime']) && $banned['bantime'] == $time)
		{
			$liftlist .= " selected=\"selected\"";
		}
		if($time == '---' || !isset($banned['dateline']))
		{
			$liftlist .= ">{$title}</option>\n";
		}
		else
		{
			$thatime = my_date("D, jS M Y @ g:ia", ban_date2timestamp($time, $banned['dateline']));
			$liftlist .= ">{$title} ({$thatime})</option>\n";
		}
	}

	$bangroups = '';
	$groupscache = $cache->read("usergroups");

	foreach($groupscache as $key => $group)
	{
		if($group['isbannedgroup'])
		{
			$selected = "";
			if(isset($banned['gid']) && $banned['gid'] == $group['gid'])
			{
				$selected = " selected=\"selected\"";
			}
			$bangroups .= "<option value=\"{$group['gid']}\"{$selected}>".htmlspecialchars_uni($group['title'])."</option>\n";
		}
	}

	if(!empty($user['uid']))
	{
		$lift_link = "<div class=\"float_right\"><a href=\"modcp.php?action=liftban&amp;uid={$user['uid']}&amp;my_post_key={$mybb->post_code}\">{$lang->lift_ban}</a></div>";
		$uid = $user['uid'];
	}
	else
	{
		$lift_link = '';
		$uid = 0;
	}

	$plugins->run_hooks("modcp_banuser_end");

	eval("\$banuser = \"".$templates->get("modcp_banuser")."\";");
	output_page($banuser);
}

if($mybb->input['action'] == "do_modnotes")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("modcp_do_modnotes_start");

	// Update Moderator Notes cache
	$update_cache = array(
		"modmessage" => $mybb->get_input('modnotes')
	);
	$cache->update("modnotes", $update_cache);

	$plugins->run_hooks("modcp_do_modnotes_end");

	redirect("modcp.php", $lang->redirect_modnotes);
}

if(!$mybb->input['action'])
{
	$query = $db->query("
		SELECT COUNT(aid) AS unapprovedattachments
		FROM  ".TABLE_PREFIX."attachments a
		LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=a.pid)
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
		WHERE a.visible='0' {$tflist}
	");
	$unapproved_attachments = $db->fetch_field($query, "unapprovedattachments");

	if($unapproved_attachments > 0)
	{
		$query = $db->query("
			SELECT t.tid, p.pid, p.uid, t.username, a.filename, a.dateuploaded
			FROM  ".TABLE_PREFIX."attachments a
			LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=a.pid)
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			WHERE a.visible='0' {$tflist}
			ORDER BY a.dateuploaded DESC
			LIMIT 1
		");
		$attachment = $db->fetch_array($query);
		$attachment['date'] = my_date('relative', $attachment['dateuploaded']);
		$attachment['profilelink'] = build_profile_link($attachment['username'], $attachment['uid']);
		$attachment['link'] = get_post_link($attachment['pid'], $attachment['tid']);
		$attachment['filename'] = htmlspecialchars_uni($attachment['filename']);
		$unapproved_attachments = my_number_format($unapproved_attachments);

		eval("\$latest_attachment = \"".$templates->get("modcp_lastattachment")."\";");
	}
	else
	{
		$latest_attachment = "<span style=\"text-align: center;\">{$lang->lastpost_never}</span>";
	}

	$query = $db->query("
		SELECT COUNT(pid) AS unapprovedposts
		FROM  ".TABLE_PREFIX."posts p
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
		WHERE p.visible='0' {$tflist} AND t.firstpost != p.pid
	");
	$unapproved_posts = $db->fetch_field($query, "unapprovedposts");

	if($unapproved_posts > 0)
	{
		$query = $db->query("
			SELECT p.pid, p.tid, p.subject, p.uid, p.username, p.dateline
			FROM  ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			WHERE p.visible='0' {$tflist} AND t.firstpost != p.pid
			ORDER BY p.dateline DESC
			LIMIT 1
		");
		$post = $db->fetch_array($query);
		$post['date'] = my_date('relative', $post['dateline']);
		$post['profilelink'] = build_profile_link($post['username'], $post['uid']);
		$post['link'] = get_post_link($post['pid'], $post['tid']);
		$post['subject'] = $post['fullsubject'] = $parser->parse_badwords($post['subject']);
		if(my_strlen($post['subject']) > 25)
		{
			$post['subject'] = my_substr($post['subject'], 0, 25)."...";
		}
		$post['subject'] = htmlspecialchars_uni($post['subject']);
		$post['fullsubject'] = htmlspecialchars_uni($post['fullsubject']);
		$unapproved_posts = my_number_format($unapproved_posts);

		eval("\$latest_post = \"".$templates->get("modcp_lastpost")."\";");
	}
	else
	{
		$latest_post =  "<span style=\"text-align: center;\">{$lang->lastpost_never}</span>";
	}

	$query = $db->simple_select("threads", "COUNT(tid) AS unapprovedthreads", "visible=0 {$flist}");
	$unapproved_threads = $db->fetch_field($query, "unapprovedthreads");

	if($unapproved_threads > 0)
	{
		$query = $db->simple_select("threads", "tid, subject, uid, username, dateline", "visible=0 {$flist}", array('order_by' =>  'dateline', 'order_dir' => 'DESC', 'limit' => 1));
		$thread = $db->fetch_array($query);
		$thread['date'] = my_date('relative', $thread['dateline']);
		$thread['profilelink'] = build_profile_link($thread['username'], $thread['uid']);
		$thread['link'] = get_thread_link($thread['tid']);
		$thread['subject'] = $thread['fullsubject'] = $parser->parse_badwords($thread['subject']);
		if(my_strlen($thread['subject']) > 25)
		{
			$post['subject'] = my_substr($thread['subject'], 0, 25)."...";
		}
		$thread['subject'] = htmlspecialchars_uni($thread['subject']);
		$thread['fullsubject'] = htmlspecialchars_uni($thread['fullsubject']);
		$unapproved_threads = my_number_format($unapproved_threads);

		eval("\$latest_thread = \"".$templates->get("modcp_lastthread")."\";");
	}
	else
	{
		$latest_thread = "<span style=\"text-align: center;\">{$lang->lastpost_never}</span>";
	}

	$where = '';
	if($tflist)
	{
		$where = "WHERE (t.fid <> 0 {$tflist}) OR (!l.fid)";
	}

	$query = $db->query("
		SELECT l.*, u.username, u.usergroup, u.displaygroup, t.subject AS tsubject, f.name AS fname, p.subject AS psubject
		FROM ".TABLE_PREFIX."moderatorlog l
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=l.uid)
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=l.tid)
		LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=l.fid)
		LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=l.pid)
		{$where}
		ORDER BY l.dateline DESC
		LIMIT 5
	");

	$modlogresults = '';
	while($logitem = $db->fetch_array($query))
	{
		$information = '';
		$logitem['action'] = htmlspecialchars_uni($logitem['action']);
		$log_date = my_date('relative', $logitem['dateline']);
		$trow = alt_trow();
		$username = format_name($logitem['username'], $logitem['usergroup'], $logitem['displaygroup']);
		$logitem['profilelink'] = build_profile_link($username, $logitem['uid']);
		$logitem['ipaddress'] = my_inet_ntop($db->unescape_binary($logitem['ipaddress']));
		if($logitem['tsubject'])
		{
			$information = "<strong>{$lang->thread}</strong> <a href=\"".get_thread_link($logitem['tid'])."\" target=\"_blank\">".htmlspecialchars_uni($logitem['tsubject'])."</a><br />";
		}
		if($logitem['fname'])
		{
			$information .= "<strong>{$lang->forum}</strong> <a href=\"".get_forum_link($logitem['fid'])."\" target=\"_blank\">".htmlspecialchars_uni($logitem['fname'])."</a><br />";
		}
		if($logitem['psubject'])
		{
			$information .= "<strong>{$lang->post}</strong> <a href=\"".get_post_link($logitem['pid'])."#pid{$logitem['pid']}\">".htmlspecialchars_uni($logitem['psubject'])."</a>";
		}

		// Edited a user?
		if(!$logitem['tsubject'] || !$logitem['fname'] || !$logitem['psubject'])
		{
			$data = unserialize($logitem['data']);
			if($data['uid'])
			{
				$information = $lang->sprintf($lang->edited_user_info, htmlspecialchars_uni($data['username']), get_profile_link($data['uid']));
			}
		}

		eval("\$modlogresults .= \"".$templates->get("modcp_modlogs_result")."\";");
	}

	if(!$modlogresults)
	{
		eval("\$modlogresults = \"".$templates->get("modcp_modlogs_nologs")."\";");
	}

	$query = $db->query("
		SELECT b.*, a.username AS adminuser, u.username, (b.lifted-".TIME_NOW.") AS remaining
		FROM ".TABLE_PREFIX."banned b
		LEFT JOIN ".TABLE_PREFIX."users u ON (b.uid=u.uid)
		LEFT JOIN ".TABLE_PREFIX."users a ON (b.admin=a.uid)
		WHERE b.bantime != '---' AND b.bantime != 'perm'
		ORDER BY remaining ASC
		LIMIT 5
	");

	// Get the banned users
	$bannedusers = '';
	while($banned = $db->fetch_array($query))
	{
		$profile_link = build_profile_link($banned['username'], $banned['uid']);

		// Only show the edit & lift links if current user created ban, or is super mod/admin
		$edit_link = '';
		if($mybb->user['uid'] == $banned['admin'] || !$banned['adminuser'] || $mybb->usergroup['issupermod'] == 1 || $mybb->usergroup['cancp'] == 1)
		{
			$edit_link = "<br /><span class=\"smalltext\"><a href=\"modcp.php?action=banuser&amp;uid={$banned['uid']}\">{$lang->edit_ban}</a> | <a href=\"modcp.php?action=liftban&amp;uid={$banned['uid']}&amp;my_post_key={$mybb->post_code}\">{$lang->lift_ban}</a></span>";
		}

		$admin_profile = build_profile_link($banned['adminuser'], $banned['admin']);

		$trow = alt_trow();

		if($banned['reason'])
		{
			$banned['reason'] = htmlspecialchars_uni($parser->parse_badwords($banned['reason']));
			$banned['reason'] = my_wordwrap($banned['reason']);
		}
		else
		{
			$banned['reason'] = $lang->na;
		}

		if($banned['lifted'] == 'perm' || $banned['lifted'] == '' || $banned['bantime'] == 'perm' || $banned['bantime'] == '---')
		{
			$banlength = $lang->permanent;
			$timeremaining = $lang->na;
		}
		else
		{
			$banlength = $bantimes[$banned['bantime']];
			$remaining = $banned['remaining'];

			$timeremaining = nice_time($remaining, array('short' => 1, 'seconds' => false))."";

			if($remaining <= 0)
			{
				$timeremaining = "<span style=\"color: red;\">({$lang->ban_ending_imminently})</span>";
			}
			else if($remaining < 3600)
			{
				$timeremaining = "<span style=\"color: red;\">({$timeremaining} {$lang->ban_remaining})</span>";
			}
			else if($remaining < 86400)
			{
				$timeremaining = "<span style=\"color: maroon;\">({$timeremaining} {$lang->ban_remaining})</span>";
			}
			else if($remaining < 604800)
			{
				$timeremaining = "<span style=\"color: green;\">({$timeremaining} {$lang->ban_remaining})</span>";
			}
			else
			{
				$timeremaining = "({$timeremaining} {$lang->ban_remaining})";
			}
		}

		eval("\$bannedusers .= \"".$templates->get("modcp_banning_ban")."\";");
	}

	if(!$bannedusers)
	{
		eval("\$bannedusers = \"".$templates->get("modcp_banning_nobanned")."\";");
	}

	$modnotes = $cache->read("modnotes");
	$modnotes = htmlspecialchars_uni($modnotes['modmessage']);

	$plugins->run_hooks("modcp_end");

	eval("\$modcp = \"".$templates->get("modcp")."\";");
	output_page($modcp);
}
?>