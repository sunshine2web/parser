<?php

namespace App\Feeds\Vendors\TAS;

use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\StringHelper;

class Parser extends HtmlParser
{
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
        $d = trim( $this->getHtml( '[itemprop="description"]' ) ) ?: trim( $this->getHtml( '#product_description' ) );
        if ( $this->exists( '#ProductDetail_ProductDetails_div2' ) ) {
            $d .= '<br />' . StringHelper::removeSpaces( $this->getHtml( '#ProductDetail_ProductDetails_div2 table table td' ) );
        }
        return StringHelper::isNotEmpty($d) ? trim($d) : $this->getProduct();
    }

    public function getImages(): array
    {
        return array_values( array_unique( $this->getLinks( 'span#altviews > a' ) ) );
    }

    public function getAvail(): ?int
    {
        preg_match("/Quantity.in.Stock:(\d+)</im", $this->node->html(), $matches);
        if ( !isset($matches[1]) ) {
            return stripos( $this->getAttr( 'meta[ itemprop="availability"]', 'content' ), 'InStock' ) ? self::DEFAULT_AVAIL_NUMBER : 0;
        }
        return $matches[1] ?? self::DEFAULT_AVAIL_NUMBER;
    }

    public function getAttributes(): ?array
    {
        $attributes = [];
        if (! $this->exists( '[itemprop="offers"]' )) {
            return null;
        }
        $offers = $this->filter( '[itemprop="offers"]' )->html();
        $valid_names = ['Made In', 'Fabric', 'Color', 'Size Ratio', 'Package']; 

        foreach (explode( '<br>', $offers ) as $o) {
            $item = trim($o);
            preg_match("/<b>(.*?):<\/b>(.*)/im", $item, $matches);
            if (isset($matches[2])) {
                $name = trim($matches[1]);
                $v = trim($matches[2]);
                if (in_array($name, $valid_names)) {
                    $attributes[ $name ] = $v;
                }
            }
        }
        
        return empty($attributes) ? null : $attributes;
    }

    public function getOptions(): array
    {
        $options = [];
        
        $this->filter( '#options_table select' )->each( function ( ParserCrawler $node ) use ( &$options ) {
            $name_option = '';
            foreach( $node->getContent( 'option' ) as $option ) {
                if ( stripos( $option, 'select' ) !== false ) {
                    $name_option = StringHelper::normalizeSpaceInString( str_ireplace( 'select', '', $option ) );
                    break;
                }
            }
            preg_match('/makeComboGroup\("' . $node->attr( 'name' )  .  '.*?TCN_reload/s', $this->node->html(), $matches);
            preg_match_all('/TCN_addContent\(\"(.*?)\+/i', $matches[0], $matches);
            if (!empty($matches[1])) {
                $options[ $name_option ] = $matches[1];
            }
        });
        return $options;
    }
}
