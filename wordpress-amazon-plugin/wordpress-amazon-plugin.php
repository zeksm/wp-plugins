<?php
/*
Plugin Name: Wordpress Amazon Plugin
Plugin URI: 
Description: Wordpress Amazon Plugin
Version: 0.9.6
Author: Zeljko Smiljanic
Author URI: zeksmcode@gmail.com
*/

defined( 'ABSPATH' ) || die( 'No direct access allowed!' );

class WP_Amazon_Plugin_Main {
    
    private $plugin_dir_url = "";
    
    private $product_helper = null;
    
    private $css_enqueued = False;
    
    public function __construct() {
        
        $this->plugin_dir_url = plugin_dir_url( __FILE__ );
        
        /*
        remove_filter( 'the_content', 'wpautop' );
        add_filter( 'the_content', 'wpautop', 99 );
        add_filter( 'the_content', 'shortcode_unautop', 100 );
        */
        
        add_shortcode("wpap_box", array ($this, "box_shortcode"));
        add_shortcode("wpap_row", array ($this, "row_shortcode"));
        add_shortcode("wpap_best", array ($this, "bestseller_shortcode"));
        add_shortcode("wpap_new", array ($this, "newrelease_shortcode"));
        add_shortcode("wpap_best_row", array ($this, "bestseller_row_shortcode"));
        add_shortcode("wpap_new_row", array ($this, "newrelease_row_shortcode"));
        add_shortcode("wpap_table", array ($this, "comparison_table_shortcode"));
        add_shortcode("wpap_tablerow", array ($this, "comparison_table_row_shortcode"));
        
    }
    
    private function get_product_helper() {
        
        if (! $this->product_helper) {        
            require_once __DIR__ . "/src/product-helper.php";
            $this->product_helper = new WP_Amazon_Plugin_Product_Helper;
        }
        return $this->product_helper;
        
    }
    
    public function box_shortcode($atts, $content = null) {
        
        $default_atts = array(
		"items" => "",
        "reviews" => 0,
        "cache" => ""
        );
        
        $atts = shortcode_atts($default_atts, $atts );
        
        $item_ids = $atts["items"];
        
        if (! $item_ids) {
            return "WPAP: No item IDs provided!";
        }
        
        $item_ids = str_replace(" ", "", $item_ids);
        $item_ids = explode(",", $item_ids);
        
        $product_helper = $this->get_product_helper();
        $product_helper->set_atts($atts);
        $items = $product_helper->get_items($item_ids);
        
        if (! $items["success"]) {
            return "<div>".$items["content"]."</div>";
            
        } elseif (count($items["content"]) > 0) {
            
            $items = $items["content"];
            //echo(json_encode($items, JSON_PRETTY_PRINT));            
            
            $this->enqueue_CSS();
            
            ob_start();
            require __DIR__ . "/template/box.php";
            return ob_get_clean();
        }
       
    }
    
    public function row_shortcode($atts, $content = null) {
        
        $default_atts = array(
		"items" => "",
        "cache" => ""
        );
        
        $atts = shortcode_atts($default_atts, $atts );
        $atts["reviews"] = 0;
        
        $item_ids = $atts["items"];
        
        if (! $item_ids) {
            return "WPAP: No item IDs provided!";
        }
        
        $item_ids = str_replace(" ", "", $item_ids);
        $item_ids = explode(",", $item_ids);
        if (count($item_ids) > 5) {
            $item_ids = array_slice($item_ids, 0, 5);
        }
        
        $product_helper = $this->get_product_helper();
        $product_helper->set_atts($atts);
        $items = $product_helper->get_items($item_ids);
        
        if (! $items["success"]) {
            return "<div>".$items["content"]."</div>";
            
        } elseif (count($items["content"]) > 0) {
            
            $items = $items["content"];
            
            //echo(json_encode($items, JSON_PRETTY_PRINT));            
            
            $this->enqueue_CSS();
            
            ob_start();
            require __DIR__ . "/template/row.php";
            return ob_get_clean();
        }
       
    }
    
    private function build_newrelease($atts, $content, $display) {
        
        $default_atts = array(
		"node" => "",
        "nodesearch" => "",
        "num" => "",
        "reviews" => 0,
        "cache" => "",
        "itemcache" => ""
        );
        
        $atts = shortcode_atts($default_atts, $atts );
        
        $node_id = $atts["node"];
        $node_terms = $atts["nodesearch"];
        
        $product_helper = $this->get_product_helper();
        $product_helper->set_atts($atts);
        
        if ($node_id) {
            $items = $product_helper->get_list("new", $node_id, "node");
        } elseif ($node_terms) {
            $items = $product_helper->get_list("new", $node_terms, "nodesearch");
        } else {
            return "No browse node or search terms provided!";
        }
        
        if (! $items["success"]) {
            return "<div>".$items["content"]."</div>";
            
        } elseif (count($items["content"]) > 0) {
            
            $items = $items["content"];

            if ($atts["num"]) {
                $num = intval($atts["num"]);
                if ($num < 10 && $num > 0) {
                    $items = array_slice($items, 0, $num);
                }
            }
            
            $this->enqueue_CSS();
            
            ob_start();
            require __DIR__ . "/template/newreleases-{$display}.php";
            return ob_get_clean();    
        }
    }
    
    public function newrelease_shortcode($atts, $content = null) {
        
         return $this->build_newrelease($atts, $content,"box");
        
    }
    
    public function newrelease_row_shortcode($atts, $content = null) {
        
         return $this->build_newrelease($atts, $content, "row");
        
    }
    
    private function build_bestseller($atts, $content, $display) {
        
        $default_atts = array(
		"node" => "",
        "nodesearch" => "",
        "search" => "",
        "index" => "",
        "num" => "",
        "reviews" => 0,
        "cache" => "",
        "itemcache" => ""
        );
        
        $atts = shortcode_atts($default_atts, $atts );
        
        $node_id = $atts["node"];
        $node_terms = $atts["nodesearch"];
        $search_terms = $atts["search"];
        $search_index = $atts["index"];
        
        $product_helper = $this->get_product_helper();
        $product_helper->set_atts($atts);
        
        if ($node_id) {
            $items = $product_helper->get_list("best", $node_id, "node");
        } elseif ($node_terms) {
            $items = $product_helper->get_list("best", $node_terms, "nodesearch");
        } elseif ($search_terms && $search_index) {
            $items = $product_helper->get_list("best", $search_terms, "search", $search_index);
        } else {
            return "No browse node or search terms provided!";
        }
        
        if (! $items["success"]) {
            return "<div>".$items["content"]."</div>";
            
        } elseif (count($items["content"]) > 0) {
            
            $items = $items["content"]; 
            
            if ($atts["num"]) {
                $num = intval($atts["num"]);
                if ($num < 10 && $num > 0) {
                    $items = array_slice($items, 0, $num);
                }
            }

            $this->enqueue_CSS();
            
            ob_start();
            require __DIR__ . "/template/bestsellers-{$display}.php";
            return ob_get_clean();
        }
    }
    
    public function bestseller_shortcode($atts, $content = null) {
        
        return $this->build_bestseller($atts, $content, "box");
        
    }
    
    public function bestseller_row_shortcode($atts, $content = null) {
        
        return $this->build_bestseller($atts, $content, "row");
        
    }
    
    public function comparison_table_shortcode($atts, $content) {
        
        $default_atts = array(
		"items" => "",
        "reviews" => 0,
        "cache" => ""
        );
        
        $atts = shortcode_atts($default_atts, $atts );
        
        $item_ids = $atts["items"];
        
        if (! $item_ids) {
            return "No item IDs provided!";
        }
        
        $item_ids = str_replace(" ", "", $item_ids);
        $item_ids = explode(",", $item_ids);
        
        $product_helper = $this->get_product_helper();
        $product_helper->set_atts($atts);
        
        $items = $product_helper->get_items($item_ids);
        
        if (! $items["success"]) {
            return "<div>".$items["content"]."</div>";
            
        } elseif (count($items["content"]) > 0) {
            
            $items = $items["content"];

            $this->items = $items;
            
            $content = str_replace("<br />", "", $content);
            
            $this->enqueue_CSS();
            
            ob_start();
            require __DIR__ . "/template/table-wrapper.php";
            return ob_get_clean();
        }
          
    }
    
    public function comparison_table_row_shortcode($atts, $content) {
        
        $default_atts = array(
		"label" => "",
        "value" => "",
        "custom"=> "",
        "reviews" => 0
        );
        
        $atts = shortcode_atts($default_atts, $atts );
        
        $supported_row_values = [
            "thumb",
            "title",
            "price",
            "prime",
            "buy",
            "rating",
            "reviews"
        ];
        
        if ( $atts["custom"] || ($atts["value"] && in_array($atts["value"], $supported_row_values)) ) {
        
            if ($atts["custom"]) {
                $cell_values = explode("|", $atts["custom"]);
                $is_custom_row = True;
            } else {
                $row_value = $atts["value"];
                $is_custom_row = False;
            }
            
            $row_label = $atts["label"];
            
            $items = $this->items;
            
            ob_start();
            require __DIR__ . "/template/table-row.php";
            echo ob_get_clean();
            
        }
        
    }
    
    private function enqueue_CSS() {
        
        if (!$this->css_enqueued) {
        
            $css_file = 'css/wpap-styles.css';
            $css_url = plugins_url($css_file, __FILE__);
            wp_enqueue_style('wpap_css', $css_url);
            $this->css_enqueued = True;
            
        }
        
    }
    
}

new WP_Amazon_Plugin_Main;