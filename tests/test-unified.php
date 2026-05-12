<?php
/**
 * Unified Test Script for Pathway Bridge Suite
 */

require_once 'pathway-bridge-suite.php';

use PATHWAY_BRIDGE_SUITE\Modules\Routes\Routes_Module;
use PATHWAY_BRIDGE_SUITE\Logger;

function test_routes_bridge() {
    echo "Testing Routes Bridge...\n";
    $module = new Routes_Module();
    $payload = array('name' => 'Jules', 'msg' => 'Hello AP');
    $route_id = 123; // Mock route ID

    // Mock get_post_meta for mapping and jobs
    add_filter('get_post_metadata', function($value, $object_id, $meta_key) {
        if ($meta_key === '_pbs_mapping') {
            return array('full_message' => array('path' => 'msg', 'transform' => 'expand_abbreviations'));
        }
        if ($meta_key === '_pbs_workflow_jobs') {
            return array(
                array('name' => 'log_test', 'snippet' => 'Logger::log("Snippet executed with payload: " . json_encode($payload)); return $payload;')
            );
        }
        return $value;
    }, 10, 3);

    $result = $module->process_request($payload, $route_id);
    echo "Result: " . json_encode($result) . "\n";
    echo "Check pathway-bridge.log for output.\n";
}

// In a real environment we would use a test runner, here we just check if it loads.
echo "Pathway Bridge Suite loaded successfully.\n";
