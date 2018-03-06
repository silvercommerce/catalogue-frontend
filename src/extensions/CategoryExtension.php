<?php

namespace SilverCommerce\CatalogueFrontend\Extensions;

use SilverStripe\Assets\Image;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverCommerce\CatalogueAdmin\Helpers\Helper;

/**
 * Simple extension to category to add image support. This is mostly
 * for use in templates.
 * 
 * @package CatalogueFrontend
 * @subpackage Extensions
 */
class CategoryExtension extends DataExtension
{
    private static $has_one = [
        "Image" => Image::class
    ];

    /**
     * Gets the main image to use for this category, this
     * can either be the selected image, an image from the
     * first product or the default "no-product" image.
     * 
     * @return Image
     */
    public function PrimaryImage()
    {
        // If we have associated an image, return it
        $image = $this->owner->Image();

        if ($image->exists()) {
            return $image;
        }

        // Next try and get a child product image
        $product = $this->owner->AllProducts()->first();

        if (!empty($product)) {
            return $product->PrimaryImage();
        }

        // Finally generate our no product image
        return Helper::generate_no_image();
    }

    /**
     * Add the image upload field to the admin fields.
     * 
     */
    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldToTab(
            "Root.Main",
            UploadField::create("Image")
                ->setFolderName("categories")
        );
    }
}