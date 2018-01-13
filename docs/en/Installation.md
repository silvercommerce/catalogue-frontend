Installing and Setting Up
=========================

## Via composer

The default way to do this is to use composer, by using the following command: 

    composer require silvercommerce/catalogue-frontend


## From source / manuallly

You can download this module either direct from the Silverstripe addons
directory or Github.

If you do, then follow this process:

* Download a Zip or Tarball of this module
* Extract the module into a directory callled "catalogue" in your project
* Run http://www.yoursite.com/dev/build?flush=all

## Add "Product" and "Category" controllers to match your model objects.

The catalogue module works in a similar way to the CMS module. Once installed you will need to add a 
"ProductController" (to match your "Product" model) and a
"CategoryController" (to match your "Category" model).

For example:

    /projectroot/mysite/code/ProductController.php
    
    <?php
    
    class ProductController extends CatalogueProductController
    {
        ...
    }
    
    /projectroot/mysite/code/CategoryController.php
    
    <?php
    
    class CategoryController extends CatalogueCategoryController
    {   
        ...
    }

## Add templates to your theme

Finally, you can add templates to your theme's "Layout" folder
named to match your controllers, EG:

* `ProductController` would use a template called `Product.ss`
* `CategoryController` would use a template called `Category.ss`