<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

require "./global.php";

// Load language packs for this section
global $lang;
$lang->load('dbtools');

checkadminpermissions("canrundbtools");
logadmin();

addacpnav($lang->nav_db_tools, 'dbtools.php?'.SID);
switch($mybb->input['action'])
{
	case 'existing':
		addacpnav($lang->existing_backups);
		break;
	case 'backup':
		addacpnav($lang->backup_database);
		break;
}

if($mybb->input['action'] == 'do_delete')
{
	if($mybb->input['deletesubmit'])
	{
		$file = basename($mybb->input['file']);
		
		if(file_exists(MYBB_ADMIN_DIR.'backups/'.$file))
		{
			$delete = @unlink(MYBB_ADMIN_DIR.'backups/'.$file);
			
			if($delete)
			{
				cpredirect('dbtools.php?'.SID.'&action=existing', $lang->backup_deleted);
			}
			else
			{
				cperror($lang->error_delete_fail);
			}
		}
	}
	else
	{
		$mybb->input['action'] = 'existing';
	}
}

if($mybb->input['action'] == 'do_backup')
{
	if(!is_array($mybb->input['tables']))
	{
		cperror($lang->error_no_tables_selected);
	}
	
	if($mybb->input['write'] == 'disk')
	{
		$file = MYBB_ADMIN_DIR.'backups/backup_'.time();
		
		if($mybb->input['type'] == 'gzip')
		{
			if(!function_exists('gzopen')) // check zlib-ness
			{
				cperror($lang->error_no_zlib);
			}
			
			$fp = gzopen($file.'.gz', 'w9');
		}
		else
		{
			$fp = fopen($file.'.sql', 'w');
		}
	}
	else
	{
		$file = 'backup_'.time();
		if($mybb->input['type'] == 'gzip')
		{
			// Send headers for gzip file (do ob_start too)
			header('Content-Encoding: x-gzip');
			header('Content-Type: application/x-gzip');
			header('Content-Disposition: attachment; filename="'.$file.'.gz"');
		}
		else
		{
			// Send standard headers for .sql
			header('Content-Type: text/x-sql');
			header('Content-Disposition: attachment; filename="'.$file.'.sql"');
		}
	}
	
	$time = date('dS F Y \a\t H:i', time());
	$header = "-- MyBB Database Backup\n-- Generated: ".$time."\n---------------------------------------\n\n";
	
	if($mybb->input['write'] == 'disk')
	{
		if($mybb->input['type'] == 'gzip')
		{
			gzwrite($fp, $header, strlen($header));
		}
		else
		{
			fputs($fp, $header, strlen($header));
		}	
	}
	else
	{
		if($mybb->input['type'] == 'gzip')
		{
			echo gzencode($header);
		}
		else
		{
			echo $header;
		}
	}
	
	foreach($mybb->input['tables'] as $table)
	{
		$field_list = array();
		$query = $db->query("SHOW FIELDS FROM ".$table);
		while($row = $db->fetch_array($query))
		{
			$field_list[] = $row['Field'];
		}
		$fields = implode(",", $field_list);
		if($mybb->input['contents'] != 'data')
		{
			$structure = $db->show_create_table($table)."\n";
			if($mybb->input['write'] == 'disk')
			{
				if($type == 'gzip')
				{
					gzwrite($fp, $structure, strlen($structure));
				}
				else
				{
					fputs($fp, $structure, strlen($structure));
				}	
			}
			else
			{
				if($mybb->input['type'] == 'gzip')
				{
					echo gzencode($structure);
				}
				else
				{
					echo $structure;
				}
			}
		}
		if($mybb->input['contents'] != 'structure')
		{
			$query = $db->simple_select($table, "*");
			while($row = $db->fetch_array($query))
			{
				$insert = "INSERT INTO {$table} ($fields) VALUES (";
				$comma = '';
				foreach($field_list as $field)
				{
					if(!isset($row[$field]))
					{
						$insert .= $comma.'NULL';
					}
					else
					{
						$insert .= $comma."'".$db->escape_string($row[$field])."'";
					}
					$comma = ',';
				}
				$insert .= ")\n";
				if($mybb->input['write'] == 'disk')
				{
					if($type == 'gzip')
					{
						gzwrite($fp, $insert, strlen($insert));
					}
					else
					{
						fputs($fp, $insert, strlen($insert));
					}	
				}
				else
				{
					if($mybb->input['type'] == 'gzip')
					{
						echo gzencode($insert);
					}
					else
					{
						echo $insert;
					}
				}
			}
		}
	}
	
	if($mybb->input['write'] == 'disk')
	{
		if($mybb->input['type'] == 'gzip')
		{
			gzclose($fp);
		}
		else
		{
			fclose($fp);
		}
		
		if($mybb->input['type'] == 'gzip')
		{
			$ext = '.gz';
		}
		else
		{
			$ext = '.sql';
		}
		
		$file_from_admindir = MYBB_ADMIN_DIR.'backups/'.$file.$ext;
		$lang->backup_complete = sprintf($lang->backup_complete, $file.$ext, $file_from_admindir);
		cpmessage($lang->backup_complete);
	}
	else
	{
		if($mybb->input['type'] == 'gzip')
		{
			// End ob
			// Compress
			// Echo compressed
		}
	}
}

if($mybb->input['action'] == 'existing')
{
	cpheader();
	starttable();
	tableheader($lang->existing_backups, 'existing_backups', 5);
	
	$backups = array();
	$dir = MYBB_ADMIN_DIR.'backups/';
	$handle = opendir($dir);
	while(($file = readdir($handle)) !== false)
	{
		if(filetype(MYBB_ADMIN_DIR.'backups/'.$file) == 'file')
		{
			$ext = explode('.', basename($file));
			
			if($ext[1] == 'gz' || $ext[1] == 'sql')
			{
				$time = my_substr($ext[0], -10, 10);
				$backups[$time] = $file;
			}
		}
	}
	
	$keys = array_keys($backups);
	$count = count($backups);
	
	if($count != 0)
	{
		makelabelcode($lang->restore_database_desc, '', 5);
		echo "<tr>\n";
		echo "<td class=\"subheader\">".$lang->file_name."</td>\n";
		echo "<td class=\"subheader\" align=\"center\">".$lang->file_size."</td>\n";
		echo "<td class=\"subheader\" align=\"center\">".$lang->file_type."</td>\n";
		echo "<td class=\"subheader\" align=\"center\">".$lang->creation_date."</td>\n";
		echo "<td class=\"subheader\" align=\"center\">".$lang->file_delete."</td>\n";
		echo "</tr>\n";

		$dir = './backups/';
		
		foreach($keys as $key)
		{
			$file = explode('.', $backups[$key]);
			$filename = $file[0];
			$type = $file[1];
			$file = $dir.$filename.'.'.$type;
			$delete_link = "<a href=\"dbtools.php?".SID."&action=delete&backup=".$filename.".".$type."\">[ ".$lang->delete." ]</a>";
			$bgcolor = getaltbg();
			echo "<tr>\n";
			echo "<td class=\"$bgcolor\"><a href=\"".$file."\">".$filename."</a></td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\">".filesize($file)."</td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\">".strtoupper($type)."</td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\">".date('jS M Y H:i', $key)."</td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\">".$delete_link."</td>\n";
			echo "</tr>\n";
		}
	}
	else
	{
		makelabelcode($lang->no_existing_backups);
	}
	
	endtable();
	cpfooter();
}

if($mybb->input['action'] == 'delete')
{
	if(!$mybb->input['backup'])
	{
		$lang->error_no_backup_specified = sprintf($lang->error_no_backup_specified, $lang->deletion);
		cperror($lang->error_no_backup_specified);
	}
	
	cpheader();
	startform('dbtools.php', '', 'do_delete');
	makehiddencode('file', $mybb->input['backup']);
	starttable();
	tableheader($lang->confirm_delete);
	$yes_button = makebuttoncode('deletesubmit', $lang->yes);
	$no_button = makebuttoncode('no', $lang->no);
	makelabelcode('<div align="center">'.$lang->confirm_delete_text.'<br /><br />'.$yes_button.$no_button.'</div>');
	endtable();
	endform();
	cpfooter();
}

if($mybb->input['action'] == 'backup' || $mybb->input['action'] == '')
{
	echo "<script type=\"text/javascript\" language=\"Javascript\">
		function changeSelection(action, prefix)
		{
			var select_box = document.getElementById('table_select');
			
			for(var i = 0; i < select_box.length; i++)
			{
				if(action == 'select')
				{
					document.table_selection.table_select[i].selected = true;
				}
				else if(action == 'deselect')
				{
					document.table_selection.table_select[i].selected = false;
				}
				else if(action == 'forum' && prefix != 0)
				{
					var row = document.table_selection.table_select[i].value;
					var subString = row.substring(prefix.length, 0);
					if(subString == prefix)
					{
						document.table_selection.table_select[i].selected = true;
					}
				}
			}
		}
		</script>";
	cpheader();

	// Check if file is writable, before allowing submission
	if(!is_writable(MYBB_ADMIN_DIR."/backups"))
	{
		$lang->update_button = '';
		makewarning($lang->note_cannot_write_backup);
		$cannot_write = true;
	}
	startform('dbtools.php', 'table_selection', 'do_backup');
	starttable();
	tableheader($lang->backup_database);
	tablesubheader($lang->table_selection);
	$bgcolor = getaltbg();
	echo "<tr>\n";
	echo "<td class=\"$bgcolor\" valign=\"top\">".$lang->table_selection_desc."<br /><br /><a href=\"javascript:changeSelection('select', 0);\">".$lang->select_all."</a><br /><a href=\"javascript:changeSelection('deselect', 0);\">".$lang->deselect_all."</a><br /><a href=\"javascript:changeSelection('forum', '".TABLE_PREFIX."');\">".$lang->select_forum_tables."</a></td>\n";
	echo "<td class=\"$bgcolor\">\n";
	echo "<select id=\"table_select\" name=\"tables[]\" multiple=\"multiple\">\n";
	$table_list = $db->list_tables($config['database']);
	foreach($table_list as $id => $table_name)
	{
		echo "<option value=\"".$table_name."\">".$table_name."</option>\n";
	}
	echo "</select>\n";
	echo "</td>\n";
	echo "</tr>\n";
	tablesubheader($lang->backup_options);
	$bgcolor = getaltbg();
	echo "<tr>\n";
	echo "<td class=\"$bgcolor\">".$lang->export_file_type."</td>\n";
	echo "<td class=\"$bgcolor\">\n";
	if(function_exists("gzwrite") && function_exists("gzencode"))
	{
		echo "<label><input type=\"radio\" name=\"type\" value=\"gzip\" checked=\"checked\" /> ".$lang->gzip_compressed."</label><br />\n";
		echo "<label><input type=\"radio\" name=\"type\" value=\"text\" /> ".$lang->plain_text."</label>\n";
	}
	else
	{
		echo "<label><input type=\"radio\" name=\"type\" value=\"text\" checked=\"checked\" /> ".$lang->plain_text."</label>\n";		
	}
	echo "</td>\n";
	echo "</tr>\n";
	$bgcolor = getaltbg();
	echo "<tr>\n";
	echo "<td class=\"$bgcolor\">".$lang->download_save."</td>\n";
	echo "<td class=\"$bgcolor\">\n";
	echo "<label><input type=\"radio\" name=\"write\" value=\"disk\" ".($cannot_write?"disabled=\"disabled\"":"")." /> ".$lang->save_backup_directory."</label><br />\n";
	echo "<label><input type=\"radio\" name=\"write\" value=\"download\" checked=\"checked\" /> ".$lang->download."</label>\n";
	echo "</td>\n";
	echo "</tr>\n";
	$bgcolor = getaltbg();
	echo "<tr>\n";
	echo "<td class=\"$bgcolor\">".$lang->contents."</td>\n";
	echo "<td class=\"$bgcolor\">\n";
	echo "<label><input type=\"radio\" name=\"contents\" value=\"both\" checked=\"checked\" /> ".$lang->structure_data."</label><br />\n";
	echo "<label><input type=\"radio\" name=\"contents\" value=\"structure\" /> ".$lang->structure_only."</label><br />\n";
	echo "<label><input type=\"radio\" name=\"contents\" value=\"data\" /> ".$lang->data_only."</label>\n";
	echo "</td>\n";
	echo "</tr>\n";	
	makeyesnocode($lang->analyse_optimise, 'analyse');
	endtable();
	endform($lang->perform_backup);
	cpfooter();
}
?>