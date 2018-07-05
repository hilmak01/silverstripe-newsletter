<?php

namespace SilverStripe\Newsletter\Tests;

use SilverStripe\Dev\SapphireTest;

class NewsletterEmailTest extends SapphireTest
{
    protected static $fixture_file = "Base.yml";

    public function testConstructor()
    {
        $newsletter = $this->objFromFixture(Newsletter::class, 'all');
        $recipient = $this->objFromFixture(Recipient::class, 'normann1');
        $email = NewsletterEmail::create($newsletter, $recipient);

        $this->assertEquals($newsletter->Subject, $email->getData()->Subject);
    }

    public function testTracksRelativeLinks()
    {
        $this->markTestIncomplete();
    }

    public function testTracksAbsoluteLinks()
    {
        $this->markTestIncomplete();
    }

    public function testDoesNotDuplicateTrackingLinks()
    {
        $this->markTestIncomplete();
    }

    public function createsUniqueUnsubscribeLink()
    {
        $this->markTestIncomplete();
    }
}
