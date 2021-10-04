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
        return trim( $this->getText( '[itemprop="description"]' ) ) ?: trim( $this->getText( '#details [style="font-size: 12pt; font-family: Arial;"]' ) );
    }

    public function getImages(): array
    {
        return array_values( array_unique( $this->getLinks( 'span#altviews > a' ) ) );
    }

    public function getAvail(): ?int
    {
        preg_match("/Quantity.in.Stock:(\d+)</im", $this->node->html(), $matches);
        return $matches[1] ?? self::DEFAULT_AVAIL_NUMBER;
    }

    public function getAttributes(): array
    {
        $attributes = [];
        if (! $this->exists( '[itemprop="offers"]' )) {
            return $attributes;
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
        return $attributes;
    }

    public function getOptions(): array
    {
        $options = [];
        if (!$this->exists( 'table#options_table' )) 
        {
            return $options;
        }
        
        $table = $this->filter( 'table#options_table' )->first();
        $name = trim($table->text());
        if (stripos($name, 'size*') !== false && count($table->filter('select'))) {
            $name = 'Size';
            $select_name = $table->filter('select')->attr('name');
            $html = $this->html();
            preg_match("/makeComboGroup\(\"" . $select_name .  "(.*?)TCN_reload/is", $html, $matches);
            if (isset($matches[1])) {
                foreach( explode("\n", $matches[1]) as $line ) {
                    preg_match("/TCN_addContent\(\"(.*?)\+/i", $line, $line_matches);
                    if (isset($line_matches[1])) {
                        $options[ $name ][] = $line_matches[1];
                    }
                }
            }
        }    
        return $options;
    }
}
