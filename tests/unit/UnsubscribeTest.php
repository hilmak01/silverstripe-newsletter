<?php

namespace SilverStripe\Newsletter\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Newsletter\Control\UnsubscribeController;
use SilverStripe\Newsletter\Model\Recipient;
use SilverStripe\Security\Member;
use SilverStripe\Control\Director;
use SilverStripe\Security\Group;
use SilverStripe\Core\Config\Config;

class UnsubscribeTest extends SapphireTest
{
    protected static $fixture_file = 'UnsubscribeTest.yml';

    public static $page;

    public function setUp()
    {
        parent::setUp();

        self::$page = new UnsubscribeController();
    }

    public function testIndexWithAutoLoginHashAndNewsletterType()
    {
        $member = $this->objFromFixture(Recipient::class, "normann1");
        $group = $this->objFromFixture(Group::class, 'newsletter1');

        $this->assertTrue($member->inGroup($group));

        $url = 'unsubscribe/index/94l4ee9ib8kkw3s08k8wwcs4g/1';
        $response = Director::test($url);
        $baseurl = Director::absoluteBaseURL();

        $this->assertEquals(
        	$baseurl.'unsubscribe/done/94l4ee9ib8kkw3s08k8wwcs4g/1',
        	$response->getHeader('Location')
        );

        $this->assertFalse($member->inGroup($group));
    }

    public function testIndexWithAutoLoginHash()
    {
        $member = $this->objFromFixture(Recipient::class, "normann1");
        $url = 'unsubscribe/index/94l4ee9ib8kkw3s08k8wwcs4g';
        $body = $this->get($url)->getBody();

        $form = new Unsubscribe_MailingListForm(self::$page, 'MailingListForm', $member);

        $this->assertContains($form->forTemplate(), $body);
    }

    public function testIndex()
    {
        $url = 'unsubscribe/index';
        $body = Director::test($url)->getBody();
        $form = new Unsubscribe_EmailAddressForm(self::$page, 'EmailAddressForm');
        this->assertContains($form->forTemplate(), $body);
    }

    public function testDoneMessage()
    {
        $url1 = 'unsubscribe/done/94l4ee9ib8kkw3s08k8wwcs4g/1';
        $url2 = html_entity_decode(
         	'unsubscribe/done/94l4ee9ib8kkw3s08k8wwcs4g?MailingLists[1]=1&MailingLists[2]=2'
        );

        $body1 = Director::test($url1)->getBody();
        $body2 = Director::test($url2)->getBody();

        $message1 = sprintf(
         	_t('Unsubscribe.REMOVESUCCESS', 'Thank you. %s will no longer receive the %s.'),
         	'normann1@silverstripe.com',
         	'Daily Newsletter'
        );

        $message2 = sprintf(
        	_t('Unsubscribe.REMOVESUCCESS', 'Thank you. %s will no longer receive the %s.'),
        	'normann1@silverstripe.com',
        	'Daily Newsletter, Monthly Newsletter'
        );

        $this->AssertContains($message1, $body1);
        $this->AssertContains($message2, $body2);
    }

    public function testLinksent()
    {
        $this->markTestIncomplete();

        $url1 = 'unsubscribe/linksent?SendEmail=normann1@silverstripe.com';
        $url2 = 'unsubscribe/linksent?SendError=normann1@silverstripe.com';
        $body1 = Director::test($url1)->getBody();
        $body2 = Director::test($url2)->getBody();
        $message1 = sprintf(
         	_t('Unsubscribe.LINKSENTTO', "The unsubscribe link has been sent to %s"),
         	'normann1@silverstripe.com'
        );

        $message2 = sprintf(
        	_t(
        		'Unsubscribe.LINKSENDERR',
        		"Sorry, currently we have internal error, and can't send the unsubscribe link to %s"
        	),
        	'normann1@silverstripe.com'
        );

        $this->assertContains($message1, $body1);
        $this->assertContains($message2, $body2);
    }

    public function testSendMeUnsubscribeLink()
    {
        $this->markTestIncomplete();
    }
}
