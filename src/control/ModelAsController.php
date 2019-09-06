<?php

namespace SilverCommerce\CatalogueFrontend\Control;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Controllers\ModelAsController as CMSModelAsController;
use SilverCommerce\CatalogueAdmin\Model\CatalogueCategory;

/**
 * Customise default @link ModelAsController to allow for finding and setting
 * of catalogue categories or products.
 */
class ModelAsController extends CMSModelAsController
{
    /**
     * Get the appropriate {@link CatalogueProductController} or
     * {@link CatalogueProductController} for handling the relevent
     * object.
     *
     * @param  $object A {@link DataObject} with the getControllerName() method
     * @param  string                                                          $action
     * @return CatalogueController
     */
    public static function controller_for_object($object, $action = null)
    {
        $controller = $object->getControllerName();

        if ($action && class_exists($controller . '_' . ucfirst($action))) {
            $controller = $controller . '_' . ucfirst($action);
        }

        return Injector::inst()->create($controller, $object);
    }

    /**
     * @return ContentController
     * @throws Exception If URLSegment not passed in as a request parameter.
     */
    public function getNestedController()
    {
        $request = $this->getRequest();

        if (!$URLSegment = $request->param('URLSegment')) {
            throw new Exception('ModelAsController->getNestedController(): was not passed a URLSegment value.');
        }

        // Find page by link, regardless of current locale settings
        if (class_exists('Translatable')) {
            Translatable::disable_locale_filter();
        }

        // Select child page
        $sitetree_conditions = ["URLSegment" => rawurlencode($URLSegment)];
        $cat_conditions = $sitetree_conditions;
        $product_conditions = $sitetree_conditions;
        
        if (SiteTree::config()->get('nested_urls')) {
            $sitetree_conditions['ParentID'] = 0;
        }

        $cat_conditions['ParentID'] = 0;

        $object = SiteTree::get()
            ->filter($sitetree_conditions)
            ->first();

        if (!$object) {
            $object = CatalogueCategory::get()
                ->filter($cat_conditions)
                ->first();
        }

        // Check translation module
        // @todo Refactor out module specific code
        if (class_exists('Translatable')) {
            Translatable::enable_locale_filter();
        }

        if (!$object) {
            $this->httpError(404, 'The requested page could not be found.');
        }

        // Enforce current locale setting to the loaded SiteTree object
        if (class_exists('Translatable') && $object->Locale) {
            Translatable::set_current_locale($object->Locale);
        }

        if (isset($_REQUEST['debug'])) {
            Debug::message("Using record #$object->ID of type " . get_class($object) . " with link {$sitetree->Link()}");
        }

        return self::controller_for_object($object, $this->getRequest()->param('Action'));
    }
}