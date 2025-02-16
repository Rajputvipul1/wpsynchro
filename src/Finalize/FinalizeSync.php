<?php

/**
 * Class for handling the finalization of the sync
 * @since 1.0.0
 */

namespace WPSynchro\Finalize;

use WPSynchro\Files\SyncList;
use WPSynchro\Database\DatabaseFinalize;
use WPSynchro\Files\FinalizeFiles;
use WPSynchro\Logger\FileLogger;
use WPSynchro\Logger\LoggerInterface;
use WPSynchro\Transport\Destination;
use WPSynchro\Transport\RemoteTransport;
use WPSynchro\Utilities\SyncTimerList;

class FinalizeSync
{
    // Base data
    private $job = null;
    private $migration = null;
    // Dependencies
    private $logger;
    public $timer = null;

    /**
     *  Constructor
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? FileLogger::getInstance();
        $this->timer = SyncTimerList::getInstance();
    }

    /**
     *  Run finalize method
     *  @since 1.0.0
     */
    public function runFinalize(&$migration, &$job)
    {
        $this->migration = &$migration;
        $this->job = &$job;

        $this->logger->log("INFO", "Starting finalize - Remaining time: " . $this->timer->getRemainingSyncTime());

        $this->job->finalize_progress = 10;

        /**
         *  Check what we need to do
         */
        if (!$this->migration->sync_files) {
            error_log('migration sync files '.print_r($$this->migration->sync_files,true));

            $this->job->finalize_files_completed = true;
            $this->job->finalize_progress += 45;
        }
        if (!$this->migration->sync_database) {
            $this->job->finalize_db_completed = true;
            $this->job->finalize_progress += 45;
        }

        /**
         *  If we have errors, return
         */
        if (count($this->job->errors) > 0) {
            return;
        }

        /**
         *  Files finalize
         */
        if (!$this->job->finalize_files_completed) {
            $this->finalizefiles();
            if ($this->job->finalize_files_completed) {
                $this->job->finalize_progress += 45;
            }

            return;
        }

        /**
         *  DB finalize
         */
        if (!$this->job->finalize_db_completed) {
            $destination = new Destination(Destination::TARGET);
            $databasefinalize = new DatabaseFinalize();
            $databasefinalize->finalize();
            $this->job->finalize_progress += floor(45 * $databasefinalize->getPercentCompletedForDatabaseFinalize());
            return;
        }

        $this->job->finalize_progress = 100;

        $this->logger->log("INFO", "Completed finalize - remaining time: " . $this->timer->getRemainingSyncTime());

        if ($this->job->finalize_files_completed && $this->job->finalize_db_completed) {
            // Execute last actions on target
            $this->logger->log("INFO", "Execute last actions on target - remaining time: " . $this->timer->getRemainingSyncTime());
            $this->finalizeActionsOnTarget();
            $this->logger->log("INFO", "Completed last actions on target - remaining time: " . $this->timer->getRemainingSyncTime());

            // Update progress
            $this->job->finalize_progress = 100;
            $this->job->finalize_completed = true;
            $this->job->finalize_progress_description = "";

            // Update option with counted success times
            $success_count = get_site_option("wpsynchro_success_count", 0);
            $success_count++;
            update_site_option("wpsynchro_success_count", $success_count);
        }
    }


    /**
     *  Finalize files
     *  @since 1.0.3
     */
    private function finalizefiles()
    {
        $sync_list = new SyncList();
        $sync_list->init($this->migration, $this->job);

        $finalize_files_handler = new FinalizeFiles();
        $finalize_files_handler->init($sync_list, $this->migration, $this->job);

        $finalize_files_handler->finalizeFiles();
    }

    /**
     *  Finalize actions on target
     *  @since 1.6.0
     */
    private function finalizeActionsOnTarget()
    {

        $actions_to_run = ["cleartransfertoken"];
        if ($this->migration->clear_cache_on_success === true) {
            array_unshift($actions_to_run, "clearcaches");
        }

        $destination = new Destination(Destination::TARGET);

        $url = $destination->getFullURL() . '?action=wpsynchro_execute_action';

        // Get remote transfer object
        $remotetransport = new RemoteTransport();
        $remotetransport->setDestination($destination);
        $remotetransport->init();
        $remotetransport->setUrl($url);
        $remotetransport->setDataObject($actions_to_run);
        $action_result = $remotetransport->remotePOST();

        if (!$action_result->isSuccess()) {
            $this->job->warnings[] = __("Some finalize actions failed to run - This can be cache clearing error or other error from target site. This normally does not impact the migration that much and therefore just a warning.", "wpsynchro");
        }
    }
}
