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
        if ( $this->exists( '.product_productprice' ) ) {
            return $this->getMoney( '.product_productprice' );
        }

        return StringHelper::getMoney( $this->getAttr( 'meta[ itemprop="price"]', 'content' ) );
    }

    public function getCostToUs(): float
    {
        return $this->getListPrice();
    }

    public function getDescription(): string
    {
        $description = trim( $this->getText( '[itemprop="description"]' ) );
        if ( $description === '' ) {
            $description = trim( $this->getText( '#details [style="font-size: 12pt; font-family: Arial;"]' ) );
        }
        return $description;
    }

    public function getImages(): array
    {
        return array_values( array_unique( $this->getLinks( 'span#altviews > a' ) ) );
    }

    public function getAvail(): ?int
    {
        $html = $this->html();
        preg_match("/Quantity.in.Stock:(\d+)</im", $html, $matches);
        if (isset($matches[1])) {
            return $matches[1];
        }
        return self::DEFAULT_AVAIL_NUMBER;
    }

    public function getOptions(): array
    {
        $options = [];
        if (! $this->exists( '[itemprop="offers"]' )) {
            return $options;
        }
        $offers = $this->filter( '[itemprop="offers"]' )->html();

        foreach (explode( '<br>', $offers ) as $o) {
            $item = trim($o);
            preg_match("/<b>(.*?):<\/b>(.*)/im", $item, $matches);
            if (isset($matches[2])) {
                $name = trim($matches[1]);
                $v = trim($matches[2]);
                $validNames = ['Made In', 'Fabric', 'Color', 'Size Ratio', 'Package']; 
                if (in_array($name, $validNames)) {
                    $options[ $name ][] = $v;
                }
            }
        }
        return $options;
    }
}
