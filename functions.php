<?php

/**
 * Debug Modal (Envesko) helpers. Auto-loaded for the active plugin.
 */

if (!function_exists('debugModalConfig')) {
    function debugModalConfig()
    {
        global $db;
        static $cfg = null;
        if ($cfg !== null) {
            return $cfg ?: null;
        }
        try {
            $q = $db->query('SELECT * FROM debugmodal_config WHERE id = 1');
            $cfg = $q->count() ? $q->first() : false;
        } catch (Exception $e) {
            $cfg = false;
        }
        return $cfg ?: null;
    }
}

if (!function_exists('debugModalShow')) {
    // Is a section / option enabled? Falls back to $default when there is no config row yet.
    function debugModalShow($key, $default = 1)
    {
        $cfg = debugModalConfig();
        if (!$cfg) {
            return (bool) $default;
        }
        return isset($cfg->$key) ? ((int) $cfg->$key === 1) : (bool) $default;
    }
}
