<?php

namespace SilverStripe\Newsletter\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Newsletter\Tasks\NewsletterSendTask;

class NewsletterSendControllerTest extends SapphireTest
{
    protected static $fixture_file = "Base.yml";

    public function testEnqueue()
    {
        $newsletters = array();
        $newsletters[] = $this->objFromFixture('Newsletter', 'daily');
        $newsletters[] = $this->objFromFixture('Newsletter', 'monthly');
        $newsletters[] = $this->objFromFixture('Newsletter', 'all');

        $stuck1 = $this->objFromFixture('Recipient', 'stuck1');
        $stuck2 = $this->objFromFixture('Recipient', 'stuck2');

        $oldStuckTimeout = NewsletterSendTask::$stuck_timeout;
        NewsletterSendTask::$stuck_timeout = -5;  //this evals to --5 minutes which is +5 minutes, ie: the future

        foreach ($newsletters as $newsletter) {
            $nsc = NewsletterSendTask::inst();
            $nsc->enqueue($newsletter);
            $nsc->processQueue($newsletter->ID);

            foreach ($newsletter->MailingLists() as $mailingList) {
                foreach ($mailingList->Recipients() as $r) {
                    $this->assertEmailSent($r->Email, $newsletter->SendFrom, $newsletter->Subject);
                }
            }

            //check the email is sent out to the stalled item
            if ($newsletter->Subject == "Monthly Newsletter") {
                $this->assertEmailSent($stuck1->Email, $newsletter->SendFrom, $newsletter->Subject);
                $this->assertNull($this->findEmail($stuck2->Email, $newsletter->SendFrom, $newsletter->Subject),
                    'Email to stuck2 was NOT sent, as expected because the retry count was too high');
            }
        }

        NewsletterSendTask::$stuck_timeout = $oldStuckTimeout;
    }

    public function testDuplicateFiltering()
    {
        $newsletter = $this->objFromFixture('Newsletter', 'all');
        $nsc = NewsletterSendTask::inst();

        $added = $nsc->enqueue($newsletter);
        $this->assertGreaterThanOrEqual($added, 4, "4 recipients added");

        //add the same newsletter again
        $added = $nsc->enqueue($newsletter);
        $this->assertEquals($added, 0, "0 recipients added. Because newsletter is a duplicate");

        $newsletter = $this->objFromFixture('Newsletter', 'daily');
        $this->assertEquals($nsc->enqueue($newsletter), 2, "2 recipients added first time");
        $this->assertEquals($nsc->enqueue($newsletter), 0, "0 recipients added. Because newsletter is a duplicate");

        $newsletter = $this->objFromFixture('Newsletter', 'monthly');
        $this->assertEquals($nsc->enqueue($newsletter), 2, "2 recipients added first time");
        $this->assertEquals($nsc->enqueue($newsletter), 0, "0 recipients added. Because newsletter is a duplicate");
    }
}
