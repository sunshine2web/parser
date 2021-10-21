<?php

namespace App\Feeds\Vendors\TAS;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\StringHelper;

class Parser extends HtmlParser
{
    private bool $extra_product = false;
    private string $desc = '';
    private array $attributes = [];

    private function pushAttributes(): void
    {
        preg_match_all( '/<b>.*?<br>/', $this->getHtml( '[itemprop="offers"]' ), $matches );

        foreach ( $matches[ 0 ] as $match ) {
            [ $key, $value ] = array_map( static fn( $el ) => StringHelper::trim( strip_tags( $el ) ), explode( ':', $match ) );
            $this->attributes[ $key ] = $value;
        }
    }

    private function pushDescAndAttributes(): void
    {
        $this->desc = $this->getHtml( '#ProductDetail_ProductDetails_div' );
        $this->desc .= '<br>' . $this->getHtml( '#ProductDetail_ProductDetails_div2' );
        $this->desc = strip_tags( $this->desc, [ 'p', 'li', 'ul', 'br', 'div' ] );

        //search measurements attributes in desc
        if ( preg_match( '/(🖤*\s*item measurements:(.*?))<(\/)*.*?>/ui', $this->desc, $matches ) && str_contains( $matches[ 2 ], ':' ) ) {
            $this->desc = str_ireplace( $matches[ 1 ], '', $this->desc );

            //if it found, do explode on attributes in measurements value
            //example: item measurements: Attr1: Value1, Attr2: Value2 ...
            preg_match_all( '/,*(.*?:\s*(\d*[.\d+]*)"*)/', $matches[ 2 ], $matches );
            foreach ( $matches[ 1 ] as $match ) {
                [ $key, $value ] = array_map( static fn( $el ) => StringHelper::trim( $el ), explode( ':', $match ) );
                $this->attributes[ $key ] = $value;
            }
        }

        $this->desc = preg_replace( '/<br>\s*<br>/', '<br>', $this->replaceInvalidPartsFromDesc( $this->desc ) );
    }

    private function replaceInvalidPartsFromDesc( string $desc ): string
    {
        $invalid_parts = [
            '/email: info@tashaapparel\.co(m)*/i',
            '/Questions about fit❓/iu',
            '/❓Questions about fit/iu',
            '/Please follow our social media\./i',
        ];

        return preg_replace( $invalid_parts, '', $desc );
    }

    public function beforeParse(): void
    {
        $this->pushDescAndAttributes();
        $this->pushAttributes();
    }

    public function getMpn(): string
    {
        return $this->getText( '.product_code' );
    }

    public function getCostToUs(): float
    {
        return $this->getListPrice();
    }

    public function getListPrice(): ?float
    {
        return $this->getMoney( '.product_productprice' ) ?: StringHelper::getMoney( $this->getAttr( 'meta[ itemprop="price"]', 'content' ) );
    }

    public function getDescription(): string
    {
        return StringHelper::isNotEmpty( $this->desc ) ? $this->desc : $this->getProduct();
    }

    public function getProduct(): string
    {
        return trim( $this->getAttr( 'meta[property="og:title"]', 'content' ) );
    }

    public function getImages(): array
    {
        return array_values( array_unique( $this->getLinks( 'span#altviews > a' ) ) );
    }

    public function getAvail(): ?int
    {
        preg_match( "/Quantity.in.Stock:(\d+)</im", $this->node->html(), $matches );

        if ( !isset( $matches[ 1 ] ) ) {
            return stripos( $this->getAttr( 'meta[ itemprop="availability"]', 'content' ), 'InStock' ) !== false ? self::DEFAULT_AVAIL_NUMBER : 0;
        }

        return $matches[ 1 ] ?? self::DEFAULT_AVAIL_NUMBER;
    }

    public function getAttributes(): ?array
    {
        return array_filter( $this->attributes ) ?: null;
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
