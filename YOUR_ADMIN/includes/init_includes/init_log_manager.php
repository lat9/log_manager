<?php
// -----
// Part of the Log Manager plugin, created by lat9 (lat9@vinosdefrutastropicales.com)
// Copyright (c) 2017-2025, Vinos de Frutas Tropicales.
//
// -----
// If the plugin is enabled and current session is associated with a logged-in admin and the plugin's processing hasn't been run
// yet for this session ... manage those logs!
//
if (isset($_SESSION['admin_id']) && !isset($_SESSION['log_managed'])) {
    // -----
    // If the plugin's configuration settings (in Configuration->Logging) haven't been set, set them now!
    //
    if (!defined('LOG_MANAGER_KEEP_DAYS')) {
        $next_sort = $db->ExecuteNoCache(
            "SELECT MAX(sort_order) as max_sort
               FROM " . TABLE_CONFIGURATION . "
              WHERE configuration_group_id = 10"
        );
        $sort_order = $next_sort->fields['max_sort'] + 1;
        $sort_order2 = $sort_order + 1;
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function, set_function)
             VALUES
                ('Log Manager: Days to Keep', 'LOG_MANAGER_KEEP_DAYS', '0', 'Enter the maximum number of <em>days</em> to keep any file with a <code>.log</code> file extension in your store\'s <b>logs</b> directory.<br><br>If the value you enter is non-zero, then any files created prior to that relative date will be <b>permanently removed</b> from your store\'s file-system.<br>', 10, $sort_order, now(), NULL, NULL),
                
                ('Log Manager: Logs to Keep', 'LOG_MANAGER_KEEP_THESE', 'zcInstall', 'Enter a comma-separated list of name-prefixes for any log-files that you want to <b><i>keep</i></b>, regardless of their age.<br><br>The values you enter are <em>case-sensitive</em>, i.e. <em>zcInstall</em> is different than <em>zcinstall</em>.  The default setting (<code>zcInstall</code>) results in any file matching <code>/logs/zcInstall*.log</code> being kept regardless of its creation date.<br>', 10, $sort_order2, now(), NULL, NULL)"
        );
        define('LOG_MANAGER_KEEP_DAYS', '0');
        define('LOG_MANAGER_KEEP_THESE', 'zcInstall');
    }

    if (((int)LOG_MANAGER_KEEP_DAYS) > 0) {
        // -----
        // Build up a string-to-match (via preg_match) for the .log files that should be "kept".  That string
        // contains the log-file name prefixes.
        //
        $match_string = '';
        if (LOG_MANAGER_KEEP_THESE !== '') {
            $logs_to_keep = explode(',', str_replace(' ', '', LOG_MANAGER_KEEP_THESE));
            if (count($logs_to_keep) > 1) {
                $match_string = '/^(' . implode('|', $logs_to_keep) . ').*$/';
            } else {
                $match_string = '/^' . $logs_to_keep[0] . '.*$/';
            }
        }

        // -----
        // Determine the keep-until date (some number of days **prior to** today).
        //
        $keep_until = strtotime('-' . LOG_MANAGER_KEEP_DAYS . ' day');
        $keep_until_date = date(DATE_FORMAT . ' H:m:i', $keep_until);

        // -----
        // Loop through all files present in the /logs and, for zc158 and later, the
        // /app/storage/logs, sub-directories as well as any additional directories
        // that might be supplied in an optional constant definition.
        //
        $log_manager_dirs = [
            DIR_FS_LOGS,
        ];
        if (is_dir(DIR_FS_CATALOG . 'app/storage/logs')) {
            $log_manager_dirs[] = DIR_FS_CATALOG . 'app/storage/logs';
        }

        // -----
        // To remove .log files from other directories, too, use an /admin/extra_datafiles file to
        // define the following constant.  The constant's definition would look like the following
        // to also remove log-files from /includes/modules/payment/paypay/logs and /logs/edit_orders:
        //
        // define('LOG_MANAGER_EXTRA_DIRECTORIES', DIR_FS_CATALOG . 'includes/modules/payment/paypay/logs' . ';' . DIR_FS_LOGS . '/edit_orders');
        //
        if (defined('LOG_MANAGER_EXTRA_DIRECTORIES')) {
            $log_manager_extra_dirs = explode(';', str_replace(' ', '', LOG_MANAGER_EXTRA_DIRECTORIES));
            foreach ($log_manager_extra_dirs as $log_manager_extra) {
                if (is_dir($log_manager_extra)) {
                    $log_manager_dirs[] = $log_manager_extra;
                } else {
                    trigger_error('Unknown directory ($log_manager_extra) identified in LOG_MANAGER_EXTRA_DIRECTORIES.', E_USER_NOTICE);
                }
            }
            unset($log_manager_extra_dirs, $log_manager_extra);
        }

        $files_removed = 0;
        foreach ($log_manager_dirs as $log_manager_dir) {
            if ($dir = dir($log_manager_dir)) {
                while (($current_file = $dir->read()) !== false) {
                    // -----
                    // ... looking for a ".log" file with a name that isn't in the "keep-these" list ...
                    //
                    if (strpos($current_file, '.log') !== false && $match_string !== '' && preg_match($match_string, $current_file) !== 1) {
                        // -----
                        // ... that was last modified prior to the keep-until date ...
                        //
                        $current_file = $log_manager_dir . DIRECTORY_SEPARATOR . $current_file;
                        $modified = is_file($current_file) ? filemtime($current_file) : false;
                        if ($modified !== false && $modified < $keep_until) {
                            // -----
                            // ... remove it.
                            //
                            unlink($current_file);
                            $files_removed++;
                        }
                    }
                }
                $dir->close();
            }
        }

        // -----
        // If one or more .log files was removed, let the admin know (via message) and log the removal action.
        //
        if ($files_removed !== 0) {
            $log_directories = implode(', ', $log_manager_dirs);
            $logMessage = sprintf(LOG_MANAGER_FILES_MESSAGE_FORMAT, $files_removed, '.log', $log_directories, $keep_until_date);
            $messageStack->add($logMessage, 'success');
            error_log(date(PHP_DATE_TIME_FORMAT) . ', ' . $_SESSION['admin_id'] . ": $logMessage" . PHP_EOL, 3, DIR_FS_LOGS . '/log_manager_removal.log');
        }
    }

    // -----
    // Note that the plugin's processing has been run for the current admin session.
    //
    $_SESSION['log_managed'] = true;
}
