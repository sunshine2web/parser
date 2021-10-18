<?php

namespace App\Feeds\Processor;

use App\Feeds\Utils\Data;
use App\Feeds\Utils\Link;
use Symfony\Component\DomCrawler\Crawler;

abstract class SitemapHttpProcessor extends HttpProcessor
{
    /**
     * An array of css selectors that select elements of links (<a>) to product categories for their further traversal
     */
    public const CATEGORY_LINK_CSS_SELECTORS = [ 'sitemap loc' ];
    /**
     * An array of css selectors that select link elements (<a>) to product pages to collect information from them
     */
    public const PRODUCT_LINK_CSS_SELECTORS = [ 'loc' ];

    /**
     * Returns all links to category pages that were found by the selectors specified in the constant "CATEGORY_LINK_CSS_SELECTORS"
     * @param Data $data Html markup of the loaded page
     * @param string $url the url of the loaded page
     * @return array An array of links containing app/Feeds/Utils/Link objects
     */
    public function getCategoriesLinks( Data $data, string $url ): array
    {
        $result = [];
        $crawler = new Crawler( $data->getData() );

        foreach ( static::CATEGORY_LINK_CSS_SELECTORS as $css ) {
            if ( $links = $crawler->filter( $css )->each( static function ( Crawler $node ) {
                return new Link( $node->text() );
            } ) ) {
                array_push( $result, ...$links );
            }
        }

        return $result;
    }

    /**
     * Returns all links to product pages that were found by the selectors specified in the constant "PRODUCT_LINK_CSS_SELECTORS"
     * @param Data $data Html markup of the loaded page
     * @param string $url the url of the loaded page
     * @return array An array of links containing app/Feeds/Utils/Link objects
     */
    public function getProductsLinks( Data $data, string $url ): array
    {
        if ( preg_match_all( '/<loc>([^<]*)<\/loc>/m', $data->getData(), $matches ) ) {
            $links = array_map( static fn( $url ) => new Link( htmlspecialchars_decode( $url ) ), $matches[ 1 ] );
        }
        $tempLinks = [];
        //$tempLinks[] = $links[0];
        //$tempLinks[] = $links[1];
        //$tempLinks[] = $links[2];
        //$tempLinks[] = new Link('https://www.tashaapparel.com/Yellow-Underwire-Halter-Neck-Top-Swimsuit-p/448212-yellow(6).htm');
        $tempLinks[] = new Link('https://www.tashaapparel.com/Grey-Fuzzy-Knit-Open-Front-Sash-Belt-Cardigan-p/t4929-grey(6).htm');
        //$tempLinks[] = new Link('https://www.tashaapparel.com/Red-Eyelet-Crochet-Embroidery-Puff-Sleeve-Top-p/hc448-red.htm');
        //$tempLinks[] = new Link('https://www.tashaapparel.com/Orange-Lace-Bodysuit-Flare-Maxi-Leg-Jumpsuit-p/1001j-orange(6).htm');
        //$tempLinks[] = new Link('https://www.tashaapparel.com/White-Crochet-Trim-Button-Down-Boho-Top-p/ta20559-white.htm');
        //$tempLinks[] = new Link('https://www.tashaapparel.com/Gold-Pearled-Elegant-Style-Bobby-Pins-p/mmh7046-gold(12).htm');
        //$tempLinks[] = new Link('https://www.tashaapparel.com/White-Crochet-Trim-Button-Down-Boho-Top-p/ta20559-white.htm');
        //$tempLinks[] = new Link('https://www.tashaapparel.com/Adobe-Underwire-Halter-Neck-Top-Swimsuit-p/448212-adobe.htm');
        $links = $tempLinks;
        return array_values( array_filter( $links ?? [], [ $this, 'filterProductLinks' ] ) );
    }
}