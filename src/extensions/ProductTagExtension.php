<?php

namespace SilverCommerce\CatalogueFrontend\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;

/**
 * Simple extension to allow tags to be aware of how they are interacted with.
 */
class ProductTagExtension extends DataExtension
{
    /**
     * Returns true if this is the currently active page being used to handle this request.
     *
     * @return bool
     */
    public function isCurrent()
    {
        $request = Injector::inst()->get(HTTPRequest::class);
        $tag = $request->getVar("t");

        return ($tag == $this->owner->URLSegment);
    }
}