<?php

defined("ABSPATH") || die("No direct access allowed!");

class WP_Amazon_Plugin_Cache_Handler {
    
    private $product_cache_time = 86400;
    private $list_cache_time = 86400;
    
    private $product_cache_table;
    private $list_cache_table;
    
    private $atts;
    
    public function __construct() {
        
        global $wpdb;
        
        $this->product_cache_table = $wpdb->prefix . 'wpap_products_cache';
        $this->list_cache_table = $wpdb->prefix . 'wpap_lists_cache';
        $this->check_tables();
        
    }
    
    public function set_atts($atts=[]) {
        $this->atts = $atts;
    }
    
    private function check_tables() {
        
        global $wpdb;
        
        $table_name = $this->product_cache_table;
        
        $query = $wpdb->prepare( "SHOW TABLES LIKE '%s'", $table_name );
        if (! ($wpdb->get_var($query) === $table_name)) {
            
            $query = $wpdb->query("CREATE TABLE " . $table_name ." (
                ASIN VARCHAR(20) NOT NULL,
                updated INT(11) UNSIGNED NOT NULL,
                data MEDIUMTEXT NOT NULL,
                reviews TINYINT(1),
                PRIMARY KEY (ASIN)
            )");
            //$wpdb->query($query);
        
        } else {
            
            $check_upgrade = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s ",
                    DB_NAME, $table_name, "reviews"
                )
            );
            
            if (empty($check_upgrade)) {
                $wpdb->query("ALTER TABLE " . $table_name . " 
                    ADD reviews TINYINT(1)");
            }
            
        }
        
        $table_name = $this->list_cache_table;
        
        $query = $wpdb->prepare( "SHOW TABLES LIKE '%s'", $table_name );
        if (! ($wpdb->get_var($query) === $table_name)) {
            
            $query = $wpdb->query("CREATE TABLE " . $table_name ." (
                ID BIGINT(20) NOT NULL AUTO_INCREMENT,
                term VARCHAR(20) NOT NULL,
                listtype VARCHAR(4) NOT NULL,
                lookuptype VARCHAR(20) NOT NULL,
                searchindex VARCHAR(60),
                updated INT(11) UNSIGNED NOT NULL,
                ASINs TEXT NOT NULL,
                PRIMARY KEY (ID)
            )");
            //$wpdb->query($query);
        
        }
    
    }
    
    public function get_item($id) {
        
        global $wpdb;
        
        $query = $wpdb->prepare("SELECT * FROM {$this->product_cache_table} WHERE ASIN=%s", $id);
        $result = $wpdb->get_row($query);
        
        if ($result !== null) {
            
            $last_updated = intval($result->updated);
            $cache_duration = $this->atts["cache"] ? intval($this->atts["cache"]) : $this->product_cache_time;
            if ((time() - $last_updated) > $cache_duration) {
                //echo "Cache expired for ".$id."<br>";
                return False;
            } elseif (!$result->reviews && $this->atts["reviews"]) {
                return False;
            } else {
                return json_decode($result->data, True);
            }
            
        } else {
            return False;
        }
        
    }
    
    public function set_items($items) {
        
        global $wpdb;
        
        foreach ($items as $item) {
            
            $new_time = time();
            $data = json_encode($item);
            $reviews = isset($item["CustomerReviews"]["Rating"]) ? 1 : 0;
            
            $query = $wpdb->prepare("INSERT INTO {$this->product_cache_table} (ASIN,updated,data,reviews) VALUES (%s,%d,%s,%d) ON DUPLICATE KEY UPDATE updated=%d, data=%s,reviews=%d", [$item["ASIN"], $new_time, $data, $reviews, $new_time, $data, $reviews]);
            $wpdb->query($query);
            
        }
        
    }
    
    public function get_list($list_type, $value, $type, $search_index) {
        
        global $wpdb;
        
        if ($search_index === null) {
            $search_index = "";
        }
        
        $sql = "SELECT * FROM {$this->list_cache_table} WHERE term=%s AND listtype=%s AND lookuptype=%s AND searchindex=%s";
        $values = [$value, $list_type, $type, $search_index];
        
        $query = $wpdb->prepare($sql, $values);
        $result = $wpdb->get_row($query);
        
        if ($result !== null) {
            
            $last_updated = intval($result->updated);
            $cache_duration = $this->atts["cache"] ? intval($this->atts["cache"]) : $this->list_cache_time;
            if ((time() - $last_updated) > $cache_duration) {
                //echo "Cache expired for list";
                return False;
            } else {
                return json_decode($result->ASINs, True);
            }
            
        } else {
            return False;
        }
        
    }
    
    public function set_list($list, $list_type, $value, $type, $search_index) {
        
        global $wpdb;
            
        $new_time = time();
        $ASINs = json_encode($list);
        if ($search_index === null) {
            $search_index = "";
        }
        
        $where = " WHERE term=%s AND listtype=%s AND lookuptype=%s AND searchindex=%s";
        $values = [$value, $list_type, $type, $search_index];
        
        $query = $wpdb->prepare("SELECT * FROM {$this->list_cache_table}".$where, $values);
        $result = $wpdb->get_row($query);
        
        if ($result !== null) {
            $query = "UPDATE {$this->list_cache_table} SET updated=%d, ASINs=%s".$where;
            $values = array_merge([$new_time, $ASINs], $values);
        } else {
            $query = "INSERT INTO {$this->list_cache_table}
                (term, listtype, lookuptype, searchindex, updated, ASINs)
                VALUES (%s, %s, %s, %s, %d, %s)";
            $values[] = $new_time;
            $values[] = $ASINs;
        }
        
        $query = $wpdb->prepare($query, $values);
        $wpdb->query($query);
        
    }
    
    private function cleanup_expired() {
        
    }
    
}