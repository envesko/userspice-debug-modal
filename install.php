<?php

require_once 'init.php';
if (in_array($user->data()->id, $master_account)) {
    $db = DB::getInstance();
    include 'plugin_info.php';

    $check = $db->query('SELECT * FROM us_plugins WHERE plugin = ?', [$plugin_name])->count();
    if ($check > 0) {
        err($plugin_name.' has already been installed!');
    } else {
        $fields = [
            'plugin' => $plugin_name,
            'status' => 'installed',
        ];
        $db->insert('us_plugins', $fields);
        if (!$db->error()) {
            err($plugin_name.' installed');
            logger($user->data()->id, 'USPlugins', $plugin_name.' installed');
        } else {
            err($plugin_name.' was not installed');
            logger($user->data()->id, 'USPlugins', 'Failed to to install plugin, Error: '.$db->errorString());
        }
    }

    // Config table: which sections show, auto-open default, and an optional admin-only gate.
    $db->query('CREATE TABLE IF NOT EXISTS debugmodal_config (
        id INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
        ShowSession TINYINT(1) NOT NULL DEFAULT 1,
        ShowPost TINYINT(1) NOT NULL DEFAULT 1,
        ShowGet TINYINT(1) NOT NULL DEFAULT 0,
        ShowCookies TINYINT(1) NOT NULL DEFAULT 0,
        ShowServer TINYINT(1) NOT NULL DEFAULT 0,
        ShowUser TINYINT(1) NOT NULL DEFAULT 1,
        ShowSystem TINYINT(1) NOT NULL DEFAULT 1,
        AutoOpen TINYINT(1) NOT NULL DEFAULT 0,
        RequireAdmin TINYINT(1) NOT NULL DEFAULT 0
    )');
    if ($db->query('SELECT id FROM debugmodal_config WHERE id = 1')->count() == 0) {
        $db->insert('debugmodal_config', ['id' => 1]);
    }

    $hooks = [];
    registerHooks($hooks, $plugin_name);
}
