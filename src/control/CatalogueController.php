<?php

namespace SilverCommerce\CatalogueFrontend\Control;

use SilverStripe\Control\Director;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\View\SSViewer;
use SilverCommerce\CatalogueAdmin\Model\CatalogueProduct;
use SilverCommerce\CatalogueAdmin\Model\CatalogueCategory;
use \Page;


/**
 * Controller used to render pages in the catalogue (either categories or pages)
 *
 * @author i-lateral (http://www.i-lateral.com)
 * @package catalogue
 */
class CatalogueController extends ContentController
{

    /**
     * Find a filter from the URL that we can apply to the products list
     * 
     * @return array
     */
    public function getFilter()
    {
        $filter = [];
        $tag = $this->getRequest()->getVar("t");

        if ($tag) {
            $filter["Tags.URLSegment"] = $tag;
        }

        $this->extend("updateFilter", $filter);

        return $filter;
    }
    
    /**
     * Get a paginated list of products contained in this category
     *
     * @return PaginatedList
     */
    public function PaginatedProducts($limit = 10)
    {
        $products = $this->SortedProducts();
        $filter = $this->getFilter();

        if (count($filter)) {
            $products = $products->filter($filter);
        }
        return PaginatedList::create(
            $filter,
            $this->getRequest()
        )->setPageLength($limit);
    }


    /**
     * Get a paginated list of all products at this level and below
     *
     * @return PaginatedList
     */
    public function PaginatedAllProducts($limit = 10)
    {
        $products = $this->AllProducts();
        $filter = $this->getFilter();

        if (count($filter)) {
            $products = $products->filter($filter);
        }

        return PaginatedList::create(
            $products,
            $this->getRequest()
        )->setPageLength($limit);
    }

    /**
     * The Controller will take the URLSegment parameter from the URL
     * and use that to look up a record.
     */
    public function __construct($dataRecord = null)
    {   
        if (!$dataRecord) {
            $dataRecord = new CatalogueCategory();
            if ($this->hasMethod("Title")) {
                $dataRecord->Title = $this->Title();
            }
            $dataRecord->URLSegment = get_class($this);
            $dataRecord->ID = -1;
        }
        
        parent::__construct($dataRecord);
    }

    protected function init()
    {
        parent::init();

        # Check for subsites and add support
        if (class_exists(Subsite::class)) {
            $subsite = Subsite::currentSubsite();

            if ($subsite && $subsite->Theme) {
                SSViewer::add_themes([$subsite->Theme]);
            }

            if ($subsite && i18n::getData()->validate($subsite->Language)) {
                i18n::set_locale($subsite->Language);
            }
        }
    }

        /**
     * The productimage action is used to determine the default image that will
     * appear related to a product
     *
     * @return Image
     */
    public function ProductImage()
    {
        $image = $this->SortedImages()->first();
            
        $this->extend("updateProductImage", $image);

        return $image;
    }

    /**
     * This acts the same as {@link Controller::handleRequest()}, but if an action cannot be found this will attempt to
     * fall over to a child controller in order to provide functionality for nested URLs.
     *
     * @param HTTPRequest $request
     * @return HTTPResponse
     * @throws HTTPResponse_Exception
     */
    public function handleRequest(HTTPRequest $request)
    {
        /** @var SiteTree $child */
        $child  = null;
        $action = $request->param('Action');

        // If nested URLs are enabled, and there is no action handler for the current request then attempt to pass
        // control to a child controller. This allows for the creation of chains of controllers which correspond to a
        // nested URL.
        if ($action && !$this->hasAction($action)) {
            // See ModelAdController->getNestedController() for similar logic
            if (class_exists('Translatable')) {
                Translatable::disable_locale_filter();
            }
            // look for a child category with this URLSegment
            $child = CatalogueCategory::get()->filter([
                'ParentID' => $this->ID,
                'URLSegment' => rawurlencode($action)
            ])->first();

            // Next check to see if the child os a product
            if (!$child) {
                $child = CatalogueProduct::get()->filter([
                    "Categories.ID" => $this->ID,
                    "URLSegment" => rawurldecode($action)
                ])->first();
            }

            if (class_exists('Translatable')) {
                Translatable::enable_locale_filter();
            }
        }

        // we found a page with this URLSegment.
        if ($child) {
            $request->shiftAllParams();
            $request->shift();

            $response = ModelAsController::controller_for_object($child)->handleRequest($request);
        } else {
            // If a specific locale is requested, and it doesn't match the page found by URLSegment,
            // look for a translation and redirect (see #5001). Only happens on the last child in
            // a potentially nested URL chain.
            if (class_exists('Translatable')) {
                $locale = $request->getVar('locale');
                if ($locale
                    && i18n::getData()->validate($locale)
                    && $this->dataRecord
                    && $this->dataRecord->Locale != $locale
                ) {
                    $translation = $this->dataRecord->getTranslation($locale);
                    if ($translation) {
                        $response = new HTTPResponse();
                        $response->redirect($translation->Link(), 301);
                        throw new HTTPResponse_Exception($response);
                    }
                }
            }

            Director::set_current_page($this->data());

            try {
                $response = parent::handleRequest($request);

                Director::set_current_page(null);
            } catch (HTTPResponse_Exception $e) {
                $this->popCurrent();

                Director::set_current_page(null);

                throw $e;
            }
        }

        return $response;
    }

    /**
     * Overwrite default SSViewer call to get a custom
     * template list
     *
     * @param $action string
     * @return SSViewer
     */
    public function getViewer($action)
    {
        // Manually set templates should be dealt with by Controller::getViewer()
        if (isset($this->templates[$action]) && $this->templates[$action]
            || (isset($this->templates['index']) && $this->templates['index'])
            || $this->template
        ) {
            return parent::getViewer($action);
        }

        // Prepare action for template search
        if ($action == "index") {
            $action = "";
        } else {
            $action = '_' . $action;
        }

        $templates = array_merge(
            // Find templates by dataRecord
            SSViewer::get_templates_by_class(get_class($this->dataRecord), $action, "SilverStripe\\CMS\\Model\\SiteTree"),
            // Now get templates for sitetree
            SSViewer::get_templates_by_class(Page::singleton(), $action, "SilverStripe\\CMS\\Model\\SiteTree"),
            // Next, we need to add templates for all controllers
            SSViewer::get_templates_by_class(static::class, $action, "SilverStripe\\Control\\Controller"),
            // Fail-over to the same for the "index" action
            SSViewer::get_templates_by_class(get_class($this->dataRecord), "", "SilverStripe\\CMS\\Model\\SiteTree"),
            SSViewer::get_templates_by_class(static::class, "", "SilverStripe\\Control\\Controller")
        );

        return SSViewer::create($templates);
    }
    
    /**
     * Returns a fixed navigation menu of the given level.
     * @return SS_List
     */
    public function CategoryMenu($level = 1)
    {
        if ($level == 1) {
            $result = CatalogueCategory::get()->filter(array(
                "ParentID" => 0
            ));
        } else {
            $parent = $this->data();
            $stack = array($parent);

            if ($parent) {
                while ($parent = $parent->Parent) {
                    array_unshift($stack, $parent);
                }
            }

            if (isset($stack[$level-2])) {
                $result = $stack[$level-2]->Children();
            }
        }

        $visible = array();

        if (isset($result)) {
            foreach ($result as $item) {
                if ($item->canView()) {
                    $visible[] = $item;
                }
            }
        }

        return new ArrayList($visible);
    }
}
