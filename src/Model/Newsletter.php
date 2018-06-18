<?php

namespace SilverStripe\Newsletter\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\ORM\CMSPreviewable;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Control\Email\Email;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Control\Director;
use SilverStripe\Control\Controller;
use SilverStripe\Newsletter\Control\NewsletterAdmin;

class Newsletter extends DataObject implements CMSPreviewable
{
    private static $db = [
        "Status" => "Enum('Draft, Sending, Sent', 'Draft')",
        "Subject" => "Varchar(255)",
        "Content" => "HTMLText",
        "SentDate" => "Datetime",
        "SendFrom" => "Varchar(255)",
        "ReplyTo" => "Varchar(255)",
        "RenderTemplate"  => "Varchar",
    ];

    private static $has_many = [
        "SendRecipientQueue" => SendRecipientQueue::class,
        "TrackedLinks" => NewsletterTrackedLink::class
    ];

    private static $many_many = [
        "MailingLists" => MailingList::class
    ];

    private static $searchable_fields = [
        "Subject",
        "Content",
        "SendFrom",
        "SentDate"
    ];

    private static $default_sort = [
        "LastEdited DESC"
    ];

    private static $summary_fields = [
        "Subject",
        "SentDate",
        "Status"
    ];

    private static $required_fields = [
        'Subject',
        'SendFrom'
    ];

    private static $required_relations = [
        'MailingLists'
    ];

    private static $table_name = 'Newsletter';

    private static $singular_name = 'Newsletter';

    private static $plural_name = 'Newsletters';

    public function fieldLabels($includelrelations = true)
    {
        $labels = parent::fieldLabels($includelrelations);

        $labels["Subject"] = _t('Newsletter.FieldSubject', "Subject");
        $labels["Status"] = _t('Newsletter.FieldStatus', "Status");
        $labels["SendFrom"] = _t('Newsletter.FieldSendFrom', "From Address");
        $labels["ReplyTo"] = _t('Newsletter.FieldReplyTo', "Reply To Address");
        $labels["Content"] = _t('Newsletter.FieldContent', "Content");

        return $labels;
    }

    public function validate()
    {
        $result = parent::validate();

        foreach (static::config()->get('required_fields') as $field) {
            if (empty($this->$field)) {
                $result->addError(_t('Newsletter.FieldRequired',
                    '"{field}" field is required',
                        array('field' => isset(self::$field_labels[$field])?self::$field_labels[$field]:$field)
                ));
            }
        }

        if (!empty($this->ID)) {
            foreach (self::$required_relations as $relation) {
                if ($this->$relation()->Count() == 0) {
                    $result->addError(_t('Newsletter.RelationRequired',
                        'Select at least one "{relation}"',
                            array('relation' => $relation)
                    ));
                }
            }
        }

        return $result;
    }

    /**
     * Returns a FieldSet with which to create the CMS editing form.
     * You can use the extend() method of FieldSet to create customised forms for your other
     * data objects.
     *
     * @param Controller
     * @return FieldSet
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $admin = Email::config()->get('admin_email');

        $fields->removeByName('FileTracking');
        $fields->removeByName('LinkTracking');

        $fields->removeByName('Status');
        $fields->addFieldToTab(
            'Root.Main',
            new ReadonlyField('Status', $this->fieldLabel('Status')),
            'Subject'
        );

        $fields->removeByName("SentDate");
        if ($this->Status == "Sent") {
            $fields->addFieldToTab(
                'Root.Main',
                new ReadonlyField('SentDate', $this->fieldLabel('SentDate')),
                'Subject'
            );
        }

        $fields->dataFieldByName('SendFrom')
            ->setValue($admin)
            ->setAttribute('placeholder', 'My Name <admin@example.org>');

        $fields->dataFieldByName('ReplyTo')
            ->setValue($admin)
            ->setAttribute('placeholder', 'admin@example.org')
            ->setDescription(_t(
                'Newsletter.ReplyToDesc',
                'Any undeliverable emails will be collected in this mailbox'
            ));

        $fields->removeFieldFromTab('Root.SendRecipientQueue', "SendRecipientQueue");
        $fields->removeByName('SendRecipientQueue');
        $fields->removeByName('TrackedLinks');

        if ($this->Status != 'Sent') {
            $contentHelp = '<strong>'
                . _t('Newsletter.FormattingHelp', 'Formatting Help')
                . '</strong><br />';
            $contentHelp .= '<ul>';
            foreach ($this->getAvailablePlaceholders() as $title => $description) {
                $contentHelp .= sprintf('<li><em>$%s</em>: %s</li>', $title, $description);
            }
            $contentHelp .= '</ul>';
            $contentField = $fields->dataFieldByName('Content');
            if ($contentField) {
                $contentField->setDescription($contentHelp);
            }
        }

        // Only show template selection if there's more than one template set
        $templateSource = $this->templateSource();
        if (count($templateSource) > 1) {
            $fields->replaceField(
                "RenderTemplate",
                new DropdownField("RenderTemplate", _t('NewsletterAdmin.RENDERTEMPLATE',
                    'Template the newsletter render to'),
                $templateSource)
            );

            $explanationTitle = _t("Newletter.TemplateExplanationTitle",
                "Select a styled template (.ss template) that this newsletter renders with"
            );
            $fields->insertBefore(
                LiteralField::create("TemplateExplanationTitle", "<h5>$explanationTitle</h5>"),
                "RenderTemplate"
            );
            if (!$this->ID) {
                $explanation1 = _t("Newletter.TemplateExplanation1",
                    'You should make your own styled SilverStripe templates	make sure your templates have a'
                    . '$Body coded so the newletter\'s content could be clearly located in your templates'
                );
                $explanation2 = _t("Newletter.TemplateExplanation2",
                    "Make sure your newsletter templates could be looked up in the dropdown list below by
					either placing them under your theme directory,	e.g. themes/mytheme/templates/email/
					");
                $explanation3 = _t("Newletter.TemplateExplanation3",
                    "or under your project directory e.g. mysite/templates/email/
					");
                $fields->insertBefore(
                    LiteralField::create("TemplateExplanation1", "<p class='help'>$explanation1</p>"),
                    "RenderTemplate"
                );
                $fields->insertBefore(
                    LiteralField::create(
                        "TemplateExplanation2",
                        "<p class='help'>$explanation2<br />$explanation3</p>"
                    ),
                    "RenderTemplate"
                );
            }
        } else {
            $fields->replaceField("RenderTemplate",
                new HiddenField('RenderTemplate', false, key($templateSource))
            );
        }

        if ($this && $this->exists()) {
            $fields->removeByName("MailingLists");
            $mailinglists = MailingList::get();

            $fields->addFieldToTab("Root.Main",
                new CheckboxSetField(
                    "MailingLists",
                    _t('Newsletter.SendTo', "Send To", 'Selects mailing lists from set of checkboxes'),
                    $mailinglists->map('ID', 'FullTitle')
                )
            );
        }

        if ($this->Status === 'Sending' || $this->Status === 'Sent') {
            //make the whole field read-only
            $fields = $fields->transform(new ReadonlyTransformation());
            $fields->push(new HiddenField("NEWSLETTER_ORIGINAL_ID", "", $this->ID));

            $gridFieldConfig = GridFieldConfig::create()->addComponents(
                new GridFieldNewsletterSummaryHeader(),    //only works on SendRecipientQueue items, not TrackedLinks
                new GridFieldSortableHeader(),
                new GridFieldDataColumns(),
                new GridFieldFilterHeader(),
                new GridFieldPageCount(),
                new GridFieldPaginator(30)
            );

            $sendRecipientGrid = GridField::create(
                'SendRecipientQueue',
                _t('NewsletterAdmin.SentTo', 'Sent to'),
                $this->SendRecipientQueue(),
                $gridFieldConfig
            );

            $fields->addFieldToTab('Root.SentTo', $sendRecipientGrid);

            //only show restart queue button if the newsletter is stuck in "sending"
            //only show the restart queue button if the user can run the build task (i.e. has full admin permissions)
            if ($this->Status == "Sending" && Permission::check('ADMIN')) {
                $restartLink = Controller::join_links(
                    Director::absoluteBaseURL(),
                    'dev/tasks/NewsletterSendController?newsletter='.$this->ID
                );
                $fields->addFieldToTab('Root.SentTo',
                    new LiteralField(
                        'RestartQueue',
                        sprintf(
                            '<a href="%s" class="ss-ui-button" data-icon="arrow-circle-double">%s</a>',
                            $restartLink,
                            _t('Newsletter.RestartQueue', 'Restart queue processing')
                        )
                    )
                );
            }

            //only show the TrackedLinks tab, if there are tracked links in the newsletter and the status is "Sent"
            if ($this->TrackedLinks()->count() > 0) {
                $fields->addFieldToTab('Root.TrackedLinks', GridField::create(
                        'TrackedLinks',
                        _t('NewsletterAdmin.TrackedLinks', 'Tracked Links'),
                        $this->TrackedLinks(),
                        $gridFieldConfig
                    )
                );
            }
        }



        return $fields;
    }

    /**
     * return array containing all possible email templates file name
     * under the folders of both theme and project specific folder.
     *
     * @return array
     */
    public function templateSource()
    {
        $paths = NewsletterAdmin::template_paths();

        $templates = array(
            "SimpleNewsletterTemplate" => _t('TemplateList.SimpleNewsletterTemplate', 'Simple Newsletter Template')
        );

        if (isset($paths) && is_array($paths)) {
            $absPath = Director::baseFolder();
            if ($absPath{strlen($absPath)-1} != "/") {
                $absPath .= "/";
            }

            foreach ($paths as $path) {
                $path = $absPath.$path;


                if (is_dir($path)) {
                    $templateDir = opendir($path);


                    // read all files in the directory
                    while (($templateFile = readdir($templateDir)) !== false) {
                        // *.ss files are templates
                        if (preg_match('/(.*)\.ss$/', $templateFile, $match)) {
                            // only grab those haveing $Body coded
                            if (strpos("\$Body", file_get_contents($path."/".$templateFile)) === false) {
                                $templates[$match[1]] = preg_replace('/_?([A-Z])/', " $1", $match[1]);
                            }
                        }
                    }
                }
            }
        }
        return $templates;
    }

    /**
     * @return Array Map of place holder name to a description of its usage
     */
    public function getAvailablePlaceholders()
    {
        return array(
            'UnsubscribeLink' => _t(
                'Newsletter.PlaceholderUnsub',
                'Personalized link to unsubscribe from newsletter'
            ),
            'AbsoluteBaseURL' => _t(
                'Newsletter.PlaceholderAbsoluteUrl',
                'Absolute URL to the website'
            ),
            'To' => _t(
                'Newsletter.PlaceholderTo',
                'Recipient email address'
            ),
            'From' => _t(
                'Newsletter.PlaceholderFrom',
                'Sender email address'
            ),
            'Subject' => _t(
                'Newsletter.PlaceholderSubject',
                'Newsletter subject'
            ),
            'Recipient.Title' => _t(
                'Newsletter.PlaceholderTitle',
                'Recipient full name, including salutation, first/middle/last name (all optional)'
            ),
            'Recipient.Salutation' => _t(
                'Newsletter.PlaceholderSalutation',
                'Recipient salutation'
            ),
            'Recipient.FirstName' => _t(
                'Newsletter.PlaceholderFirstName',
                'Recipient first name'
            ),
            'Recipient.Surname' => _t(
                'Newsletter.PlaceholderSurname',
                'Recipient surname'
            ),
            'Recipient.Email' => _t(
                'Newsletter.PlaceholderEmail',
                'Recipient email address'
            ),
            'Now' => _t(
                'Newsletter.PlaceholderDate',
                'Current date and time (format e.g. with $Now.Nice)'
            )
        );
    }

    public function getTitle()
    {
        return $this->getField('Subject');
    }

    public function render()
    {
        if (!$templateName = $this->RenderTemplate) {
            $templateName = 'SimpleNewsletterTemplate';
        }
        // Block stylesheets and JS that are not required (email templates should have inline CSS/JS)
        Requirements::clear();

        // Create recipient with some test data
        $recipient = new Recipient(Recipient::$test_data);
        $newsletterEmail = NewsletterEmail::create($this, $recipient, true);

        return HTTP::absoluteURLs($newsletterEmail->getData()->renderWith($templateName));
    }

    /**
     * @return bool
     */
    public function canDelete($member = null)
    {
        $can = parent::canDelete($member);

        if ($this->Status !== 'Sending') {
            return $can;
        } else {
            if (Permission::check('ADMIN')) {
                return true;
            }

            return false;
        }
    }


    public function getContentBody()
    {
        $content = $this->obj('Content');

        $this->extend("updateContentBody", $content);
        return $content;
    }


    public function Link($action = null)
    {
        return Controller::join_links(singleton(NewsletterAdmin::class)->Link('Newsletter'),
            '/EditForm/field/Newsletter/item/', $this->ID, $action);
    }

    /**
     * @return string
     */
    public function CMSEditLink()
    {
        return Controller::join_links(singleton(NewsletterAdmin::class)->Link('Newsletter'),
            '/EditForm/field/Newsletter/item/', $this->ID, 'edit');
    }

    /**
     * @return string
     */
    public function PreviewLink($action = null)
    {
        return Controller::join_links(singleton(NewsletterAdmin::class)->Link('Newsletter'),
            '/EditForm/field/Newsletter/item/', $this->ID, 'edit');
    }

    /**
     * @return string
     */
    public function getMimeType()
    {
        return 'text/html';
    }

    public function onBeforeDelete()
    {
        parent::onBeforeDelete();

        $queueditems = $this->SendRecipientQueue();
        if ($queueditems && $queueditems->exists()) {
            foreach ($queueditems as $item) {
                $item->delete();
            }
        }

        $trackedLinks = $this->TrackedLinks();
        if ($trackedLinks && $trackedLinks->exists()) {
            foreach ($trackedLinks as $link) {
                $link->delete();
            }
        }

        //remove this from its belonged mailing lists
        $this->MailingLists()->removeAll();
    }
}
