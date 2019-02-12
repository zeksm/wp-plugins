<?php

defined("ABSPATH") || die("No direct access allowed!");

class WP_Amazon_Plugin_Product_Helper {
    
    private $API = null;
    private $cache = null;
    
    private $items = [];
    private $atts = [];
    
    private $country;
    
    public function __construct() {
        
        $this->API = $this->get_API();
        $this->cache = $this->get_cache();
        $this->set_country($this->API->get_country());
        
    }
    
    private function get_API() {
        
        if (! $this->API) {        
            require_once __DIR__ . "/API.php";
            $this->API = new WP_Amazon_Plugin_API_Wrapper;
        }
        return $this->API;
        
    }
    
    private function get_cache() {
        
        if (! $this->cache) {        
            require_once __DIR__ . "/cache.php";
            $this->cache = new WP_Amazon_Plugin_Cache_Handler;
        }
        return $this->cache;
        
    }
    
    public function set_country($country) {
        $this->country = $country;
    }
    
    public function set_atts($atts=[]) {
        $this->atts = $atts;
        $this->API->set_atts($atts);
        $this->cache->set_atts($atts);
    }
    
    public function get_items($item_ids) {
        
        $cached_items = [];
        $API_item_ids = [];
        $full_items = [];
        
        foreach ($item_ids as $id) {
            
            $cached_item = $this->cache->get_item($id);
            
            if ($cached_item) {
                $cached_items[$id] = $cached_item;
            } else {
                $API_item_ids[] = $id;
            }
            
        }
        
        if ($API_item_ids) {
            
            $API_items = $this->API->get_items($API_item_ids);
            
            if ($API_items["success"]) {
                
                $API_items = $API_items["content"];
            
                $this->cache->set_items($API_items);
                
                foreach ($item_ids as $id) {
                    
                    if (array_key_exists($id, $cached_items)) {
                        $full_items[] = $cached_items[$id];
                    } elseif (array_key_exists($id, $API_items)) {
                        $full_items[] = $API_items[$id];
                    }
                    
                }
            
            } else {
                return ["success" => False, "content" => $API_items["content"]];
            }
            
        } else {
            $full_items = $cached_items;
        }
        
        return ["success" => True, "content" => $this->parse_items($full_items)];
        
    }
    
    public function parse_items($items) {
        
        $parsed_items = [];
        
        foreach ($items as $item) {
            $parsed_items[] = $this->parse_item_data($item);
        }
        
        return $parsed_items;
        
    }
    
    private function parse_item_data($item) {
                
        $data = [];
        
        $data["ASIN"] = $item["ASIN"];
        $data["title"] = $item["ItemAttributes"]["Title"];
        $data["detailPage"] = $item["DetailPageURL"];
        
        $data["description"] = $this->build_description($item);
        $data["price"] = $this->parse_price_data($item);
        $data["image"] = isset($item["LargeImage"]) ? $item["LargeImage"] : "";
        
        if ($this->atts["reviews"] && isset($item["CustomerReviews"]["HasReviews"]) && $item["CustomerReviews"]["HasReviews"]) {
                
                if (isset($item["CustomerReviews"]["Rating"])) {
                    $data["rating"] = $item["CustomerReviews"]["Rating"];
                    $numeric_rating = (float)explode(" ", $data["rating"])[0];
                    $data["percentage_rating"] = ($numeric_rating/5) * 100;
                }
                
                if (isset($item["CustomerReviews"]["ReviewsNum"])) {
                    $data["reviews_num"] = $item["CustomerReviews"]["ReviewsNum"];
                }
                    
                $data["reviews_url"] = $item["CustomerReviews"]["IFrameURL"];

        }
        
        return $data;
        
    }
    
    private function build_description($item) {
        
        $parts = [];
        
        if (isset($this->atts["description_parts"])) {
            $item_attributes = explode(",", $this->atts["description_parts"]);
            foreach ($item_attributes as $attribute) {
                if (isset($item["ItemAttributes"][$attribute])) {
                    $value = $item["ItemAttributes"][$attribute];
                    if (is_array($value)) {
                        foreach ($value as $val) {
                            $parts[] = $val;
                        }
                    } elseif ($value) {
                        $parts[] = $value;
                    }
                }
            }
            return $parts;
        }
        
        if (isset($item["ItemAttributes"]["Feature"])) {
            $features = $item["ItemAttributes"]["Feature"];
            if (is_array($features)) {
                foreach ($features as $feature) {
                    if ($feature) {
                        $parts[] = $feature;
                    }
                }
            } elseif ($features) {
                $parts[] = $features;
            }
        }
        
        $product_type = $item["ItemAttributes"]["ProductTypeName"];
        
        if ($product_type === "ABIS_BOOK" || $product_type === "ABIS_EBOOKS") {

            if (isset($item["ItemAttributes"]["Author"])) {
                if(is_array($item["ItemAttributes"]["Author"])) {
                    $parts[] = "Authors: " . implode(", ", $item["ItemAttributes"]["Author"]);
                } elseif ($item["ItemAttributes"]["Author"]) {
                    $parts[] =  "Author: " . $item["ItemAttributes"]["Author"];
                }
            }
            if (isset($item["ItemAttributes"]["Binding"])) {
                $parts[] = $item["ItemAttributes"]["Binding"];
            }
            if (isset($item["ItemAttributes"]["NumberOfPages"])) {
                $parts[] = "Pages: " . $item["ItemAttributes"]["NumberOfPages"];
            }
            if (isset($item["ItemAttributes"]["Publisher"])) {
                $parts[] = "Publisher: " . $item["ItemAttributes"]["Publisher"];
            }
            if (isset($item["ItemAttributes"]["Edition"])){
                $parts[] = "Edition: " . $item["ItemAttributes"]["Edition"];
            }
            if (isset($item["ItemAttributes"]["PublicationDate"])) {
                $parts[] = "Publication date: " . $item["ItemAttributes"]["PublicationDate"];
            }
 
        }
        
        elseif ($product_type === "ABIS_MUSIC") {

            if (isset($item["ItemAttributes"]["Artist"])) {
                if(is_array($item["ItemAttributes"]["Artist"])) {
                    $parts[] = "Artists: " . implode(", ", $item["ItemAttributes"]["Artist"]);
                } elseif ($item["ItemAttributes"]["Artist"]) {
                    $parts[] =  "Artist: " . $item["ItemAttributes"]["Artist"];
                }
            }
            if (isset($item["ItemAttributes"]["Title"])) {
                $parts[] = "Title: " . $item["ItemAttributes"]["Title"];
            }
            if (isset($item["ItemAttributes"]["Label"])) {
                $parts[] = "Label: " . $item["ItemAttributes"]["Label"];
            }
            if (isset($item["ItemAttributes"]["Binding"])) {
                $parts[] = $item["ItemAttributes"]["Binding"];    
            }
            if (isset($item["ItemAttributes"]["PublicationDate"])) {
                $parts[] = "Publication date: " . $item["ItemAttributes"]["PublicationDate"];
            } elseif (isset($item["ItemAttributes"]["ReleaseDate"])) {
                $parts[] = "Release date: " . $item["ItemAttributes"]["ReleaseDate"];
            }
        }

        elseif ($product_type === "DOWNLOADABLE_MUSIC_TRACK") {

            if (isset($item["ItemAttributes"]["Creator"])) {
                $parts[] = "Artist: " . $item["ItemAttributes"]["Creator"];
            }
            if (isset($item["ItemAttributes"]["Studio"])) {
                $parts[] = "Studio: " . $item["ItemAttributes"]["Studio"];
            }
            if (isset($item["ItemAttributes"]["Binding"])) {
                $parts[] = $item["ItemAttributes"]["Binding"];
            }
            if (isset($item["ItemAttributes"]["PublicationDate"])) {
                $parts[] = "Publication date: " . $item["ItemAttributes"]["PublicationDate"];
            } elseif (isset($item["ItemAttributes"]["ReleaseDate"])) {
                $parts[] = "Release date: " . $item["ItemAttributes"]["ReleaseDate"];
            }
        }

        elseif ( $product_type === "ABIS_DVD" || $product_type === "DOWNLOADABLE_MOVIE" || $product_type === "DOWNLOADABLE_TV_SEASON") {

            if (isset($item["ItemAttributes"]["Studio"]) && isset($item["ItemAttributes"]["ReleaseDate"])) {
                $parts[] = "Studio: " . $item["ItemAttributes"]["Studio"];
            }
            if (isset($item["ItemAttributes"]["PublicationDate"])) {
                $parts[] = "Publication date: " . $item["ItemAttributes"]["PublicationDate"];
            } elseif (isset($item["ItemAttributes"]["ReleaseDate"])) {
                $parts[] = "Release date: " . $item["ItemAttributes"]["ReleaseDate"];
            }
            if (isset($item["ItemAttributes"]["Binding"]) && isset($item["ItemAttributes"]["AudienceRating"])) {
                $parts[] = $item["ItemAttributes"]["Binding"] . ", " . $item["ItemAttributes"]["AudienceRating"];
            }
            if (isset($item["ItemAttributes"]["RunningTime"])) {
                $parts[] = "Running time: " . $item["ItemAttributes"]["RunningTime"];
            }
        }

        elseif ($product_type === "TOYS_AND_GAMES") {

            if (isset($item["ItemAttributes"]["Binding"])) {
                $parts[] = $item["ItemAttributes"]["Binding"];
            }
            if (isset($item["ItemAttributes"]["Publisher"])) {
                $parts[] = "Publisher: " . $item["ItemAttributes"]["Publisher"];
            }
            if (isset($item["ItemAttributes"]["HardwarePlatform"])) {
                $parts[] = "Hardware platform: " . $item["ItemAttributes"]["HardwarePlatform"];
            }
            if (isset($item["ItemAttributes"]["OperatingSystem"])) {
                $parts[] = "OS: " . $item["ItemAttributes"]["OperatingSystem"];
            } elseif (isset($item["ItemAttributes"]["Platform"])) {
                $parts[] = "OS: " . $item["ItemAttributes"]["Platform"];
            }
            if (isset($item["ItemAttributes"]["PublicationDate"])) {
                $parts[] = "Publication date: " . $item["ItemAttributes"]["PublicationDate"];
            } elseif (isset($item["ItemAttributes"]["ReleaseDate"])) {
                $parts[] = "Release date: " . $item["ItemAttributes"]["ReleaseDate"];
            }
            
        }

        /*else {

            if (isset($item["ItemAttributes"]["Brand"])) {
                $parts[] = "Brand: " . $item["ItemAttributes"]["Brand"];
            }
            if (isset($item["ItemAttributes"]["Manufacturer"])) {
                $parts[] = "Manufacturer: " . $item["ItemAttributes"]["Manufacturer"];
            }
            if (isset($item["ItemAttributes"]["Size"])) {
                $parts[] = "Size: " . $item["ItemAttributes"]["Size"];
            } elseif (isset($item["ItemAttributes"]["ClothingSize"])) {
                $parts[] = "Size: " . $item["ItemAttributes"]["ClothingSize"];
            }
            if (isset($item["ItemAttributes"]["Color"])) {
                $parts[] = "Colour: " . $item["ItemAttributes"]["Color"];
            }
            
        }*/
        
        return $parts;
        
    }
    
    
    private function parse_price_data($item) {

            $price = [];
            
            if ($item["ItemAttributes"]["ProductTypeName"] === "ABIS_EBOOKS") {
                $price["main"] = "To see Kindle ebook price, visit the product page.";
                return $price;
            }
            
            if (isset($item["OfferSummary"]["LowestNewPrice"]["Amount"])) {
                $price["lowestNew"] = $item["OfferSummary"]["LowestNewPrice"];
            }
            if (isset($item["OfferSummary"]["LowestUsedPrice"]["Amount"])) {
                $price["lowestUsed"] = $item["OfferSummary"]["LowestUsedPrice"];
            }

            if (isset ($item["ItemAttributes"]["ListPrice"]["Amount"])) {
                $price["list"] =  $item["ItemAttributes"]["ListPrice"];
            } else if (isset($item["Offers"]["Offer"]["OfferListing"]["Price"]["Amount"])) {
                $price["list"] = $item["Offers"]["Offer"]["OfferListing"]["Price"];
            } elseif (isset($price["lowestNew"])) {
                $price["list"] = ["lowestNew"];
            }
            
            if (isset ($item["VariationSummary"])) {
                if (isset ($item["VariationSummary"]["LowestSalePrice"]["Amount"])) {
                    $price["variationLowest"] = $item["VariationSummary"]["LowestSalePrice"];
                } elseif (isset($item["VariationSummary"]["LowestPrice"]["Amount"])) {
                    $price["variationLowest"] = $item["VariationSummary"]["LowestPrice"];
                }
                if (isset ($item["VariationSummary"]["HighestSalePrice"]["Amount"])) {
                    $price["variationHighest"] = $item["VariationSummary"]["HighestSalePrice"];
                } elseif (isset($item["VariationSummary"]["HighestPrice"]["Amount"])) {
                    $price["variationHighest"] = $item["VariationSummary"]["HighestPrice"];
                }   
            }

            if (isset($item["Offers"]["Offer"])) {
                $offers = $item["Offers"]["Offer"];
            }
            elseif (isset($item["Variations"]["Item"]["Offers"]["Offer"])) {
                $offers = $item["Variations"]["Item"]["Offers"]["Offer"];
            }
            if(isset($offers)) {
                $availability = $offers["OfferAttributes"]["Condition"];
                if(isset($offers["OfferListing"]["Price"])) {
                    $price["offer"] = $offers["OfferListing"]["Price"]["FormattedPrice"];
                }
                if(isset($offers["OfferListing"]["AmountSaved"])) {
                    $price["savedAmount"][$availability] = $offers["OfferListing"]["AmountSaved"];
                }
                if(isset($offers["OfferListing"]["PercentageSaved"])) {
                    $price["savedPercentage"][$availability] = $offers["OfferListing"]["PercentageSaved"];
                }
                if(isset($offers["OfferListing"]["SalePrice"]["Amount"])) {
                    $price["sale"] = $offers["OfferListing"]["SalePrice"];
                } elseif (isset($offers["Merchant"]["Name"]) && (strpos($offers["Merchant"]["Name"], "Amazon") !== false)) {
                    if (isset($offers["OfferListing"]["Price"]["Amount"]) && ! ($price["list"] === $offers["OfferListing"]["Price"]["Amount"])) {
                        $price["sale"] = $offers["OfferListing"]["Price"];
                    }
                }
                if(isset($offers["OfferListing"]["IsEligibleForPrime"]) && $offers["OfferListing"]["IsEligibleForPrime"] == "1") {
                    $price["prime"] = True;
                }
            }
                        
            $price = $this->find_correct_prices_to_display($price);

            return $price;
            
        } 
         
        private function find_correct_prices_to_display($price) {
            
            if (isset($price["sale"])) {
                $price["main"] = $price["sale"]["FormattedPrice"];
                if (isset($price["savedAmount"]) && isset($price["list"])) {
                    $price["saved"] = $price["savedAmount"]["New"]["FormattedPrice"];
                    $price["percentage"] = $price["savedPercentage"]["New"];
                    $price["old"] = $price["list"]["FormattedPrice"];
                }
            } elseif (isset($price["lowestNew"])) {
                    $price["main"] = "from " . $price["lowestNew"]["FormattedPrice"];
            } elseif (isset($price["offer"])) {
                    $price["main"] = $price["offer"]["FormattedPrice"];
            } elseif (isset($price["variationLowest"]) && isset($price["variationHighest"])) {
                if ($price["variationLowest"]["FormattedPrice"] === $price["variationHighest"]["FormattedPrice"]) {
                    $price["main"] = $price["variationLowest"]["FormattedPrice"];
                } else {
                    $price["main"] = $price["variationLowest"]["FormattedPrice"] . " - " . $price["variationHighest"]["FormattedPrice"];
                }
            } 
                /*if (isset($price["savedAmount"]) && isset($price["list"])) {
                    $price["saved"] = $price["savedAmount"]["New"]["FormattedPrice"];
                    $price["old"] = $price["list"]["FormattedPrice"];
                }*/
            
            return $price;
            
        }
        
        public function get_list($list_type, $value, $type="node", $search_index=null) {
            
            $cached_list = $this->cache->get_list($list_type, $value, $type, $search_index);
            
            if ($cached_list) {
                $item_asins = $cached_list;
            } else {
                
                if ($list_type === "new") {
                    $item_asins = $this->API->get_new_releases_list($value, $type);
                } elseif ($list_type === "best") {
                    $item_asins = $this->API->get_bestseller_list($value, $type, $search_index);
                }
                
                if (! $item_asins["success"]) {
                    return ["success" => False, "content" => $item_asins["content"]];
                } else {
                    $item_asins = $item_asins["content"];
                }
                
                $this->cache->set_list($item_asins, $list_type, $value, $type, $search_index);
                
            }
            
            if ($this->atts["itemcache"]) {
                $this->atts["cache"] = $this->atts["itemcache"];
                $this->set_atts($this->atts);
            }
            
            return $this->get_items($item_asins);
            
        }
    
}