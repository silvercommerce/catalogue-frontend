<?php

namespace SilverCommerce\CatalogueFrontend\Extensions;

use SilverStripe\Forms\Form;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\NumericField;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Forms\RequiredFields;
use SilverCommerce\OrdersAdmin\Model\LineItem;
use SilverCommerce\ShoppingCart\Control\ShoppingCart;
use SilverCommerce\QuantityField\Forms\QuantityField;
use SilverCommerce\ShoppingCart\Forms\AddToCartForm;

/**
 * Add an add to cart form that generates a {@link LineItem} for
 * this object and then adds it to the shopping cart 
 *
 * @author i-lateral (http://www.i-lateral.com)
 * @package cataloge-frontend
 */
class AddToCartExtension extends Extension
{
    private static $allowed_actions = [
        "AddToCartForm"
    ];
    
    public function AddToCartForm()
    {
        $object = $this->owner->dataRecord;

        $form = AddToCartForm::create(
            $this->owner,
            "AddToCartForm"
        );

        $form
            ->setProductClass($object->ClassName)
            ->setProductID($object->ID);

        return $form;
    }

}