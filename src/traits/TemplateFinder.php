<?php

namespace SilverCommerce\CatalogueFrontend\Traits;

use SilverStripe\Core\ClassInfo;
use SilverStripe\View\ViewableData;
use SilverStripe\ORM\DataObject;
use SilverCommerce\CatalogueAdmin\Model\CatalogueCategory;
use SilverCommerce\CatalogueAdmin\Model\CatalogueProduct;
use Catalogue;
use Page;

/**
 * Simple helper class to provide common functions across
 * all libraries
 *
 * @author i-lateral (http://www.i-lateral.com)
 * @package catalogue
 */
trait TemplateFinder
{
    /**
     * Template names to be removed from the default template list 
     * 
     * @var array
     * @config
     */
    private static $classes_to_remove = array(
        ViewableData::class,
        DataObject::class,
        CatalogueProduct::class,
        CatalogueCategory::class
    );

    /**
     * Get a list of templates for rendering
     *
     * @return array Array of classnames
     */
    public function getTemplates()
    {
        $classes = ClassInfo::ancestry($this->dataRecord->class);
        $classes = array_reverse($classes);
        $remove_classes = self::config()->classes_to_remove;
        $return = [];

        array_push($classes, Catalogue::class, Page::class);

        foreach ($classes as $class) {
            if (!in_array($class, $remove_classes)) {
                $return[] = $class;
            }
        }
        
        return $return;
    }
}