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
    
    public function AddToCartForm() {
        $object = $this->owner->dataRecord;

        $form = Form::create(
            $this->owner,
            "AddToCartForm",
            FieldList::create(
                HiddenField::create('ID')
                    ->setValue($object->ID),
                HiddenField::create('ClassName')
                    ->setValue($object->ClassName),
                QuantityField::create('Quantity', _t('Catalogue.Qty','Qty'))
                    ->addExtraClass('checkout-additem-quantity')
            ),
            FieldList::create(
                FormAction::create(
                    'doAddItemToCart',
                    _t('Catalogue.AddToCart','Add to Cart')
                )->addExtraClass('btn btn-primary')
            ),
            RequiredFields::create(["Quantity"])
        );

        $this->owner->extend("updateAddToCartForm", $form);

        return $form;
    }

    public function doAddItemToCart($data, $form) {
        $classname = $data["ClassName"];
        $id = $data["ID"];
        $cart = ShoppingCart::get();
        $item_class = Config::inst()->get(ShoppingCart::class, "item_class");

        if($object = $classname::get()->byID($id)) {
            if (method_exists($object, "getTaxFromCategory")) {
                $tax_rate = $object->getTaxFromCategory();
            } else {
                $tax_rate = null;
            }

            $deliverable = (isset($object->Deliverable)) ? $object->Deliverable : true;
            
            $item_to_add = $item_class::create([
                "Title" => $object->Title,
                "Content" => $object->Content,
                "Price" => $object->Price,
                "Quantity" => $data['Quantity'],
                "StockID" => $object->StockID,
                "Weight" => $object->Weight,
                "ProductClass" => $object->ClassName,
                "Stocked" => $object->Stocked,
                "Deliverable" => $deliverable,
                "TaxRateID" => $tax_rate,
            ]);

            // Try and add item to cart, return any exceptions raised
            // as a message
            try {
                $cart->add($item_to_add);
                
                $message = _t(
                    'Catalogue.AddedItemToCart',
                    'Added "{item}" to your shopping cart',
                    ["item" => $object->Title]
                );

                $form->sessionMessage(
                    $message,
                    ValidationResult::TYPE_GOOD
                );
            } catch(ValidationException $e) {
                $form->sessionMessage(
                    $e->getMessage()
                );
            } catch(Exception $e) {
                $form->sessionMessage(
                    $e->getMessage()
                );
            }
        } else {
            $form->sessionMessage(
                _t("Catalogue.ErrorAddingToCart", "Error adding item to cart")
            );
        }

        return $this->owner->redirectBack();
    }

}