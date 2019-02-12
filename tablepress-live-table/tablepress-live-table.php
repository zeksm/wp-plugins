<?php 
/*
Plugin Name: TablePress Extension: Live Tables
Plugin URI: 
Description: Extension for TablePress that allows adding tables that update without having to reload the whole page
Version: 1.0.0
Author: Zeljko Smiljanic
Author URI: zeksmcode@gmail.com
*/

class TablePress_Live_Tables {

    protected $updateInterval = 5000;
        
    protected $tableid;
    
    public function __construct() {
        
        add_action('tablepress_event_saved_table', array ($this, 'save_live_table'));
        add_shortcode("livetable", array ($this, "shortcode_livetable"));
        
    }

    public function save_live_table($tableid) {
        
        $table = TablePress::$model_table->load( $tableid, true, true );
        
        if ( is_wp_error( $table ) ) {
            TablePress::redirect( array( 'action' => 'export', 'message' => 'error_load_table' ) );
        }
        if ( isset( $table['is_corrupted'] ) && $table['is_corrupted'] ) {
            TablePress::redirect( array( 'action' => 'export', 'message' => 'error_table_corrupted' ) );
        }
        
        $upload_dir = wp_upload_dir();
        $plugin_save_dir = $upload_dir['basedir'] . '/live-table';
        wp_mkdir_p($plugin_save_dir);
        
        array_shift($table["data"]);
        
        file_put_contents($plugin_save_dir . "/{$tableid}.json", wp_json_encode($table["data"]));
        
        $frontend = TablePress::load_class( 'TablePress_Frontend_Controller', 'controller-frontend.php', 'controllers' );
        $fulltable = $frontend->shortcode_table(["id" => $tableid]);
            
        file_put_contents($plugin_save_dir . "/{$tableid}.html", time() . "\n" . trim($fulltable));
        
    }

    public function shortcode_livetable($atts) {
        
        $tableid = $atts["id"];
        $this->tableid = $tableid;
        
        add_action('tablepress_table_render_options', array ($this, 'setup_js_actions'), 10, 2);
        
        $frontend = TablePress::load_class( 'TablePress_Frontend_Controller', 'controller-frontend.php', 'controllers' );
        $table = $frontend->shortcode_table($atts);
        
        $table = "<div id='live-table-container-{$tableid}'>" . $table . "</div>";
        return $table;
        
    }
    
    public function setup_js_actions($render_options, $table) {
        
        if ( $render_options['use_datatables'] && $render_options['table_head'] && count( $table['data'] ) > 1 ) {
            add_action('tablepress_datatables_parameters', array($this, "add_datatables_ajax_source"), 10, 4);
            add_action('tablepress_datatables_command', array ($this, "add_datatables_ajax_reload"), 10, 5);
        } 
        else {
            add_action( 'wp_print_footer_scripts', array( $this, 'add_live_table_js' ), 11 );
        }
        
        return $render_options;
        
    }
    
    public function add_datatables_ajax_source($parameters, $table_id, $html_id, $js_options) {
        
        $parameters["ajax"] = '"ajax": {"url": "' . wp_upload_dir()["baseurl"] . '/live-table/' . $table_id . '.json", "dataSrc": ""}';
        
        return $parameters;
        
    }
    
    public function add_datatables_ajax_reload($command, $html_id, $parameters, $table_id, $js_options) {
        
        $command = ";var live_table_{$table_id} = " . $command . " var live_table_{$table_id}_API = live_table_{$table_id}.api(); setInterval( function() { live_table_{$table_id}_API.ajax.reload(); }, {$this->updateInterval});";
        
        return $command;
        
    }

    public function add_live_table_js() {
        
        $tableid = $this->tableid;
        $updateInterval = $this->updateInterval;
        $dir = wp_upload_dir()["baseurl"];
        
        echo <<<JS
<script type="text/javascript">
jQuery(document).ready(function($){

  var httpRequest; 
  var lastUpdate;
  
  function updateTable() {
    try {
      var response = httpRequest.responseText;
      var htmlTime = parseInt(response.substr(0,response.indexOf("\\n")));
      if (htmlTime > lastUpdate) {
        var html = response.substr(response.indexOf("\\n")+1);
        console.log(html);
        var table = document.querySelector("#live-table-container-{$tableid} table");
        table.outerHTML = html;
        console.log("Table updated");
        lastUpdate = htmlTime;
      }
    } catch(e) {
      console.log('Caught exception while making AJAX call: ' + e.description);
    }
  }
  
  function makeRequest(url) {
    httpRequest = new XMLHttpRequest();
    console.log("start")

    if (!httpRequest) {
      console.log('Cannot create an XMLHTTP instance');
      return false;
    }
    httpRequest.onreadystatechange = function() {
      if (httpRequest.readyState === XMLHttpRequest.DONE) {
        if (httpRequest.status === 200) {
          updateTable();
        } else {
          console.log("AJAX call failed, status: " + httpRequest.status);
        }
      }
    };
    httpRequest.open('GET', url + "?" + Date.now());
    httpRequest.setRequestHeader('Cache-Control', 'no-cache');
    httpRequest.send();
  }
  
  lastUpdate = Date.now()/1000 - 30;
  var refresh = setInterval(function(){
    makeRequest("{$dir}/live-table/{$tableid}.html");
  }, {$updateInterval});

});

</script>      
JS;
    
    }
    
}

new TablePress_Live_Tables;