<?php

namespace SilverCommerce\CatalogueFrontend\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\FieldList;

class CatalogueLinkExtension extends DataExtension
{
    private static $db = [
        "URLSegment" => "Varchar"
    ];

    public function updateRelativeLink($link, $action)
    {
        
    }

    public function updateCMSFields(FieldList $fields)
    {
        $parent = null;
        $parent_link = null;

        var_dump("test");
        
        if (method_exists($this->owner, "Categories")) {
            $parent = $this
                ->owner
                ->Categories()
                ->first();
        } elseif (method_exists($this->owner, "Parent")) {
            $parent = $this
                ->owner
                ->Parent();
        }

        if ($parent) {
            $parent_link = $parent->RelativeLink();
        }

        $baseLink = Controller::join_links(
            Director::absoluteBaseURL(),
            $parent_link,
            $this->owner->RelativeLink()
        );

        $url_field = SiteTreeURLSegmentField::create("URLSegment", $this->fieldLabel('URLSegment'))
            ->setURLPrefix($baseLink);

        $fields->addFieldToTab(
            "Root.Main",
            $url_field
        );
    }
}