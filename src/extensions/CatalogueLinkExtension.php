<?php

namespace SilverCommerce\CatalogueFrontend\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Deprecation;
use SilverStripe\View\Parsers\URLSegmentFilter;
use SilverStripe\CMS\Forms\SiteTreeURLSegmentField;
use SilverStripe\CMS\Model\SiteTree;
use SilverCommerce\CatalogueFrontend\Control\CatalogueController;
use SilverCommerce\CatalogueAdmin\Model\CatalogueProduct;
use SilverCommerce\CatalogueAdmin\Model\CatalogueCategory;

class CatalogueLinkExtension extends DataExtension
{
    private static $db = [
        "URLSegment" => "Varchar"
    ];

    public function updateRelativeLink(&$link, $action)
    {
        $parent = $this->owner->Parent();

        if ($parent && $parent->exists()) {
            $link = Controller::join_links(
                $parent->RelativeLink(),
                $this->owner->URLSegment,
                $action
            ); 
        } else {
            $link = Controller::join_links(
                $this->owner->URLSegment,
                $action
            ); 
        }
    }

    /**
     * Returns true if this is the currently active page being used to handle this request.
     *
     * @return bool
     */
    public function isCurrent()
    {
        $currentPage = Director::get_current_page();

        if ($currentPage instanceof ContentController) {
            $currentPage = $currentPage->data();
        }
        if ($currentPage instanceof CatalogueCategory || $currentPage instanceof CatalogueProduct) {
            return $currentPage === $this->owner || $currentPage->ID === $this->owner->ID;
        }
        return false;
    }

    /**
     * Check if this page is in the currently active section (e.g. it is either current or one of its children is
     * currently being viewed).
     *
     * @return bool
     */
    public function isSection()
    {
        $is_curr = $this->isCurrent();
        $curr = Director::get_current_page();

        return $is_curr || (
            ($curr instanceof CatalogueCategory || $curr instanceof CatalogueProduct) && in_array($this->owner->ID, $curr->getAncestors()->column())
        );
    }
    
    /**
     * Return "link" or "section" depending on if this is the current section.
     *
     * @return string
     */
    public function LinkOrSection()
    {
        return $this->isSection() ? 'section' : 'link';
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

    /**
     * Find the controller name by our convention of {$ModelClass}Controller
     *
     * @return string
     */
    public function getControllerName()
    {
        //default controller for SiteTree objects
        $controller = CatalogueController::class;

        //go through the ancestry for this class looking for
        $ancestry = ClassInfo::ancestry($this->owner->ClassName);

        // loop over the array going from the deepest descendant (ie: the current class) to SiteTree
        while ($class = array_pop($ancestry)) {
            //we don't need to go any deeper than the SiteTree class
            if ($class == CatalogueProduct::class || $class == CatalogueCategory::class) {
                break;
            }
            
            // If we have a class of "{$ClassName}Controller" then we found our controller
            if (class_exists($candidate = sprintf('%sController', $class))) {
                $controller = $candidate;
                break;
            } elseif (class_exists($candidate = sprintf('%s_Controller', $class))) {
                // Support the legacy underscored filename, but raise a deprecation notice
                Deprecation::notice(
                    '5.0',
                    'Underscored controller class names are deprecated. Use "MyController" instead of "My_Controller".',
                    Deprecation::SCOPE_GLOBAL
                );
                $controller = $candidate;
                break;
            }
        }

        return $controller;
    }

    public function onBeforeWrite()
    {
        // Only call on first creation, ir if title is changed
        if ($this->owner->isChanged('Title') || !$this->owner->URLSegment) {
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
