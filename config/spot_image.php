<?php defined('SYSPATH') or die('No direct access allowed.');

return array
(
// Weather to use cache or not
'cache' => TRUE,

// Folders to look when searching for files
'folders' => array ("repos/images/", "../repos/images/", "repos/temp/", "../repos/temp/"),

// Route prefix (same as in init.php)
'prefix' => "repos",

// Use database
'use_database' => TRUE,

// Database connection
'image_sizes_table_db' => 'default',

// MySQL table
'image_sizes_table' => '_image_sizes',

// Cache directory
'cache_directory' => 'repos/images/cache/',

// Cache maxage in seconds
'cache_maxage' => 30 * (24 * 60 * 60), //30 days

// Cache maximum number of files
'cache_maxfiles' => 1000, 

// Cache maximum folder size
'cache_maxsize' => 50 * (1000 * pow (2, 10)), //50 MBytes

'browser_cache_expire' => 7 * 24 * 60 * 60, //in seconds

// Allow enlargment of images
'allow_enlarge' => FALSE,
);
