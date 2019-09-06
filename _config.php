<?php

use Wilr\GoogleSitemaps\GoogleSitemap;
use SilverCommerce\CatalogueAdmin\Model\CatalogueProduct;
use SilverCommerce\CatalogueAdmin\Model\CatalogueCategory;

if (class_exists(GoogleSitemap::class)) {
    GoogleSitemap::register_dataobject(CatalogueProduct::class);
    GoogleSitemap::register_dataobject(CatalogueCategory::class);
}