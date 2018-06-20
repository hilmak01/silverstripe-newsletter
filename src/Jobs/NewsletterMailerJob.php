<?php

namespace SilverStripe\Newsletter\Jobs;

use SilverStripe\Newsletter\Model\Newsletter;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;

class NewsletterMailerJob extends AbstractQueuedJob
{
    protected $newsletterId;

    /**
     * @param int $newsletterId
     */
    public function __construct($newsletterId)
    {
        $this->newsletterId = $newsletterId;
        $this->currentStep = 0;
        $this->totalSteps = count($this->pagesToProcess);
    }

    /**
     * Sitemap job is going to run for a while...
     *
     * @return int
     */
    public function getJobType()
    {
        return QueuedJob::QUEUED;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return _t(__CLASS__ . '.REGENERATE', 'Regenerate Google sitemap .xml file');
    }

    /**
     * Return a signature for this queued job
     *
     * @return string
     */
    public function getSignature()
    {
        return md5(get_class($this) . $this->newsletterId);
    }

    /**
     * Note that this is duplicated for backwards compatibility purposes...
     */
    public function setup()
    {
        parent::setup();

        Environment::increaseTimeLimitTo();
        Environment::increaseMemoryLimitTo();
    }

    /**
     * On any restart, make sure to check that our temporary file is being
     * created still.
     */
    public function prepareForRestart()
    {
        parent::prepareForRestart();


    }

    public function process()
    {
        $remainingChildren = $this->pagesToProcess;

        // if there's no more, we're done!
        if (!count($remainingChildren)) {
            $this->completeJob();

            $this->isComplete = true;
            return;
        }

        // todoo

        // and now we store the new list of remaining children
        $this->pagesToProcess = $remainingChildren;
        $this->currentStep++;

        if (!count($remainingChildren)) {
            $this->completeJob();
            $this->isComplete = true;
            return;
        }
    }

    /**
     * Markes the job as complete
     */
    protected function completeJob()
    {

    }
}
