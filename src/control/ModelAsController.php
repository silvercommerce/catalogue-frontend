<?php

namespace SilverCommerce\CatalogueFrontend\Control;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\CMS\Controllers\ModelAsController as CMSModelAsController;

/**
 * Customise default @link ModelAsController to allow for finding and setting
 * of catalogue categories or products.
 * 
 */
class ModelAsController extends CMSModelAsController
{
    /**
     * Get the appropriate {@link CatalogueProductController} or
     * {@link CatalogueProductController} for handling the relevent
     * object.
     *
     * @param $object Either Product or Category object
     * @param string $action
     * @return CatalogueController
     */
    protected static function controller_for_object($object, $action = null)
    {
        if ($object->class == 'CatalogueProduct') {
            $controller = "CatalogueProductController";
        } elseif ($object->class == 'CatalogueCategory') {
            $controller = "CatalogueCategoryController";
        } else {
            $ancestry = ClassInfo::ancestry($object->class);
            
            while ($class = array_pop($ancestry)) {
                if (class_exists($class . "_Controller")) {
                    break;
                }
            }
            
            // Find the controller we need, or revert to a default
            if ($class !== null) {
                $controller = "{$class}_Controller";
            } elseif (ClassInfo::baseDataClass($object->class) == "CatalogueProduct") {
                $controller = "CatalogueProductController";
            } elseif (ClassInfo::baseDataClass($object->class) == "CatalogueCategory") {
                $controller = "CatalogueCategoryController";
            }
        }

        if ($action && class_exists($controller . '_' . ucfirst($action))) {
            $controller = $controller . '_' . ucfirst($action);
        }
        
        return class_exists($controller) ? Injector::inst()->create($controller, $object) : $object;
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
        $conditions = array('"SiteTree"."URLSegment"' => rawurlencode($URLSegment));
        if (SiteTree::config()->get('nested_urls')) {
            $conditions[] = array('"SiteTree"."ParentID"' => 0);
        }
        /** @var SiteTree $sitetree */
        $sitetree = DataObject::get_one(SiteTree::class, $conditions);

        // Check translation module
        // @todo Refactor out module specific code
        if (class_exists('Translatable')) {
            Translatable::enable_locale_filter();
        }

        if (!$sitetree) {
            $this->httpError(404, 'The requested page could not be found.');
        }

        // Enforce current locale setting to the loaded SiteTree object
        if (class_exists('Translatable') && $sitetree->Locale) {
            Translatable::set_current_locale($sitetree->Locale);
        }

        if (isset($_REQUEST['debug'])) {
            Debug::message("Using record #$sitetree->ID of type " . get_class($sitetree) . " with link {$sitetree->Link()}");
        }

        return self::controller_for($sitetree, $this->getRequest()->param('Action'));
    }
}