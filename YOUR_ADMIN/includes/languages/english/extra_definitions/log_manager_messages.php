<?php
// -----
// Part of the Log Manager plugin, created by lat9 (lat9@vinosdefrutastropicales.com)
// Copyright (c) 2017, Vinos de Frutas Tropicales.
//
// -----
// This message, issued by the plugin's initialization script, is issued if one or more .log file has been removed:
//
// %1$u ... The number of files removed
// %2$s ... The date/time that was used during the check.
//
define ('LOG_MANAGER_FILES_MESSAGE_FORMAT', 'Removed %1$u .log file(s) from the /logs directory that were dated prior to %2$s.');