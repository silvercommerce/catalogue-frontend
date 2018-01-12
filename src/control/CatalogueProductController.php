<?php


namespace SilverCommerce\CatalogueFrontend\Control;

use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\ORM\PaginatedList;
use SilverCommerce\CatalogueAdmin\Model\CatalogueCategory;
use SilverCommerce\CatalogueFrontend\Traits\TemplateFinder;

/**
 * Controller used to render pages in the catalogue (either categories
 * or pages)
 *
 * @author i-lateral (http://www.i-lateral.com)
 * @package catalogue
 */
class CatalogueProductController extends ContentController
{
    use TemplateFinder;

    private static $allowed_actions = [
        'iid'
    ];

    /**
     * The Controller will take the URLSegment parameter from the URL
     * and use that to look up a record.
     */
    public function __construct($dataRecord = null)
    {
        if (!$dataRecord) {
            $dataRecord = new CatalogueProduct();
            if ($this->hasMethod("Title")) {
                $dataRecord->Title = $this->Title();
            }
            $dataRecord->URLSegment = get_class($this);
            $dataRecord->ID = -1;
        }
        
        $this->dataRecord = $dataRecord;
        $this->failover = $this->dataRecord;
        parent::__construct();
    }
    
    /**
     * The productimage action is used to determine the default image that will
     * appear related to a product
     *
     * @return Image
     */
    public function getImageForProduct()
    {
        $image = null;
        $action = $this->request->param('Action');
        $id = $this->request->param('ID');

        if ($action && $action === "iid" && $id) {
            $image = $this->Images()->byID($id);
        }

        if (!$image) {
            $image = $this->SortedImages()->first();
        }
            
        $this->extend("updateImageForProduct", $image);

        return $image;
    }

    /**
     * Get a list of templates to call and return a default render with
     */
    public function index()
    {
        $this->customise(array(
            "ProductImage" => $this->getImageForProduct()
        ));
        
        $this->extend("onBeforeIndex");

        $classes = $this->getTemplates();
        
        return $this->renderWith($classes);
    }
    
    /**
     * Get a list of templates to call and return a default render with
     */
    public function iid()
    {
        $this->customise(array(
            "ProductImage" => $this->getImageForProduct()
        ));
        
        $this->extend("onBeforeIID");
        
        $classes = $this->getTemplates();
        
        return $this->renderWith($classes);
    }
}
