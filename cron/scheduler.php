<?php

/**
 * Railway Scheduler Worker
 * 
 * This script runs continuously and executes cron jobs on schedule.
 * Railway doesn't support traditional cron, so this worker handles
 * all scheduled tasks internally.
 * 
 * Usage: php cron/scheduler.php
 * 
 * This should be run as a separate Railway service (see railway.toml)
 */

// Prevent script timeout
set_time_limit(0);
ignore_user_abort(true);

// Load application
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/bootstrap.php';

use App\Services\TradingBotService;
use App\Services\SignalService;

// Scheduler configuration
define('SCHEDULER_INTERVAL', 10); // Check every 10 seconds
define('TRADING_LOOP_INTERVAL', 60); // Run every 60 seconds
define('SIGNAL_PROCESSOR_INTERVAL', 60); // Run every 60 seconds
define('CONTRACT_MONITOR_INTERVAL', 15); // Run every 15 seconds

// Track last execution times
$lastTradingLoop = 0;
$lastSignalProcessor = 0;
$lastContractMonitor = 0;

// Log scheduler start
error_log("Railway Scheduler: Started at " . date('Y-m-d H:i:s'));

/**
 * Execute trading loop
 */
function executeTradingLoop() {
    global $lastTradingLoop;
    
    try {
        $tradingBot = TradingBotService::getInstance();
        
        // Process trading loop for all active users
        $tradingBot->processTradingLoop();
        
        // Cleanup stale sessions
        $tradingBot->cleanupStaleSessions();
        
        // Perform health check
        $tradingBot->performHealthCheck();
        
        $lastTradingLoop = time();
        error_log("Trading loop executed successfully at " . date('Y-m-d H:i:s'));
        
    } catch (Exception $e) {
        error_log("Trading loop error: " . $e->getMessage());
    }
}

/**
 * Execute signal processor
 */
function executeSignalProcessor() {
    global $lastSignalProcessor;
    
    try {
        $signalService = SignalService::getInstance();
        
        // Process unprocessed signals (up to 10 at a time)
        $result = $signalService->processUnprocessedSignals(10);
        
        if ($result['processed'] > 0) {
            error_log("Signal processor: Processed {$result['processed']} signals");
        }
        
        // Cleanup old signals (older than 30 days) - run once per day at midnight
        $currentHour = (int)date('H');
        $currentMinute = (int)date('i');
        
        if ($currentHour === 0 && $currentMinute === 0) {
            $deleted = $signalService->cleanupOldSignals(30);
            if ($deleted > 0) {
                error_log("Signal processor: Cleaned up {$deleted} old signals");
            }
        }
        
        $lastSignalProcessor = time();
        
    } catch (Exception $e) {
        error_log("Signal processor error: " . $e->getMessage());
    }
}

/**
 * Execute contract monitor
 */
function executeContractMonitor() {
    global $lastContractMonitor;
    
    try {
        $tradingBot = TradingBotService::getInstance();
        
        // Process contract results
        $tradingBot->processContractResults();
        
        $lastContractMonitor = time();
        
    } catch (Exception $e) {
        error_log("Contract monitor error: " . $e->getMessage());
    }
}

// Main scheduler loop
while (true) {
    $currentTime = time();
    
    // Execute trading loop (every 60 seconds)
    if ($currentTime - $lastTradingLoop >= TRADING_LOOP_INTERVAL) {
        executeTradingLoop();
    }
    
    // Execute signal processor (every 60 seconds)
    if ($currentTime - $lastSignalProcessor >= SIGNAL_PROCESSOR_INTERVAL) {
        executeSignalProcessor();
    }
    
    // Execute contract monitor (every 15 seconds)
    if ($currentTime - $lastContractMonitor >= CONTRACT_MONITOR_INTERVAL) {
        executeContractMonitor();
    }
    
    // Sleep for the scheduler interval
    sleep(SCHEDULER_INTERVAL);
    
    // Memory cleanup every 10 minutes
    if ($currentTime % 600 === 0) {
        gc_collect_cycles();
    }
}

