<?php

namespace App\Feeds\Vendors\TAS;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\SitemapHttpProcessor;
use App\Feeds\Utils\Link;

class Vendor extends SitemapHttpProcessor
{
    protected array $first = [ 'https://www.tashaapparel.com/sitemap.xml' ];
    
    public function filterProductLinks( Link $link ): bool
    {
        $isValid = stripos( $link->getUrl(), '/articles.asp') === false
            && stripos( $link->getUrl(), '/help_answer.asp') === false
            && str_ends_with($link->getUrl(), '.htm');
        return $isValid;
    }

    protected function isValidFeedItem( FeedItem $fi ): bool
    {
        return count( $fi->getImages() ) && $fi->getListPrice() > 0;
    }
}
