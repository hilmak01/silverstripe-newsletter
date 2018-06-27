<?php

namespace SilverStripe\Newsletter\Jobs;

use SilverStripe\Newsletter\Model\Newsletter;
use SilverStripe\Core\Injector\Injector;
use Psr\Log\LoggerInterface;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;

class NewsletterMailerJob extends AbstractQueuedJob
{
    private static $process_page_size = 10;

    protected $newsletterId;

    /**
     * @param int $newsletterId
     */
    public function __construct($newsletterId)
    {
        $this->newsletterId = $newsletterId;
        $this->currentStep = 0;
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
     * @return Newsletter
     */
    public function getNewsletter()
    {
        return Newsletter::get()->byId($this->newsletterId);
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return _t(__CLASS__ . '.MAILER', 'Newsletter Mailer');
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
     * This is run once per job, set ups the mailing queue.
     */
    public function setup()
    {
        parent::setup();

        Environment::increaseTimeLimitTo();
        Environment::increaseMemoryLimitTo();

        $newsletter = $this->getNewsletter();

        if (!$newsletter) {
            return;
        }

        $lists = $newsletter->MailingLists();
        $queueCount = 0;

        foreach ($lists as $list) {
            foreach ($list->Recipients()->column('ID') as $recipientID) {
                $existingQueue = SendRecipientQueue::get()->filter([
                    'RecipientID' => $recipientID,
                    'NewsletterID' => $newsletter->ID
                ]);

                if ($existingQueue->exists()) {
                    continue;
                }

                $queueItem = SendRecipientQueue::create();
                $queueItem->NewsletterID = $newsletter->ID;
                $queueItem->RecipientID = $recipientID;
                $queueItem->Status = 'Scheduled';
                $queueItem->write();
                $queueCount++;
            }
        }

        $this->totalSteps = $queueCount;
    }

    /**
     *
     */
    public function prepareForRestart()
    {
        parent::prepareForRestart();

        Environment::increaseTimeLimitTo();
        Environment::increaseMemoryLimitTo();

    }

    public function process()
    {
        $newsletter = $this->getNewsletter();

        if (!$newsletter) {
            return;
        }

        $remainingChildren = $newsletter->SendRecipientQueue()->filter('Status', 'Scheduled');

        // if there's no more, we're done!
        if (!$remainingChildren->exists()) {
            $this->completeJob();
            $this->isComplete = true;

            return;
        }

        $records = $remainingChildren->limit(0, self::config()->get('process_page_size'))->column('ID');
        $send = [];

        // mark all as in progress first.
        foreach ($records as $recordId) {
            $this->currentStep++;

            $record = SendRecipientQueue::get()->byId($recordId);

            if ($record) {
                $record->Status = 'InProgress';

                try {
                    $record->write();
                    $send[] = $recordId;
                } catch (Exception $e) {
                    Injector::inst()->get(LoggerInterface::class)->error($e->getMessage())
                }
            }
        }

        // send each of notices
        foreach ($send as $recordId) {
            $record = SendRecipientQueue::get()->byId($recordId);

            if ($record && $record->Status == 'InProgress') {
                try {
                    $record->send();
                } catch (Exception $e) {
                    Injector::inst()->get(LoggerInterface::class)->error($e->getMessage())
                }
            }
        }
    }

    /**
     * Marks the job as complete
     */
    protected function completeJob()
    {
        $newsletter = $this->getNewsletter();

        if ($newsletter) {
            $newsletter->Status = 'Sent';
            $newsletter->extend('onCompleteJob');
            $newsletter->write();
        }
    }
}
