<?php

/**
 * @file jobs/statistics/CompileMonthlyMetrics.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CompileMonthlyMetrics
 *
 * @ingroup jobs
 *
 * @brief Compile and store monthly usage stats from the daily records.
 */

namespace PKP\jobs\statistics;

use PKP\jobs\BaseJob;
use PKP\site\Site;

class CompileMonthlyMetrics extends BaseJob
{
    /**
     * The month the usage metrics should be aggregated by, in format YYYYMM.
     */
    protected string $month;

    protected Site $site;

    /**
     * Create a new job instance.
     *
     * @param string $month In format YYYYMM
     */
    public function __construct(string $month, Site $site)
    {
        parent::__construct();
        $this->month = $month;
        $this->site = $site;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $currentMonth = date('Ym');
        $lastMonth = date('Ym', strtotime('last month'));

        $geoService = app()->get('geoStats');
        $geoService->deleteMonthlyMetrics($this->month);
        $geoService->addMonthlyMetrics($this->month);
        if (!$this->site->getData('keepDailyUsageStats') && $this->month != $currentMonth && $this->month != $lastMonth) {
            $geoService->deleteDailyMetrics($this->month);
        }

        $counterService = app()->get('sushiStats');
        $counterService->deleteMonthlyMetrics($this->month);
        $counterService->addMonthlyMetrics($this->month);
        if (!$this->site->getData('keepDailyUsageStats') && $this->month != $currentMonth && $this->month != $lastMonth) {
            $counterService->deleteDailyMetrics($this->month);
        }
    }
}
