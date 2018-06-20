<?php

namespace SilverStripe\Newsletter\Control;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Newsletter\Model\Newsletter;
use SilverStripe\Newsletter\Model\MailingList;
use SilverStripe\Newsletter\Model\Recipient;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Newsletter\Form\GridField\NewsletterGridFieldDetailForm;
use SilverStripe\Newsletter\Form\GridField\NewsletterGridFieldDetailForm_ItemRequest;

class NewsletterAdmin extends ModelAdmin
{
    private static $url_segment = 'newsletter';

    private static $menu_title = 'Newsletter';

    private static $menu_icon = 'silverstripe/newsletter:client/images/newsletter-icon.png';

    private static $extra_requirements_css = [
        'silverstripe/newsletter:client/css/NewsletterAdmin.css'
    ];

    private static $extra_requirements_js = [
        'silverstripe/newsletter:client/javascript/ActionOnConfirmation.js'
    ];

    private static $managed_models = [
        Newsletter::class,
        MailingList::class,
        Recipient::class
    ];

    private static $template_paths = null;

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        if ($this->modelClass == Newsletter::class) {
            $config = $form->Fields()->first()->getConfig();
            $config->removeComponentsByType(GridFieldDetailForm::class);

            $config->addComponent((new NewsletterGridFieldDetailForm())->setItemRequestClass(
                    NewsletterGridFieldDetailForm_ItemRequest::class
                ));

            $config->getComponentByType(GridFieldDataColumns::class)
                ->setFieldCasting(array(
                    "Content" => "HTMLText->LimitSentences",
            ));

            $form->Fields()->first()->setConfig($config);
        }
        else if ($this->modelClass == Recipient::class) {
            $config = $form->Fields()->first()->getConfig();
            $config->getComponentByType(GridFieldDataColumns::class)
                ->setFieldCasting(array(
                    "Blacklisted" => "Boolean->Nice",
                    "Verified" => "Boolean->Nice",
            ));
        }

        return $form;
    }

    /**
     * @return array
     */
    public static function template_paths()
    {
        $paths = self::config()->get('template_paths');

        if (!$paths) {
            if ($config = SiteConfig::current_site_config()) {
                $theme = $config->Theme;
            } elseif (SSViewer::current_custom_theme()) {
                $theme = SSViewer::current_custom_theme();
            } elseif (SSViewer::current_theme()) {
                $theme = SSViewer::current_theme();
            } else {
                $theme = false;
            }

            if ($theme) {
                if (file_exists("../".THEMES_DIR."/".$theme."/templates/email")) {
                    $paths[] = THEMES_DIR."/".$theme."/templates/email";
                }

                if (file_exists("../".THEMES_DIR."/".$theme."/templates/Email")) {
                    $paths[] = THEMES_DIR."/".$theme."/templates/Email";
                }
            }

            $project = project();

            if (file_exists("../". $project . '/templates/email')) {
                $paths[] = $project . '/templates/email';
            }

            if (file_exists("../". $project . '/templates/Email')) {
                $paths[] = $project . '/templates/Email';
            }
        } else {
            if (is_string($paths)) {
                $paths = array($paths);
            }
        }

        return $paths;
    }
}
