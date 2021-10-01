<?php

namespace App\Feeds\Vendors\TAS;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\HttpProcessor;
use App\Feeds\Utils\Data;
use App\Feeds\Utils\Link;
use App\Feeds\Utils\ParserCrawler;

class Vendor extends HttpProcessor
{
    protected array $first = [ 'https://www.tashaapparel.com/Wholesale-New-Dresses-s/398.htm' ];

    public function getCategoriesLinks( Data $data, string $url ): array
    {
        $links = [];
        $links[] = "https://www.tashaapparel.com/Wholesale-New-Dresses-s/398.htm?searching=Y&sort=3&cat=398&show=360&page=1";
        return array_merge($links, parent::getCategoriesLinks($data, $url) );
    }

    public function getProductsLinks( Data $data, string $url ): array
    {
        return array_merge(
            [
                'https://www.tashaapparel.com/Junior-Plus-Navy-Multi-Paisley-Print-Tunic-Top-p/wpw10156a-navy-multi.htm'
            ], parent::getProductsLinks($data, $url) );
    }

    protected function isValidFeedItem( FeedItem $fi ): bool
    {
        return count( $fi->getImages() ) && $fi->getListPrice() > 0;
    }
}
