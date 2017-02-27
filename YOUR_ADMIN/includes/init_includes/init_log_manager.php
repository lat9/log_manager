<?php
// -----
// Part of the Log Manager plugin, created by lat9 (lat9@vinosdefrutastropicales.com)
// Copyright (c) 2017, Vinos de Frutas Tropicales.
//
if (!defined ('LOG_MANAGER_KEEP_DAYS')) {
    $next_sort = $db->Execute (
        "SELECT MAX(sort_order) as max_sort 
           FROM " . TABLE_CONFIGURATION . " 
          WHERE configuration_group_id = 10", 
         false, 
         false, 
         0, 
         true
    );
    $sort_order = $next_sort->fields['max_sort'] + 1;
    $sort_order2 = $sort_order + 1;
    $db->Execute (
        "INSERT INTO " . TABLE_CONFIGURATION . "
            ( configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function, set_function )
         VALUES
            ( 'Log Manager: Days to Keep', 'LOG_MANAGER_KEEP_DAYS', '0', 'Enter the maximum number of <em>days</em> to keep any file with a <code>.log</code> file extension in your store\'s <b>logs</b> directory.<br /><br />If the value you enter is non-zero, then any files created prior to that relative date will be <b>permanently removed</b> from your store\'s file-system.<br />', 10, $sort_order, now(), NULL, NULL),
            
            ( 'Log Manager: Logs to Keep', 'LOG_MANAGER_KEEP_THESE', 'zcInstall', 'Enter a comma-separated list of name-prefixes for any log-files that you want to <b><i>keep</i></b>, regardless of their age.<br /><br />The values you enter are <em>case-sensitive</em>, i.e. <em>zcInstall</em> is different than <em>zcinstall</em>.  The default setting (<code>zcInstall</code>) results in any file matching <code>/logs/zcInstall*.log</code> being kept regardless of its creation date.<br />', 10, $sort_order2, now(), NULL, NULL)"
    );
} else {
    if (((int)LOG_MANAGER_KEEP_DAYS) > 0) {
        $match_string = '';
        if (LOG_MANAGER_KEEP_THESE != '') {
            $logs_to_keep = explode (',', str_replace (' ', '', LOG_MANAGER_KEEP_THESE));
            if (count ($logs_to_keep) > 1) {
                $match_string = '/^(' . implode ('|', $logs_to_keep) . ').*$/';
            } else {
                $match_string = '/^' . $logs_to_keep[0] . '.*$/';
            }
        }
        $keep_until = strtotime ('-' . LOG_MANAGER_KEEP_DAYS . ' day');
        $keep_until_date = date (DATE_FORMAT . ' H:m:i', $keep_until);
        $files_removed = 0;
        if ($dir = dir (DIR_FS_LOGS)) {
            while (($current_file = $dir->read()) !== false) {
                if (strpos ($current_file, '.log') !== false && $match_string != '' && !preg_match ($match_string, $current_file)) {
                    $modified = filemtime (DIR_FS_LOGS . DIRECTORY_SEPARATOR . $current_file);
                    if ($modified !== false && $modified < $keep_until) {
                        // -----
                        // I'm not a big fan of inhibiting error-reports, but for this case it's possible that multiple
                        // admins have "hit" the site concurrently and are all causing this script to run.
                        //
                        @unlink (DIR_FS_LOGS . DIRECTORY_SEPARATOR . $current_file);
                        $files_removed++;
                    }
                }
            }
            $dir->close();
        }
        if ($files_removed != 0) {
            $messageStack->add (sprintf (LOG_MANAGER_FILES_MESSAGE_FORMAT, $files_removed, $keep_until_date), 'success');
        }
    }
}