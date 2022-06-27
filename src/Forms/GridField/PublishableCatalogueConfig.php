<?php

namespace SilverCommerce\CatalogueFrontend\Forms\GridField;

use Colymba\BulkManager\BulkAction\PublishHandler;
use Colymba\BulkManager\BulkAction\UnPublishHandler;
use Colymba\BulkManager\BulkManager;
use SilverCommerce\CatalogueAdmin\Forms\GridField\GridFieldConfig_Catalogue;


/**
 * Overwrite default config to also push publish and
 * unpublish actions to bulk manager.
 * 
 * This class overwrites its parent via Injector
 */
class PublishableCatalogueConfig extends GridFieldConfig_Catalogue
{
    public function __construct(
        $classname,
        $itemsPerPage = null,
        $sort_col = false,
        $create_baseclass = false
    ) {
        parent::__construct(
            $classname,
            $itemsPerPage,
            $sort_col,
            $create_baseclass
        );

        /** @var BulkManager */
        $bulk_manager = $this->getComponentByType(BulkManager::class);

        if (empty($bulk_manager)) {
            return;
        }

        $existing = $bulk_manager->getBulkActions();

        if (!in_array(PublishHandler::class, $existing)) {
            $bulk_manager->addBulkAction(PublishHandler::class);
        }

        if (!in_array(UnPublishHandler::class, $existing)) {
            $bulk_manager->addBulkAction(UnPublishHandler::class);
        }
    }
}