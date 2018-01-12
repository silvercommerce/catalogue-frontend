<?php

namespace SilverCommerce\CatalogueFrontend\Control;

use SilverStripe\CMS\Controllers\RootURLController;
use SilverStripe\Control\HTTPRequest;

/**
 * URLController determins what part of Silverstripe (framework, 
 * Catalogue or CMS) will handle the current URL.
 *
 * @author i-lateral (http://www.i-lateral.com)
 * @package catalogue
 */
class URLController extends RootURLController
{

    /**
     * Check catalogue URL's before we get to the CMS (if it exists)
     * 
     * @param SS_HTTPRequest $request
     * @param DataModel|null $model
     * @return SS_HTTPResponse
     */
    public function handleRequest(HTTPRequest $request)
    {
        $this->request = $request;
		$this->setDataModel($model);
        $catalogue_enabled = Catalogue::config()->enable_frontend;
		
		$this->pushCurrent();

        // Create a response just in case init() decides to redirect
        $this->response = new SS_HTTPResponse();

        $this->init();
        
        // If we had a redirection or something, halt processing.
        if ($this->response->isFinished()) {
            $this->popCurrent();
            return $this->response;
        }
        
        // If DB is not present, build
        if (!DB::isActive() || !ClassInfo::hasTable('CatalogueProduct') || !ClassInfo::hasTable('CatalogueCategory')) {
            return $this->response->redirect(Director::absoluteBaseURL() . 'dev/build?returnURL=' . (isset($_GET['url']) ? urlencode($_GET['url']) : null));
        }
        
        $urlsegment = $request->param('URLSegment');

        $this->extend('onBeforeInit');

        $this->init();

        $this->extend('onAfterInit');

        // Find link, regardless of current locale settings
        if (class_exists('Translatable')) {
            Translatable::disable_locale_filter();
        }
        
        $filter = array(
            'URLSegment' => $urlsegment,
            'Disabled' => 0
        );
        
        if($catalogue_enabled && $object = CatalogueProduct::get()->filter($filter)->first()) {
            $controller = $this->controller_for($object);
        } elseif($catalogue_enabled && $object = CatalogueCategory::get()->filter($filter)->first()) {
            $controller = $this->controller_for($object);
        } elseif (class_exists('ModelAsController')) { // If CMS installed
            $controller = ModelAsController::create();
        } else {
            $controller = Controller::create();
        }
        
        if (class_exists('Translatable')) {
            Translatable::enable_locale_filter();
        }
        
        $result = $controller->handleRequest($request, $model);

        $this->popCurrent();
        return $result;
    }
}
