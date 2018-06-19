<?php

namespace SilverStripe\Newsletter\Tests;

use SilverStripe\Dev\SapphireTest;

class RecipientTest extends SapphireTest
{
    protected static $fixture_file = "Base.yml";

    public function testCanNotDeleteWithExistingQueue()
    {
        $this->markTestIncomplete();
    }

    public function testCanNotCreateDuplicateRecipient()
    {
        $this->markTestIncomplete();
    }
}
