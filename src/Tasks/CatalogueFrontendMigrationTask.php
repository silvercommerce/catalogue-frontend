<?php

namespace SilverCommerce\CatalogueFrontend\Tasks;

use SilverCommerce\CatalogueAdmin\Model\CatalogueCategory;
use SilverCommerce\CatalogueAdmin\Model\CatalogueProduct;
use SilverStripe\ORM\DataList;
use SilverStripe\Control\Director;
use SilverStripe\Dev\MigrationTask;
use SilverStripe\Subsites\Model\Subsite;

/**
 * Task to handle migrating orders/items to newer versions
 */
class CatalogueFrontendMigrationTask extends MigrationTask
{
    const CHUNK_SIZE = 200;

    /**
     * Should this task be invoked automatically via dev/build?
     *
     * @config
     *
     * @var bool
     */
    private static $run_during_dev_build = true;

    private static $segment = 'CatalogueFrontendMigrationTask';

    protected $description = "Upgrade catalogue data objects inline with latest amendments";

    /**
     * Run this task
     *
     * @param HTTPRequest $request The current request
     *
     * @return void
     */
    public function run($request)
    {
        if ($request->getVar('direction') == 'down') {
            $this->down();
        } else {
            $this->up();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $start_time = time();

        if (class_exists(Subsite::class)) {
            $products = Subsite::get_from_all_subsites(CatalogueProduct::class);
            $categories = Subsite::get_from_all_subsites(CatalogueCategory::class);
        } else {
            $products = CatalogueProduct::get();
            $categories = CatalogueCategory::get();
        }

        $this->processChunkedList($products, $start_time);
        $this->processChunkedList($categories, $start_time);

        // purge current var
        $products = null;
        $categories = null;
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        $this->log('Downgrade Not Possible');
    }

    protected function processChunkedList(
        DataList $list,
        int $start_time
    ) {
        $chunk_size = self::CHUNK_SIZE;
        $curr_chunk = 0;
        $migrated = 0;
        $items_count = $list->count();
        $total_chunks = 1;
        $class = $list->dataClass();

        // If we have a usable list, calculate total chunks
        if ($items_count > 0) {
            $this->log("- {$items_count} {$class} to migrate.");

            // Round up the total chunks, so stragglers are caught
            $total_chunks = ceil(($items_count / $chunk_size));
        }

        /**
         * Break line items list into chunks to save memory
         *
         * @var DataList $estimates
         */
        while ($curr_chunk < $total_chunks) {
            $chunked_list =  $list
                ->limit($chunk_size, $curr_chunk * $chunk_size);

            foreach ($chunked_list as $item) {
                $result = 0;

                if ($item instanceof CatalogueProduct) {
                    $result = $this->convertProduct($item);
                } elseif ($item instanceof CatalogueCategory) {
                    $result = $this->convertCategory($item);
                }

                if ($result === $item->ID) {
                    $migrated++;
                }
            }

            $chunked_time = time() - $start_time;

            $this->log(
                "- Migrated {$migrated} of {$items_count} in {$chunked_time}s",
                true
            );

            $curr_chunk++;
        }

        // purge current var
        $chunked_list = null;
    }

    protected function convertProduct(CatalogueProduct $product): int
    {
        if (isset($product->Disabled) && !$product->Disabled && !$product->isPublished()) {
            $product->write();
            $product->publishRecursive();
            return 1;
        }

        return 0;
    }

    protected function convertCategory(CatalogueCategory $category): int
    {
        if (isset($category->Disabled) && !$category->Disabled && !$category->isPublished()) {
            $category->write();
            $category->publishRecursive();
            return 1;
        }

        return 0;
    }

    /**
     * Log a message to the terminal/browser
     * 
     * @param string $message   Message to log
     * @param bool   $linestart Set cursor to start of line (instead of return)
     * 
     * @return null
     */
    protected function log($message, $linestart = false)
    {
        if (Director::is_cli()) {
            $end = ($linestart) ? "\r" : "\n";
            print_r($message . $end);
        } else {
            print_r($message . "<br/>");
        }
    }
}
