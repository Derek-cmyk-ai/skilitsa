<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('skilitsa_dogmatcher_settings');
delete_option('skilitsa_dogmatcher_config');
delete_option('skilitsa_dogmatcher_sync_meta');
