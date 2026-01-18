#!/bin/bash

has_errors=0
output=$(php -l "/Users/noelsaw/Local Sites/neochrome-timesheets/app/public/wp-content/plugins/Hypercart-Server-Monitor-MKII/src/Services/ScoringService.php")
if [ $? -ne 0 ]; then
    echo "$output"
    has_errors=1
fi
output=$(php -l "/Users/noelsaw/Local Sites/neochrome-timesheets/app/public/wp-content/plugins/Hypercart-Server-Monitor-MKII/src/Domain/FsmStateStore.php")
if [ $? -ne 0 ]; then
    echo "$output"
    has_errors=1
fi
output=$(php -l "/Users/noelsaw/Local Sites/neochrome-timesheets/app/public/wp-content/plugins/Hypercart-Server-Monitor-MKII/src/Plugin.php")
if [ $? -ne 0 ]; then
    echo "$output"
    has_errors=1
fi
output=$(php -l "/Users/noelsaw/Local Sites/neochrome-timesheets/app/public/wp-content/plugins/Hypercart-Server-Monitor-MKII/src/Services/SchedulerService.php")
if [ $? -ne 0 ]; then
    echo "$output"
    has_errors=1
fi
output=$(php -l "/Users/noelsaw/Local Sites/neochrome-timesheets/app/public/wp-content/plugins/Hypercart-Server-Monitor-MKII/autoload.php")
if [ $? -ne 0 ]; then
    echo "$output"
    has_errors=1
fi
output=$(php -l "/Users/noelsaw/Local Sites/neochrome-timesheets/app/public/wp-content/plugins/Hypercart-Server-Monitor-MKII/src/Admin/AdminController.php")
if [ $? -ne 0 ]; then
    echo "$output"
    has_errors=1
fi
output=$(php -l "/Users/noelsaw/Local Sites/neochrome-timesheets/app/public/wp-content/plugins/Hypercart-Server-Monitor-MKII/src/Admin/views/tab-dashboard.php")
if [ $? -ne 0 ]; then
    echo "$output"
    has_errors=1
fi
output=$(php -l "/Users/noelsaw/Local Sites/neochrome-timesheets/app/public/wp-content/plugins/Hypercart-Server-Monitor-MKII/src/Admin/views/tab-debug.php")
if [ $? -ne 0 ]; then
    echo "$output"
    has_errors=1
fi
output=$(php -l "/Users/noelsaw/Local Sites/neochrome-timesheets/app/public/wp-content/plugins/Hypercart-Server-Monitor-MKII/src/Admin/views/tab-email.php")
if [ $? -ne 0 ]; then
    echo "$output"
    has_errors=1
fi
output=$(php -l "/Users/noelsaw/Local Sites/neochrome-timesheets/app/public/wp-content/plugins/Hypercart-Server-Monitor-MKII/src/Admin/views/tab-logs.php")
if [ $? -ne 0 ]; then
    echo "$output"
    has_errors=1
fi
output=$(php -l "/Users/noelsaw/Local Sites/neochrome-timesheets/app/public/wp-content/plugins/Hypercart-Server-Monitor-MKII/src/Admin/views/tab-manual-test.php")
if [ $? -ne 0 ]; then
    echo "$output"
    has_errors=1
fi
output=$(php -l "/Users/noelsaw/Local Sites/neochrome-timesheets/app/public/wp-content/plugins/Hypercart-Server-Monitor-MKII/src/Helpers/LockHelper.php")
if [ $? -ne 0 ]; then
    echo "$output"
    has_errors=1
fi
output=$(php -l "/Users/noelsaw/Local Sites/neochrome-timesheets/app/public/wp-content/plugins/Hypercart-Server-Monitor-MKII/src/Metrics/BenchmarkCollector.php")
if [ $? -ne 0 ]; then
    echo "$output"
    has_errors=1
fi
output=$(php -l "/Users/noelsaw/Local Sites/neochrome-timesheets/app/public/wp-content/plugins/Hypercart-Server-Monitor-MKII/src/Metrics/CpuLoadCollector.php")
if [ $? -ne 0 ]; then
    echo "$output"
    has_errors=1
fi
output=$(php -l "/Users/noelsaw/Local Sites/neochrome-timesheets/app/public/wp-content/plugins/Hypercart-Server-Monitor-MKII/src/Metrics/DiskCollector.php")
if [ $? -ne 0 ]; then
    echo "$output"
    has_errors=1
fi
output=$(php -l "/Users/noelsaw/Local Sites/neochrome-timesheets/app/public/wp-content/plugins/Hypercart-Server-Monitor-MKII/src/Metrics/MemoryCollector.php")
if [ $? -ne 0 ]; then
    echo "$output"
    has_errors=1
fi
output=$(php -l "/Users/noelsaw/Local Sites/neochrome-timesheets/app/public/wp-content/plugins/Hypercart-Server-Monitor-MKII/src/Metrics/MetricsCollectorInterface.php")
if [ $? -ne 0 ]; then
    echo "$output"
    has_errors=1
fi
output=$(php -l "/Users/noelsaw/Local Sites/neochrome-timesheets/app/public/wp-content/plugins/Hypercart-Server-Monitor-MKII/src/Persistence/HealthRepository.php")
if [ $? -ne 0 ]; then
    echo "$output"
    has_errors=1
fi
output=$(php -l "/Users/noelsaw/Local Sites/neochrome-timesheets/app/public/wp-content/plugins/Hypercart-Server-Monitor-MKII/src/Services/EmailService.php")
if [ $? -ne 0 ]; then
    echo "$output"
    has_errors=1
fi
output=$(php -l "/Users/noelsaw/Local Sites/neochrome-timesheets/app/public/wp-content/plugins/Hypercart-Server-Monitor-MKII/wp-server-performance-monitor.php")
if [ $? -ne 0 ]; then
    echo "$output"
    has_errors=1
fi

if [ $has_errors -ne 0 ]; then
    echo "There were syntax errors."
    exit 1
fi

echo "No syntax errors found."
