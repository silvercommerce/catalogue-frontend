<?php

namespace SilverCommerce\CatalogueFrontend\Extensions;

use SilverStripe\Core\Extension;
use SilverCommerce\CatalogueAdmin\Model\CatalogueProduct;
use SilverCommerce\CatalogueAdmin\Model\CatalogueCategory;

/**
 * Extension for Controller that provide additional methods to all
 * templates 
 *
 * @author i-lateral (http://www.i-lateral.com)
 * @package catalogue
 */
class ControllerExtension extends Extension
{
    /**
     * Gets a list of all Categories, either top level (default) or
     * from a sub level
     *
     * @param Parent the ID of a parent cetegory
     *
     * @return SS_List
     */
    public function CatalogueCategories($ParentID = 0)
    {
        return CatalogueCategory::get()
            ->filter([
                "ParentID" => $ParentID,
                "Disabled" => 0
            ]);
    }

    /**
     * Generate a list of categories that can be used in menus
     * (this means that) categories are checked for their
     * can view state before being returned
     * 
     * @param Parent the ID of a parent cetegory
     *
     * @return SS_List
     */
    public function CatalogueMenu($ParentID = 0)
    {
        return $this
            ->owner
            ->CatalogueCategories()
            ->filterByCallback(function($item, $list) {
                return $item->canView();
            });
    }

    /**
     * Get a full list of products, filtered by a category if provided.
     *
     * @param ParentCategoryID the ID of the parent category
     */
    public function CatalogueProducts($ParentCategoryID = 0)
    {
        return CatalogueProduct::get()
            ->filter([
                "ParentID" => $ParentCategoryID,
                "Disabled" => 0
            ]);
    }
}