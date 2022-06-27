<?php

use SilverStripe\Core\Config\Config;
use SilverStripe\Versioned\Versioned;
use Wilr\GoogleSitemaps\GoogleSitemap;
use SilverCommerce\CatalogueAdmin\Model\CatalogueProduct;
use SilverCommerce\CatalogueAdmin\Model\CatalogueCategory;

// Re-configure versioning to also contain a "Live" and "Staged" state
$versioned_class = Versioned::class . '.versioned';
$product_extensions = Config::inst()->get(CatalogueProduct::class, 'extensions');
$category_extensions = Config::inst()->get(CatalogueCategory::class, 'extensions');

$product_extensions = array_diff(
    $product_extensions,
    [$versioned_class]
);
$product_extensions[] = Versioned::class;

$category_extensions = array_diff(
    $category_extensions,
    [$versioned_class]
);
$category_extensions[] = Versioned::class;

Config::modify()->set(
    CatalogueProduct::class,
    'extensions',
    $product_extensions
);
Config::modify()->set(
    CatalogueCategory::class,
    'extensions',
    $category_extensions
);

if (class_exists(GoogleSitemap::class)) {
    GoogleSitemap::register_dataobject(CatalogueProduct::class);
    GoogleSitemap::register_dataobject(CatalogueCategory::class);
}