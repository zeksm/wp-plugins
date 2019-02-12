<?php 

defined( "ABSPATH" ) || die( "No direct access allowed!" );

require_once __DIR__ . "/../lib/vendor/autoload.php";

use ApaiIO\ApaiIO;

class WP_Amazon_Plugin_API_Wrapper {
    
    private $country = "co.uk";
    
    private $API_key = "AKIAI4UOG735UNCTH5SQ";
    private $API_secret_key = "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";
    private $associate_tag = "secretsales-21";
    
    private $API = null;
    
    private $atts = [];
    
    public function __construct() {
        
        $configuration = new \ApaiIO\Configuration\GenericConfiguration();

        $configuration->setCountry($this->country);
        $configuration->setAccessKey($this->API_key);
        $configuration->setSecretKey($this->API_secret_key);
        $configuration->setAssociateTag($this->associate_tag);
        
        $client = new \GuzzleHttp\Client();
        $request = new \ApaiIO\Request\GuzzleRequest($client);
        $configuration->setRequest($request);
        
        $configuration->setResponseTransformer(new \ApaiIO\ResponseTransformer\XmlToArray());

        try {
            $this->API = new ApaiIO($configuration);
        } catch (Exception $e) {
            //echo $e->getMessage();
        }
        
    }
    
    public function get_country() {
        return $this->country;
    }
    
    public function set_atts($atts=[]) {
        $this->atts = $atts;
    }
    
    private function check_response_for_errors($response, $product_type) {
        
        if (isset( $response["Error"])) {
            return ["success" => False, "content" => "Error:" . $response["Error"]];
        }
        
        if (isset($response[$product_type]["Request"]["IsValid"]) && $response[$product_type]["Request"]["IsValid"] == True) {
            
            if (isset($response[$product_type]["Request"]["Errors"]["Error"])) {
                $error = $response[$product_type]["Request"]["Errors"]["Error"];
                if (isset($error["Message"])) {
                    return ["success" => False, "content" => $error["Message"]];
                } elseif (is_array($error)) {
                    $content = "";
                    foreach($error as $e) {
                        if (isset($e["Message"])) {
                            $content .= $e["Message"] . "<br>";
                        }
                    }
                    return ["success" => False, "content" => $content];
                } 
            } else {
                return ["success" => True, "content" => $response];
            }

        } else {
            return ["success" => False, "content" => "Error"];
        }
        
    }
    
    public function get_items(Array $item_ids, $response_group=[], $reviews=True) {
        
        $item_ids = array_chunk( $item_ids, 10 );
        
        $items_data = [];
        
        foreach($item_ids as $item_ids_chunk) {
            
            $item_ids_string = implode(",", $item_ids_chunk);
        
            $lookup = new \ApaiIO\Operations\Lookup();
            $lookup->setItemId($item_ids_string);
            if (! $response_group) {
                $response_group = ["ItemAttributes", "Small", "EditorialReview","Images", "OfferFull", "Reviews", /*"Similarities",*/ "SalesRank", "VariationOffers"];
            }
            $lookup->setResponseGroup($response_group);
            
            try {
                $response = $this->API->runOperation($lookup);
            } catch (Exception $e) {
                return ["success" => False, "content" => $e->/*getMessage()];//*/getResponse()->getBody()->getContents()];
            }
            
            $response = $this->check_response_for_errors($response, "Items");
            
            if ($response["success"]) {
                
                $response = $response["content"];
                
                $items = [];
                if (isset($response["Items"]["Item"][0]["ASIN"])) {
                    foreach ($response["Items"]["Item"] as $item) {
                        $items[] = $item;
                    }
                } elseif (isset($response["Items"]["Item"]["ASIN"])) {
                    $items[] = $response["Items"]["Item"];
                }
                
                foreach ($items as $item) {
                    
                    if (empty($item["LargeImage"])) {
                        if (! empty($item["Variations"]["Item"]["ASIN"])) {
                            $variant_ASIN = $item["Variations"]["Item"]["ASIN"];
                        } elseif (! empty($item["Variations"]["Item"][0]["ASIN"])) {
                            $variant_ASIN = $item["Variations"]["Item"][0]["ASIN"];
                        }
                        if (isset($variant_ASIN)) {
                            $variation = $this->get_items([$variant_ASIN], ["Images"], False);
                            if ($variation["success"]) {
                                $variation = $variation["content"];
                                if ($variation && isset($variation[$variant_ASIN]["LargeImage"])) {
                                    $item["LargeImage"] = $variation[$variant_ASIN]["LargeImage"];
                                }
                            }
                        }
                    }
                    
                    if ($reviews && $this->atts["reviews"] && isset($item["CustomerReviews"]["HasReviews"]) && $item["CustomerReviews"]["HasReviews"]) {
                        $reviews_info = $this->get_reviews_info($item["ASIN"], $item["CustomerReviews"]["IFrameURL"]);
                        if ($reviews_info) {
                            if (isset($reviews_info["rating"])) {
                                $item["CustomerReviews"]["Rating"] = str_replace(" stars", "", $reviews_info["rating"]);
                            }
                            if (isset($reviews_info["reviews_num"])) {
                                $item["CustomerReviews"]["ReviewsNum"] = $reviews_info["reviews_num"];
                            }
                        }
                    }
                                
                    $items_data[$item["ASIN"]] = $item;
                    
                }
                
            } else {
                return $response;
            }
            
        }
        
        return ["success" => True, "content" => $items_data];
        
    }
    
    private function search($terms, $response_groups=[], $index="All", $sort=null) {
        
        $search = new \ApaiIO\Operations\Search();
        $search->setKeywords($terms);
        $search->setResponseGroup($response_groups);
        if (isset($sort)) {
            $search->setSort($sort);
            $search->setCategory($index);
        } else {
            $search->setCategory("All");
        }
        
        try {
            $response = $this->API->runOperation($search);
        } catch (Exception $e) {
            return ["success" => False, "content" => $e->/*getMessage()];//*/getResponse()->getBody()->getContents()];
        }
        
        $response = $this->check_response_for_errors($response, "Items");
        
        return $response;
        
    }
    
    private function lookup_browsenode($id, $response_group) {
        
        $lookup = new \ApaiIO\Operations\BrowseNodeLookup();
        $lookup->setNodeId($id);
        $lookup->setResponseGroup($response_group);

        try {
            $response = $this->API->runOperation($lookup);
        } catch (Exception $e) {
            return ["success" => False, "content" => $e->/*getMessage()];//*/getResponse()->getBody()->getContents()];
        }
        
        $response = $this->check_response_for_errors($response, "BrowseNodes");
        
        return $response;
        
    }
    
    private function find_node($term) {
        
        $term = strtolower($term);
        
        $search_results = $this->search($term, ["BrowseNodes"]);
        
        if (! $search_results["success"]) {
            return False;
        }
        
        $search_results = $search_results["content"];
        
        if (isset($search_results["Items"]["Item"])) {
            
            $nodes = [];
            foreach ($search_results["Items"]["Item"] as $item) {
                                
                if (isset($item["BrowseNodes"]["BrowseNode"])) {
                    if (isset($item["BrowseNodes"]["BrowseNode"][0])) {
                        foreach ($item["BrowseNodes"]["BrowseNode"] as $node) {
                            $nodes[] = $node;
                        }
                    } else {
                        $nodes[] = $item["BrowseNodes"]["BrowseNode"];
                    }
                }
                
            }
                
            $exact_match = [];
            $best_distance = 0;
            $partial_match = [];
            $backup_partial_match = [];
            
            foreach ($nodes as $node) {
                
                $current_node = $node;
                
                while (True) {
                    
                    if (!isset($current_node["Name"]) || !isset($current_node["BrowseNodeId"])) {
                        $partial_match = [];
                        $exact_match = [];
                        break;
                    }
                    
                    $current_node_name = strtolower($current_node["Name"]);
                    
                    if ($current_node_name === $term) {
                        $exact_match["Name"] = $current_node["Name"];
                        $exact_match["Id"] = $current_node["BrowseNodeId"];
                    } else {
                        $distance = levenshtein($term, $current_node_name);
                        if (!$best_distance || ($best_distance > $distance)) {
                            $best_distance = $distance;
                            $partial_match["Name"] = $current_node["Name"];
                            $partial_match["Id"] = $current_node["BrowseNodeId"];
                        }
                    }
                    
                    if (!isset($current_node["Ancestors"]["BrowseNode"])) {
                        break;
                    } else {
                        $current_node = $current_node["Ancestors"]["BrowseNode"];
                    }
                    
                };
                    
                if ($exact_match) {
                    break;
                } elseif ($partial_match) {
                    $backup_partial_match = $partial_match;
                }
                    
            }
            
            if ($exact_match) {
                $id = $exact_match["Id"];
            } elseif ($partial_match) {
                $id = $partial_match["Id"];
            } elseif ($backup_partial_match) {
                $id = $backup_partial_match["Id"];
            } else {
                $id = False;
            }
            
            return $id;
            
        } else {
            return False;
        }
        
    }
    
    public function get_bestseller_list($value, $type="node", $search_index=null) {
        
        if ($type == "node") {
            $node = $value;
        } elseif ($type == "nodesearch") {
            $node = $this->find_node($value);
        } elseif ($type == "search") {
            $search_terms = $value;
        }
        
        if (isset($node) && $node) {
            
            $response = $this->lookup_browsenode($node, ["TopSellers"]);
            
            if ($response["success"]) {
                
                $response = $response["content"];
            
                $item_asins = [];
            
                if (isset($response["BrowseNodes"]["BrowseNode"]["TopSellers"]["TopSeller"])) {
                    foreach ($response["BrowseNodes"]["BrowseNode"]["TopSellers"]["TopSeller"] as $item) {
                        if (isset($item["ASIN"])) {
                            $item_asins[] = $item["ASIN"];
                        }
                    }
                }
                
                return ["success" => True, "content" => $item_asins];
            
            }
            
        } elseif (isset($search_terms) && isset($search_index)) {
            
            $response = $this->search($search_terms, ["ItemIds"], $search_index, "salesrank");
            
            if ($response["success"]) {
                
                $response = $response["content"];
            
                $item_asins = [];
                
                if (isset($response["Items"]["Item"])) {
                    foreach ($response["Items"]["Item"] as $item) {
                        if (isset($item["ASIN"])) {
                            $item_asins[] = $item["ASIN"];
                        }
                    }
                }
                
                return ["success" => True, "content" => $item_asins];
            
            }
            
        }

        return ["success" => False, "content" => "No valid attributes present"];

    }
    
    public function get_new_releases_list($value, $type="node") {
        
        if ($type == "node") {
            $node = $value;
        } elseif ($type == "nodesearch") {
            $node = $this->find_node($value);
        }
        
        if ($node) {
        
            $response = $this->lookup_browsenode($node, ["NewReleases"]);
            
            if ($response["success"]) {
                
                $response = $response["content"];
            
                $item_asins = [];
                
                if (isset($response["BrowseNodes"]["BrowseNode"]["NewReleases"]["NewRelease"])) {

                    if (isset($response["BrowseNodes"]["BrowseNode"]["NewReleases"]["NewRelease"]["ASIN"])) {
                        $items_asins = ["ASIN" => $response["BrowseNodes"]["BrowseNode"]["NewReleases"]["NewRelease"]["ASIN"]];
                    } elseif (is_array($response["BrowseNodes"]["BrowseNode"]["NewReleases"]["NewRelease"])) {
                        foreach ($response["BrowseNodes"]["BrowseNode"]["NewReleases"]["NewRelease"] as $item) {
                            if (isset($item["ASIN"])) {
                                $item_asins[] = $item["ASIN"];
                            }
                        }
                    }

                }
                
                return ["success" => True, "content" => $item_asins];
            
            } else {
                return $response;
            }
            
        } else {
            return ["success" => False, "content" => "No valid node provided or found"];
        }
        
    }
    
    private function get_reviews_info($ASIN, $reviews_IFrame_URL) {
        
        if (function_exists("wp_remote_get") && function_exists("is_wp_error")) {
            
            $data = [];
            
            $response = wp_remote_get($reviews_IFrame_URL);
            if (!is_wp_error($response)) {
                
                if (isset($response["response"]["code"])) {
                    $$response["response"]["code"] = "200";
                    $page = $response["body"];
                    
                    $dom = new \PHPHtmlParser\Dom();
                    $dom->load($page);
                    
                    $rating_element = $dom->find(".asinReviewsSummary");
                    if (count($rating_element) > 0) {  
                        $rating_element = $rating_element[0]->find("img");
                        if (count($rating_element) > 0) { 
                            $rating = $rating_element[0]->getAttribute("alt");
                        }
                    }
                    
                    $reviews_num_element = $dom->find(".crIFrameHeaderHistogram");
                    if (count($reviews_num_element) > 0) {  
                        $reviews_num_element = $reviews_num_element[0]->find(".tiny");
                        if (count($reviews_num_element) > 0) {  
                            $reviews_num_element = $reviews_num_element[0]->find("b");
                            if (count($reviews_num_element) > 0) {  
                                $reviews_num = $reviews_num_element[0]->text;
                            }
                        }
                    }
                    
                    if (isset($rating)) {
                        $data["rating"] = $rating;
                    }
                    if (isset($reviews_num)) {
                        $data["reviews_num"] = $reviews_num;
                    }
                    
                }
                
            }
            
            if ($data) {
                return $data;
            }
                           
            $reviews_URL = 'https://www.amazon.' . $this->country . '/product-reviews/' . $ASIN;
            $response = wp_remote_get($reviews_URL);
            if (!is_wp_error($response)) {
                
                if (isset($response["response"]["code"])) {
                    $$response["response"]["code"] = "200";
                    $page = $response["body"];
                    
                    $dom = new \PHPHtmlParser\Dom();
                    $dom->load($page);
                    
                    $rating_element = $dom->find(".averageStarRatingNumerical");
                    if (count($rating_element) > 0) {  
                        $rating_element = $rating_element[0]->find(".arp-rating-out-of-text");
                        if (count($rating_element) > 0) { 
                            $rating = $rating_element[0]->text;
                        }
                    }
                    
                    $reviews_num_element = $dom->find(".totalReviewCount");
                    if (count($reviews_num_element) > 0) {  
                        $reviews_num = $reviews_num_element[0]->text;
                    }
                    
                    
                    if (isset($rating)) {
                        $data["rating"] = $rating;
                    }
                    if (isset($reviews_num)) {
                        $data["reviews_num"] = $reviews_num . " Reviews";
                    }
                    
                }

            }
            
            if ($data) {
                return $data;
            } else {
                return False;
            }
                       
        } else {
            return False;
        }
    
    }
    
}