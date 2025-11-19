<?php
/**
 * Legacy wrapper for older URLs hitting index.php?action=dts_event_save.
 * This file now simply includes the main save action to avoid code duplication.
 */
require_once __DIR__ . '/dts_ev_save.php';
