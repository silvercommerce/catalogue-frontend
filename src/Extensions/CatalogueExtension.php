<?php

namespace SilverCommerce\CatalogueFrontend\Extensions;

use SilverStripe\View\HTML;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Dev\Deprecation;
use SilverStripe\Forms\FieldList;
use SilverStripe\Control\Director;
use SilverStripe\ORM\DataExtension;
use SilverStripe\View\Requirements;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\View\Parsers\URLSegmentFilter;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\CMS\Forms\SiteTreeURLSegmentField;
use SilverCommerce\CatalogueAdmin\Model\CatalogueProduct;
use SilverCommerce\CatalogueAdmin\Model\CatalogueCategory;
use SilverCommerce\CatalogueFrontend\Control\CatalogueController;
use SilverStripe\Core\Manifest\ModuleLoader;

class CatalogueExtension extends DataExtension
{
    private static $db = [
        "URLSegment" => "Varchar",
        "MetaDescription" => "Text",
        "ExtraMeta" => "HTMLFragment(['whitelist' => ['meta', 'link']])"
    ];

    private static $casting = [
        'MetaTags' => 'HTMLFragment'
    ];

    /**
     * Add extra fields to export (including SEO )
     *
     * @return array
     */
    public function updateExportFields(&$fields)
    {
        $fields['URLSegment'] = 'URLSegment';
        $fields['MetaDescription'] = 'MetaDescription';
        $fields['ExtraMeta'] = 'ExtraMeta';
        
        $manifest = ModuleLoader::inst()->getManifest();
        
        // If SEO module installed, add extra SEO subject field
        if ($manifest->moduleExists('hubertusanton/silverstripe-seo')) {
            $fields['SEOPageSubject'] = 'SEOPageSubject';
        }
    }

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
     * Return "link", "current" or section depending on if this page is the current page, or not on the current page but
     * in the current section.
     *
     * @return string
     */
    public function LinkingMode()
    {
        if ($this->isCurrent()) {
            return 'current';
        } elseif ($this->isSection()) {
            return 'section';
        } else {
            return 'link';
        }
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
        // Add CMS requirements for URL Segment Field
        Requirements::javascript('silverstripe/cms: client/dist/js/bundle.js');
        Requirements::css('silverstripe/cms: client/dist/styles/bundle.css');
        Requirements::add_i18n_javascript('silverstripe/cms: client/lang', false, true);

        $fields->removeByName("MetaDescription");
        $fields->removeByName("ExtraMeta");

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

        if (substr(rtrim($baseLink), -1) != "/") {
            $baseLink = $baseLink . "/";
        }

        $fields->addFieldToTab(
            "Root.Main",
            SiteTreeURLSegmentField::create(
                "URLSegment",
                $this->owner->fieldLabel('URLSegment')
            )->setURLPrefix($baseLink),
            'Content'
        );

        // Add meta info fields
        $fields->addFieldToTab(
            "Root.Main",
            ToggleCompositeField::create(
                'Metadata',
                _t(__CLASS__.'.MetadataToggle', 'Metadata'),
                [
                    $metaFieldDesc = TextareaField::create(
                        "MetaDescription",
                        $this->owner->fieldLabel('MetaDescription')
                    ),
                    $metaFieldExtra = TextareaField::create(
                        "ExtraMeta",
                        $this->owner->fieldLabel('ExtraMeta')
                    )
                ]
            )->setHeadingLevel(4)
        );

        // Help text for MetaData on page content editor
        $metaFieldDesc
            ->setRightTitle(
                _t(
                    'SilverStripe\\CMS\\Model\\SiteTree.METADESCHELP',
                    "Search engines use this content for displaying search results (although it will not influence their ranking)."
                )
            )->addExtraClass('help');

        $metaFieldExtra
            ->setRightTitle(
                _t(
                    'SilverStripe\\CMS\\Model\\SiteTree.METAEXTRAHELP',
                    "HTML tags for additional meta information. For example <meta name=\"customName\" content=\"your custom content here\" />"
                )
            )
            ->addExtraClass('help');
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

    /**
     * Generate a URL segment based on the title provided.
     *
     * @param string $title Page title
     *
     * @return string Generated url segment
     */
    public function generateURLSegment($title)
    {
        $filter = URLSegmentFilter::create();
        $t = $filter->filter($title);

        // Fallback to generic name if path is empty (= no valid, convertable characters)
        if (!$t || $t == '-' || $t == '-1') {
            $t = "{$this->owner->ID}";
        }

        // Ensure that this object has a non-conflicting URLSegment value.
        $existing_cats = CatalogueCategory::get()->filter('URLSegment', $t)->count();
        $existing_products = CatalogueProduct::get()->filter('URLSegment', $t)->count();
        $existing_pages = SiteTree::get()->filter('URLSegment', $t)->count();
        $count = (int)$existing_cats + (int)$existing_products + (int)$existing_pages;
        $t = ($count) ? $t . '-' . ($count + 1) : $t;

        // Hook for extensions
        $this->getOwner()->extend('updateURLSegment', $t, $title);

        return $t;
    }

    public function onBeforeWrite()
    {
        // Only call on first creation, ir if title is changed
        if ($this->getOwner()->isChanged('Title') || !$this->getOwner()->URLSegment) {
            $this->getOwner()->URLSegment = $this
                ->getOwner()
                ->generateURLSegment($this->getOwner()->Title);
        }
    }
    
    /**
     * Hides disabled products from googlesitemaps
     * Only called if googlesitemaps module is installed
     *
     * @param [type] $can
     * @return bool
     */
    public function alterCanIncludeInGoogleSitemap(&$can)
    {
        return !$this->getOwner()->Disabled;
    }
}
