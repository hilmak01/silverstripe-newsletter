<?php

namespace SilverStripe\Newsletter\Control\Email;

use SilverStripe\Control\Email\Email;
use SilverStripe\Control\Director;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\ArrayData;
use SilverStripe\Newsletter\Model\NewsletterTrackedLink;
use SilverStripe\Newsletter\Control\UnsubscribeController;

class NewsletterEmail extends Email
{
    protected $mailinglists;

    protected $newsletter;

    protected $recipient;

    protected $fakeRecipient;

    /**
     * Should the link tracking be enabled.
     *
     * @var boolean
     */
    private static $link_tracking_enabled = true;

    /**
     * @param Newsletter $newsletter
     * @param Mailinglists $recipient
     * @param Boolean $fakeRecipient
     */
    public function __construct($newsletter, $recipient, $fakeRecipient=false)
    {
        $this->newsletter = $newsletter;
        $this->mailinglists = $newsletter->MailingLists();
        $this->recipient = $recipient;
        $this->fakeRecipient = $fakeRecipient;

        parent::__construct($this->newsletter->SendFrom, $this->recipient->Email);

        $this->setData(new ArrayData([
            'UnsubscribeLink' => $this->UnsubscribeLink(),
            'SiteConfig' => SiteConfig::current_site_config(),
            'AbsoluteBaseURL' => Director::absoluteBaseURL()
        ]));

        $this->body = $newsletter->getContentBody();
        $this->subject = $newsletter->Subject;
        $this->ss_template = $newsletter->RenderTemplate;

        if ($this->body && $this->newsletter) {
            $text = $this->body->forTemplate();

            //Recipient Fields ShortCode parsing
            $bodyViewer = new SSViewer_FromString($text);
            $text = $bodyViewer->process($this->templateData());

            // Install link tracking by replacing existing links with "newsletterlink" and hash-based reference.
            if ($this->config()->link_tracking_enabled &&
                !$this->fakeRecipient &&
                preg_match_all("/<a\s[^>]*href=\"([^\"]*)\"[^>]*>(.*)<\/a>/siU", $text, $matches)) {
                if (isset($matches[1]) && ($links = $matches[1])) {
                    $titles = (isset($matches[2])) ? $matches[2] : array();
                    $id = (int) $this->newsletter->ID;

                    $replacements = array();
                    $current = array();

                    // workaround as we want to match the longest urls (/foo/bar/baz) before /foo/
                    array_unique($links);

                    $sorted = array_combine($links, array_map('strlen', $links));
                    arsort($sorted);

                    foreach ($sorted as $link => $length) {
                        // ignore mailto's
                        if ((substr(strtolower($link), 0, 7) == 'mailto:')) {
                            $replacements[$link] = $link;

                            continue;
                        }

                        if ((substr(strtolower($link), 0, 15) == 'newsletterlinks')) {
                            $replacements[$link] = $link;

                            continue;
                        }

                        $tracked = NewsletterTrackedLink::get()->filter([
                            'NewsletterID' => $id,
                            'Original' => $link
                        ]);

                        if (!$tracked) {
                            $tracked = NewsletterTrackedLink::create();
                            $tracked->Original = $link;
                            $tracked->NewsletterID = $id;
                            $tracked->write();
                        }

                        // replace the link
                        $replacements[$link] = $tracked->Link();

                        // track that this link is still active
                        $current[] = $tracked->ID;
                    }

                    // replace the strings
                    $text = str_ireplace(array_keys($replacements), array_values($replacements), $text);
                }
            }
            // replace the body
            $output = new HTMLText();
            $output->setValue($text);
            $this->body = $output;
        }
    }

    public function send($id = null)
    {
        $this->extend('onBeforeSend');
        return parent::send($id);
    }

    /**
     * @return Newsletter
     */
    public function Newsletter()
    {
        return $this->newsletter;
    }

    /**
     *
     */
    public function UnsubscribeLink()
    {
        if ($this->recipient && !$this->fakeRecipient) {
            //the unsubscribe link is for all MaillingLists that the Recipient is subscribed to, intersected with a
            //list of all MaillingLists to which the Email was sent
            $recipientLists = $this->recipient->MailingLists()->column('ID');
            $sendLists = $this->mailinglists->column('ID');
            $lists = array_intersect($recipientLists, $sendLists);

            $listIDs = implode(',', $lists);
            $days = UnsubscribeController::config()->get('days_unsubscribe_link_alive');

            if ($this->recipient->ValidateHash) {
                $this->recipient->ValidateHashExpired = date('Y-m-d H:i:s', time() + (86400 * $days));
                $this->recipient->write();
            } else {
                $this->recipient->generateValidateHashAndStore($days);
            }

            $link =  Director::absoluteBaseURL() . "unsubscribe/index/".$this->recipient->ValidateHash."/$listIDs";

            return $link;
        } else {
            $listIDs = implode(",", $this->mailinglists->getIDList());

            return Director::absoluteBaseURL() . "unsubscribe/index/fackedvalidatehash/$listIDs";
        }
    }

    /**
     * @return
     */
    protected function templateData()
    {
        $default = array(
            "To" => $this->to,
            "Cc" => $this->cc,
            "Bcc" => $this->bcc,
            "From" => $this->from,
            "Subject" => $this->subject,
            "Body" => $this->body,
            "BaseURL" => $this->BaseURL(),
            "IsEmail" => true,
            "Recipient" => $this->recipient,
            "Member" => $this->recipient, // backwards compatibility
        );

        if ($this->template_data) {
            return $this->template_data->customise($default);
        } else {
            return $this;
        }
    }

    public function getData()
    {
        return $this->templateData();
    }
}
