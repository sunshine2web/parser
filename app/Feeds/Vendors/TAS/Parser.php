<?php

namespace App\Feeds\Vendors\TAS;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\StringHelper;

class Parser extends HtmlParser
{
    private bool $extra_product = false;

    public function getMpn(): string
    {
        return $this->getText( '.product_code' );
    }

    public function getProduct(): string
    {
        return trim( $this->getAttr( 'meta[property="og:title"]', 'content' ) );
    }

    public function getListPrice(): ?float
    {
        return $this->getMoney( '.product_productprice' ) ?: StringHelper::getMoney( $this->getAttr( 'meta[ itemprop="price"]', 'content' ) );
    }

    public function getCostToUs(): float
    {
        return $this->getListPrice();
    }

    public function getDescription(): string
    {
        $d = $this->extractDescription( '#ProductDetail_ProductDetails_div table' );
        if ( empty( $d ) ) {
            $d = $this->extractDescription( '#product_description' );
        }
        $d .= $this->extractDescription( '#ProductDetail_ProductDetails_div2 table tr td table tr td', true );
        return StringHelper::isNotEmpty( $d ) ? trim( $d ) : $this->getProduct();
    }

    private function extractDescription( $selector, $add_line_break = false ): string 
    {
        $result = '';
        if ( $this->exists( $selector ) ) {
            $result = strip_tags( $this->filter( $selector )->outerHtml(), [ 'p', 'li', 'ul', 'br' ] );
            $result = StringHelper::cutTagsAttributes($result);
            $result = preg_replace( '/🖤 Item(.*?)Measurements:(.*?)</im', '<', $result );
            $result = preg_replace( '/Item(.*?)Measurements:(.*?)</im', '<', $result );
            $result = preg_replace( '/❓Questions about fit?(.*?)tashaapparel.com/im', '', $result );
            $result = preg_replace( '/Questions about fit(.*?)tashaapparel.com/im', '', $result );
            
        }
        if ($result != '' && $add_line_break) {
            $result = '<br>' . $result;
        }
        return $result;
    }

    private function extractItemMeasurements( $selector ): array 
    {
        $result = '';
        if ( $this->exists( $selector ) ) {
            $this->filter( $selector )->each( function ( ParserCrawler $node ) use ( &$result ) {
                $re = ["/Measurements:(.*?)<br/im", "/Measurements:(.*?)<\//im"];
                foreach ( $re as $r ) {
                    if ( empty( $result ) ) {
                        preg_match( $r, $node->html(), $matches );
                        if ( isset( $matches[1] ) ) {
                            $result = trim( strip_tags( $matches[1] ) );
                        }        
                    }
                }
            });

        }
        $attributes = [];
        preg_match_all( "/(.*?):(.*?)\"/i", $result, $matches );
        if ( count( $matches ) == 3 && count( $matches[1] ) > 0 ) {
            for ( $i = 0; $i < count( $matches[1] ); $i++ ) {
                $name = trim ( str_replace(",", "", $matches[1][$i] ) );
                $attributes[ $name ] = trim( $matches[2][$i] ) . '"';
            }
        }

        return $attributes;
    }

    public function getImages(): array
    {
        return array_values( array_unique( $this->getLinks( 'span#altviews > a' ) ) );
    }

    public function getAvail(): ?int
    {
        preg_match( "/Quantity.in.Stock:(\d+)</im", $this->node->html(), $matches );
        if ( !isset( $matches[ 1 ] ) ) {
            return stripos( $this->getAttr( 'meta[ itemprop="availability"]', 'content' ), 'InStock' ) ? self::DEFAULT_AVAIL_NUMBER : 0;
        }
        return $matches[ 1 ] ?? self::DEFAULT_AVAIL_NUMBER;
    }

    public function getAttributes(): ?array
    {
        $attributes = [];
        if ( !$this->exists( '[itemprop="offers"]' ) ) {
            return null;
        }
        $offers = $this->filter( '[itemprop="offers"]' )->html();
        $valid_names = [ 'Made In', 'Fabric', 'Color', 'Size Ratio', 'Package' ];

        foreach ( explode( '<br>', $offers ) as $o ) {
            $item = trim( $o );
            preg_match( "/<b>(.*?):<\/b>(.*)/im", $item, $matches );
            if ( isset( $matches[ 2 ] ) ) {
                $name = trim( $matches[ 1 ] );
                $v = trim( $matches[ 2 ] );
                if ( in_array( $name, $valid_names ) ) {
                    $attributes[ $name ] = $v;
                }
            }
        }

        $item_measurements_selectors = [ '#ProductDetail_ProductDetails_div2 table tr td table tr td', '#ProductDetail_ProductDetails_div table tr', '#product_description' ];
        foreach ( $item_measurements_selectors as $s) {
            $item_measurements = $this->extractItemMeasurements( $s );
            if ( count( $item_measurements ) ) {
                $attributes = array_merge( $attributes, $item_measurements);
                break;
            }     
        }

        return empty( $attributes ) ? null : $attributes;
    }

    public function afterParse( FeedItem $fi ): void
    {
        if ( $this->extra_product ) {
            $fi->setCostToUs( 0 );
        }
    }

    public function getOptions(): array
    {
        $find_empty_option = false;
        $options = [];

        $this->filter( '#options_table select' )->each( function ( ParserCrawler $node ) use ( &$options, &$find_empty_option ) {
            $name_option = '';
            foreach ( $node->getContent( 'option' ) as $option ) {
                if ( stripos( $option, 'select' ) !== false ) {
                    $name_option = StringHelper::normalizeSpaceInString( str_ireplace( 'select', '', $option ) );
                    break;
                }
            }
            preg_match( '/makeComboGroup\("' . $node->attr( 'name' ) . '.*?TCN_reload/s', $this->node->html(), $matches );
            preg_match_all( '/TCN_addContent\(\"(.*?)\+/i', $matches[ 0 ], $matches );
            if ( !empty( $matches[ 1 ] ) ) {
                $options[ $name_option ] = $matches[ 1 ];
            }
            else {
                $find_empty_option = true;
            }
        } );

        if ( $find_empty_option && $this->exists( '#options_table select' ) ) {
            $this->extra_product = true;
        }

        return $options;
    }
}
