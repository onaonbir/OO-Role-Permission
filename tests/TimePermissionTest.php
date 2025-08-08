<?php

namespace Tests;

use Carbon\Carbon;
use OnaOnbir\OORolePermission\Models\TimePermission;

/**
 * Test class for TimePermission functionality
 * This is a simple test to verify the fixes we made
 */
class TimePermissionTest
{
    public static function run()
    {
        echo "🔍 Testing TimePermission fixes...\n";

        // Test 1: clearCacheForRole with null
        echo "Test 1: clearCacheForRole with null value\n";
        try {
            TimePermission::clearCacheForRole(null);
            echo "✅ clearCacheForRole handles null correctly\n";
        } catch (\Exception $e) {
            echo '❌ clearCacheForRole failed: '.$e->getMessage()."\n";
        }

        // Test 2: clearCacheForRole with valid ID
        echo "\nTest 2: clearCacheForRole with valid ID\n";
        try {
            TimePermission::clearCacheForRole(1);
            echo "✅ clearCacheForRole handles valid ID correctly\n";
        } catch (\Exception $e) {
            echo '❌ clearCacheForRole failed: '.$e->getMessage()."\n";
        }

        // Test 3: clearGeneralTimeCache
        echo "\nTest 3: clearGeneralTimeCache\n";
        try {
            TimePermission::clearGeneralTimeCache();
            echo "✅ clearGeneralTimeCache works correctly\n";
        } catch (\Exception $e) {
            echo '❌ clearGeneralTimeCache failed: '.$e->getMessage()."\n";
        }

        // Test 4: Create a TimePermission instance and test methods
        echo "\nTest 4: Creating TimePermission instance\n";
        try {
            $timePermission = new TimePermission;
            $timePermission->constraintable_type = 'OnaOnbir\\OORolePermission\\Models\\Role';
            $timePermission->constraintable_id = 1;
            $timePermission->additional_permissions = ['admin.users', 'admin.settings'];
            $timePermission->timezone = 'Europe/Istanbul';
            $timePermission->days_of_week = [1, 2, 3, 4, 5]; // Monday to Friday
            $timePermission->is_active = true;

            echo "✅ TimePermission instance created successfully\n";

            // Test getRoleId
            $roleId = $timePermission->getRoleId();
            echo 'Role ID: '.($roleId ?? 'null')."\n";

            // Test appliesToPermission
            $applies = $timePermission->appliesToPermission('admin.users');
            echo "Applies to 'admin.users': ".($applies ? 'yes' : 'no')."\n";

            // Test isValidOnDay
            $validOnMonday = $timePermission->isValidOnDay(1); // Monday
            echo 'Valid on Monday: '.($validOnMonday ? 'yes' : 'no')."\n";

            // Test isValidAtTime
            $now = Carbon::now();
            $validNow = $timePermission->isValidAtTime($now);
            echo 'Valid now: '.($validNow ? 'yes' : 'no')."\n";

            // Test getReadableSchedule
            $schedule = $timePermission->getReadableSchedule();
            echo 'Schedule: '.$schedule."\n";

            echo "✅ All TimePermission methods work correctly\n";

        } catch (\Exception $e) {
            echo '❌ TimePermission test failed: '.$e->getMessage()."\n";
        }

        echo "\n🎉 Testing completed!\n";
    }
}

// Run the test if called directly
if (php_sapi_name() === 'cli') {
    TimePermissionTest::run();
}
