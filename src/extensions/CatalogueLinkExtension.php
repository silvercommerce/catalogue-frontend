<?php

namespace SilverCommerce\CatalogueFrontend\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\View\Parsers\URLSegmentFilter;
use SilverStripe\CMS\Forms\SiteTreeURLSegmentField;
use SilverStripe\CMS\Model\SiteTree;
use SilverCommerce\CatalogueAdmin\Model\CatalogueProduct;
use SilverCommerce\CatalogueAdmin\Model\CatalogueCategory;

class CatalogueLinkExtension extends DataExtension
{
    private static $db = [
        "URLSegment" => "Varchar"
    ];

    public function updateRelativeLink(&$link, $action)
    {
        $link = Controller::join_links(
            $this->owner->URLSegment,
            $action
        );
    }

    public function updateCMSFields(FieldList $fields)
    {
        $parent = null;
        $parent_link = null;
        
        if ($this->owner instanceof CatalogueCategory) {
            $parent = $this
                ->owner
                ->Parent();
        } elseif ($this->owner instanceof CatalogueProduct) {
            $parent = $this
                ->owner
                ->Categories()
                ->first();
        }

        if ($parent) {
            $parent_link = $parent->RelativeLink();
        }
        
        $baseLink = Controller::join_links(
            Director::absoluteBaseURL(),
            $parent_link
        );

        $url_field = SiteTreeURLSegmentField::create("URLSegment", $this->owner->fieldLabel('URLSegment'))
            ->setURLPrefix($baseLink);
        
        if ($fields->dataFieldByName("BasePrice")) {
            $base_feild = "BasePrice";
        } else {
            $base_feild = "Content";
        }

        $fields->addFieldToTab(
            "Root.Main",
            $url_field,
            $base_feild
        );
    }

    public function onBeforeWrite()
    {
        // Only call on first creation, ir if title is changed
        if ($this->isChanged('Title') || !$this->owner->URLSegment) {
            // Set the URL Segment, so it can be accessed via the controller
            $filter = URLSegmentFilter::create();
            $t = $filter->filter($this->owner->Title);

            // Fallback to generic name if path is empty (= no valid, convertable characters)
            if (!$t || $t == '-' || $t == '-1') {
                $t = "{$this->owner->ID}";
            }

            // Ensure that this object has a non-conflicting URLSegment value.
            $existing_cats = CatalogueCategory::get()->filter('URLSegment', $t)->count();
            $existing_products = CatalogueProduct::get()->filter('URLSegment', $t)->count();
            $existing_pages = SiteTree::get()->filter('URLSegment', $t)->count();
            $count = (int)$existing_cats + (int)$existing_products + (int)$existing_pages;
            $this->owner->URLSegment = ($count) ? $t . '-' . ($count + 1) : $t;
        }
    }
}