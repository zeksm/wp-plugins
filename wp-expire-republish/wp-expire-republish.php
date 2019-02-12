<?php 
/*
Plugin Name: WordPress Expire And Republish
Description: A plugin that enables the scheduling of various post actions like expiration and republishing in the future
Version: 1.1.0
Author: Zeljko Smiljanic
Author URI: zeksmcode@gmail.com
*/

defined( 'ABSPATH' ) || die( 'No direct access allowed!' );

class WordPress_Expire_And_Republish {
    
    private $plugin_dir;
    
    private $dead_category_ID = 77;
    private $default_action = "expire";
    
    public function __construct() {
        
        $this->plugin_dir = basename(dirname(__FILE__));
        
        $this->dead_category_ID = get_category_by_slug("dead")->term_id;
        
        register_activation_hook(__FILE__, array($this, 'wpexre_activate'));
        register_deactivation_hook(__FILE__, array($this, 'wpexre_deactivate'));
        
        add_action('add_meta_boxes', array($this, 'setup_meta_boxes'));
        add_action('admin_init', array($this, 'enqueue_css'));
        add_action('admin_head', array($this, 'include_datepicker'));
        add_action('save_post', array($this, 'save_my_post_meta'), 100);
        add_action('wpexre_scheduled_action', array($this, 'wpexre_scheduled_action'), 10, 4);
        
    }
    
    public function enqueue_css() {
        $url = plugins_url('style.css', __FILE__);
        $file = WP_PLUGIN_DIR . '/wpexre/style.css';
        if (file_exists($file)) {
            wp_register_style('wpexre-css', $url);
            wp_enqueue_style('wpexre-css');
        }

    }
    
    public function setup_meta_boxes() {
        $types = get_post_types();
        $types[] = 'page';
        foreach ($types as $type) {
            $defaults = get_option('expirationdateDefaults'.ucfirst($type));
            if (!isset($defaults['activeMetaBox']) || $defaults['activeMetaBox'] == 'active') {
                add_meta_box('wpexre_box', 'Expiration and Republishing', array($this, 'build_meta_box'), $type, 'normal', 'default');
            }
        }
    }
    
    public function build_meta_box($post) {
        
        $postSettings = get_post_meta($post->ID, "wpexre_data", true);
        $enabled = isset($postSettings["enabled"]) ? $postSettings["enabled"] : false;
        $schedule = isset($postSettings["schedule"]) ? $postSettings["schedule"] : [];
        $categories = wp_get_post_categories($post->ID);
        
        ?>
        <div id="wpexre_metabox">
        
        <div id="wpexre_schedule_edit">
        
            <p>
                <label>
                    <input type="checkbox" name="wpexre_enable" id="wpexre_enable" value="checked" <?php if ($enabled) { echo ' checked="checked"'; } ?> />
                    Enable scheduling
                </label>
            </p>
            <p>
                <label for="wpexre_new_date">Time: </label>
                <input type="text" id="wpexre_new_date" class="MyDate" name="wpexre_new_date"/>
                <a class="clear_button hide-if-no-js button-cancel" title="clear" data-clear style="text-decoration:underline;">Clear</a>
            </p>
            <p>
                Action: <?php $this->list_actions($post->post_type, 'wpexre_new_action', False, $this->default_action, 'wpexre_show_categories(this)'); ?>
            </p>   
            
            
            <?php if ($post->post_type != 'page'): ?>
                <div id="wpexre_categories_new" style="display: none">
                    Choose categories:
                    <br/>
                    <div class="wp-tab-panel" id="wpexre_categories_list">
                        <ul id="wpexre_categorychecklist" class="list:category categorychecklist form-no-clear" style="margin-top: 0; margin-bottom: 0;">
                            <?php
                            $walker = new WPEXRE_Category_Walker();
                            //$walker->setDisabled();
                            //wp_category_checklist(0, ['walker' => $walker, /*'selected_cats' => $categories,*/ 'checked_ontop' => false]);
                            wp_terms_checklist(0, array( 'taxonomy' => "category", 'walker' => $walker, /*'selected_cats' => $categories,*/ 'checked_ontop' => false ) );
                            ?>
                        </ul>
                    </div>
                </div>
                
            <?php endif; ?>
            
            <p style="text-align: center;">
                <input id="wpexre_add" class="button" type="button" value="ADD" onclick="wpexre_add_schedule()">
                <input id="wpexre_edit_ok" class="button" type="button" value="OK" style="display: none" onclick="wpexre_ok_edit()">
                <input id="wpexre_edit_cancel" class="button" type="button" value="Cancel" style="display: none" onclick="wpexre_cancel_edit()">
                <input type="hidden" name="wpexre_current_schedule">
            </p>
            
        </div>
            
        <div id="wpexre_scheduled">
            <div id="wpexre_scheduled_table">
                <div id="wpexre_scheduled_head">
                    <div id="wpexre_scheduled_head_row">
                        <div class="wpexre_scheduled_head_cell">SCHEDULED TIME</div>
                        <div class="wpexre_scheduled_head_cell">ACTION</div>
                        <div class="wpexre_scheduled_head_cell">CATEGORIES</div>
                        <div class="wpexre_scheduled_head_cell">EDIT</div>
                    </div>
                </div>
                <div id="wpexre_scheduled_body">
                <?php foreach ($schedule as $s): ?>
                    <?php 
                    if (get_option("timezone_string")) {
                        $local_time = get_date_from_gmt(gmdate("Y-m-d H:i", (int)$s[0]), "Y-m-d H:i"); 
                    } else {
                        $local_time = $this->get_local_date_from_timestamp($s[0]);
                    }
                    
                    ?>
                    <div class="wpexre_single_schedule">
                        <div class="wpexre_single_field wpexre_field_time">
                            <div class="wpexre_single_field_title">TIME</div>
                            <div class="wpexre_single_field_content">
                                <?php echo $local_time ?>
                            </div>
                        </div>
                        <div class="wpexre_single_field wpexre_field_action">
                            <div class="wpexre_single_field_title">ACTION</div>
                            <div class="wpexre_single_field_content">
                                <?php echo $s[1] ?>
                            </div>
                        </div>
                        <div class="wpexre_single_field wpexre_field_categories">
                            <div class="wpexre_single_field_title">CATEGORIES</div>
                            <div class="wpexre_single_field_content">
                            <?php
                                $names = "";
                                $categoryIDs = explode(",", $s[2]);
                                foreach ($categoryIDs as $catID) {
                                    $names = $names . get_cat_name((int)$catID) . ", ";
                                }
                                $names = substr($names, 0, -2);
                                echo $names;
                            ?>
                            </div>
                        </div>
                        <div class="wpexre_single_field wpexre_field_buttons">
                            <button type="button" onclick="wpexre_edit_schedule(this)">EDIT</button>
                            <button type="button" onclick="wpexre_remove_schedule(this)">REMOVE</button>
                        </div>
                        <input type="hidden" name="wpexre_single_values[]" value="<?php echo $local_time."|".$s[1]."|".$s[2] ?>" autocomplete="off">
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div style="clear: both;"></div>
        <input type="hidden" name="wpexre_check" value="true" />
        
        <script type="text/javascript">
        
            var wpexre_currently_editing;
            var wpexre_original_checked;
            
            function wpexre_get_values() {
                var new_time = $("#wpexre_new_date").val();
                var new_action = $("#wpexre_new_action").val();
                if (!new_time || !new_action) {
                    return false;
                }
                
                var new_category_names = "";
                var new_category_ids = "";
                if (new_action == "category-replace" || new_action == "category-add" || new_action == "category-remove") {
                    //console.log(new_time + " " + new_action);
                    $("input:checkbox[name=wpexre_category_new]:checked").each(function(){
                        console.log(this);
                        new_category_ids = new_category_ids + $(this).val() + ",";
                        new_category_names = new_category_names + $(this).parent().text() + ", ";
                    });
                    new_category_ids = new_category_ids.slice(0, -1);
                    new_category_names = new_category_names.slice(0, -2);
                    if (!new_category_ids) {
                        //console.log("problem");
                        return false;
                    }
                }
                
                return {newTime: new_time, newAction: new_action, newCats: new_category_names, newCatsIds: new_category_ids}
            }
        
            function wpexre_add_schedule() {
                
                var data = wpexre_get_values();
                
                if (!data) {
                    return;
                }
                
                $("#wpexre_new_date").flatpickr().destroy();
                $('#wpexre_new_date').attr('value', '');
                $('#wpexre_new_date').flatpickr({
                    altInput: true,
                    altFormat: "M j, Y @ H:i",
                    enableTime: true,
                    dateFormat: 'Y-m-d H:i'
                });
                $("#wpexre_new_action").val("<?php echo $this->default_action ?>");
                wpexre_category_reset();
                wpexre_show_categories();
                
                var $new_schedule = $("<div class='wpexre_single_schedule'><div class='wpexre_single_field wpexre_field_time'><div class='wpexre_single_field_title'>TIME</div><div class='wpexre_single_field_content'>"+data.newTime+"</div></div><div class='wpexre_single_field wpexre_field_action'><div class='wpexre_single_field_title'>ACTION</div><div class='wpexre_single_field_content'>"+data.newAction+"</div></div><div class='wpexre_single_field wpexre_field_categories'><div class='wpexre_single_field_title'>CATEGORIES</div><div class='wpexre_single_field_content'>"+data.newCats+"</div></div><div class='wpexre_single_field wpexre_field_buttons'><button type='button' onclick='wpexre_edit_schedule(this)'>EDIT</button><button type='button' onclick='wpexre_remove_schedule(this)'>REMOVE</button></div><input type='hidden' name='wpexre_single_values[]' value='"+data.newTime+"|"+data.newAction+"|"+data.newCatsIds+"'></div>");
                //console.log($new_schedule);
                $("#wpexre_scheduled_body").append($new_schedule);
            }
            
            function wpexre_edit_schedule(that) {
                if (wpexre_currently_editing) {
                    wpexre_currently_editing.css("background-color", "white");
                }
                wpexre_currently_editing = $(that).parent().parent();
                var value = $(that).parent().siblings("input")[0].value;
                wpexre_currently_editing.css("background-color", "lightgray");
                value = value.split("|");
                //console.log(value)
                $("#wpexre_new_date").flatpickr().destroy();
                //$("#wpexre_new_date").val(value[0]);
                $('#wpexre_new_date').flatpickr({
                    altInput: true,
                    altFormat: "M j, Y @ H:i",
                    enableTime: true,
                    dateFormat: 'Y-m-d H:i',
                    defaultDate: value[0]
                });
                $('#wpexre_new_action option[value='+value[1]+']').prop('selected', true);
                catIds = value[2].trim().split(",");
                //console.log(catIds);
                $("#wpexre_categories_list input").each(function(index) {
                    var elem = $(this);
                    //console.log(elem.val());
                    if (catIds.indexOf(elem.val()) > -1) {
                        //console.log("checkingg");
                        elem.prop("checked", true);
                    } else {
                        //console.log("uncheckingg");
                        elem.prop("checked", false);
                    }
                });
                wpexre_show_categories();
                $("#wpexre_add").hide();
                $("#wpexre_edit_ok").show();
                $("#wpexre_edit_cancel").show();
            }
            
            function wpexre_remove_schedule(that) {
                $(that).parent().parent().remove();
            }
            
            function wpexre_ok_edit() {
                var data = wpexre_get_values();
                if (!data) {
                    return;
                }
                var tds = wpexre_currently_editing.find(".wpexre_single_field_content");
                tds[0].textContent = data.newTime;
                tds[1].textContent = data.newAction;
                tds[2].textContent = data.newCats;
                wpexre_currently_editing.children("input").val(data.newTime+"|"+data.newAction+"|"+data.newCatsIds);
                wpexre_cancel_edit();
            }
            
            function wpexre_cancel_edit() {
                wpexre_currently_editing.css("background-color", "white");
                
                $("#wpexre_new_date").flatpickr().destroy();
                $('#wpexre_new_date').attr('value', '');
                $('#wpexre_new_date').flatpickr({
                    altInput: true,
                    altFormat: "M j, Y @ H:i",
                    enableTime: true,
                    dateFormat: 'Y-m-d H:i'
                });
                $("#wpexre_new_action").val("<?php echo $this->default_action ?>");
                
                wpexre_category_reset();
                wpexre_show_categories();
                
                $("#wpexre_add").show();
                $("#wpexre_edit_ok").hide();
                $("#wpexre_edit_cancel").hide();
            }
            
            function wpexre_show_categories() {
                //console.log("CATEGORIES")
                var select = $("#wpexre_new_action");
                var $categories = $("#wpexre_categories_new");
                if (select.val() == 'category-replace') {
                    $categories.show();
                } else if (select.val() == 'category-add') {
                    $categories.show();
                } else if (select.val() == 'category-remove') {
                    $categories.show();
                } else {
                    $categories.hide();
                }
            }
            
            function wpexre_category_reset() {
                $("#wpexre_categories_list input").each(function(index) {
                    $(this).prop("checked", false);
                });
                /*wpexre_original_checked.each(function(index) {
                    $(this).prop("checked", true);
                });*/
            }
                
            jQuery(document).ready(function($){
                
                //wpexre_original_checked = $("#wpexre_categories_list input:checked")
                
                wpexre_show_categories();
                
                $('#wpexre_new_date').flatpickr({
                    altInput: true,
                    altFormat: "M j, Y @ H:i",
                    enableTime: true,
                    dateFormat: 'Y-m-d H:i'
                });
                
                $(".clear_button").click(function() {
                    $("#wpexre_new_date").flatpickr().destroy();
                    $('#wpexre_new_date').attr('value', '');
                    $('#wpexre_new_date').flatpickr({
                        altInput: true,
                        altFormat: "M j, Y @ H:i",
                        enableTime: true,
                        dateFormat: 'Y-m-d H:i'
                    });
                });
            });
        </script>
        
        </div>
        <?php
    }
    
    private function list_actions($type, $name, $disabled = False, $selected = "expire", $onchange = "") {
        
        ?>
        <select name="<?php echo $name ?>" id="<?php echo $name ?>"<?php $disabled == true ? ' disabled="disabled"' : ''; ?> onchange="<?php echo $onchange ?>">
            <!--<option hidden disabled selected value> -- select an action -- </option>-->
            <option value="expire" <?php $selected == 'draft' ? 'selected="selected"' : ''; ?>>Expire</option>
            <option value="draft" <?php $selected == 'draft' ? 'selected="selected"' : ''; ?>>Draft</option>
            <option value="delete" <?php $selected == 'delete' ? 'selected="selected"' : ''; ?>>Delete</option>
            <option value="trash" <?php $selected == 'trash' ? 'selected="selected"' : ''; ?>>Trash</option>
            <option value="private" <?php $selected == 'private' ? 'selected="selected"' : ''; ?>>Private</option>
            <option value="stick" <?php $selected == 'stick' ? 'selected="selected"' : ''; ?>>Stick</option>
            <option value="unstick" <?php $selected == 'unstick' ? 'selected="selected"' : ''; ?>>Unstick</option>
            <option value="republish" <?php $selected == 'unstick' ? 'selected="selected"' : ''; ?>>Republish</option>
            <?php if ($type != 'page'): ?>
                <option value="category-replace" <?php $selected == 'category-replace' ? 'selected="selected"' : ''; ?>>Category: Replace All</option>
                <option value="category-add" <?php $selected == 'category-add' ? 'selected="selected"' : ''; ?>>Category: Add</option>
                <option value="category-remove" <?php $selected == 'category-remove' ? 'selected="selected"' : ''; ?>>Category: Remove</option>
            <?php endif; ?>
        </select>
        <?php
        
    }
    
    public function save_my_post_meta($id) {
        
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
            return $id;
        }
        
        if (get_post_type($id) === "revision") {
            return $id;
        }
        
        if (!isset($_POST["wpexre_check"])) {
            return $id;
        }
        
        if (isset($_POST["wpexre_single_values"])) {
            $received = $_POST["wpexre_single_values"];
        } else {
            $received = [];
        }
        $schedules = [];
        foreach ($received as $schedule) {
            $values = explode("|", $schedule);
            $values[0] = $this->get_timestamp_from_local_time($values[0]);
            $schedules[] = $values;
        }        
        
        $postSettings = get_post_meta($id, "wpexre_data", true);
        
        if (isset($postSettings["schedule"]) && $postSettings["schedule"]) {
        
            //$new = @array_diff($schedules, $postSettings["schedule"]);
            //$removed = @array_diff($postSettings["schedule"], $schedules);
            
            $temp1 = array_map('json_encode', $schedules);
            
            $temp2 = array_map('json_encode', $postSettings["schedule"]);
            
            $new = array_diff($temp1, $temp2);
            $new = array_map('json_decode', $new);
            
            $removed = array_diff($temp2, $temp1);
            $removed = array_map('json_decode', $removed);
            
            $unchanged = array_intersect($temp1, $temp2);
            $unchanged = array_map('json_decode', $unchanged);
            
        } else {
            
            $postSettings["schedule"] = [];
            $new = $schedules;
            $removed = [];
            $unchanged = [];
        }
        
        $filter_past = [];
        foreach ($new as $n) {
            if ((int)$n[0] > time()) {
                $filter_past[] = $n;
            }
        }
        $new = $filter_past;
        
        $new_settings = [];
        foreach ($new as $n) {
            $new_settings[] = $n;
        }
        foreach ($unchanged as $u) {
            $new_settings[] = $u;
        }
                
        usort($new_settings, function($a, $b) { 
            return strcmp($a[0], $b[0]); 
        });        

        if (isset($_POST["wpexre_enable"])) {
            if (!isset($postSettings) || !$postSettings["enabled"]) {
                $toSchedule = $new_settings;
                $toUnschedule = [];
            } else {
                $toSchedule = $new;
                $toUnschedule = $removed;
            }
            $postSettings["enabled"] = True;
        } else {
            $postSettings["enabled"] = False;
            $toSchedule = [];
            $toUnschedule = $postSettings["schedule"];
        }
        
        $this->schedule_actions($id, $toSchedule);
        $this->unschedule_actions($id, $toUnschedule);
        
        $postSettings["schedule"] = $new_settings;
        
        $result = update_post_meta($id, "wpexre_data", $postSettings);

    }
    
    private function schedule_actions($id, $scheduled) {
        foreach ($scheduled as $s) {
            $timestamp = $s[0];
            //$timestamp = $this->get_timestamp_from_local_time($timeString);
            $action = $s[1];
            $categories = $s[2];
            
            if (!wp_next_scheduled('wpexre_scheduled_action',array($id, $timestamp, $action, $categories))) {
                wp_schedule_single_event($timestamp,'wpexre_scheduled_action',array($id, $timestamp, $action, $categories));
            }
        }
    }
    
    private function unschedule_actions($id, $scheduled) {
        foreach ($scheduled as $s) {
            $timestamp = $s[0];
            //$timestamp = $this->get_timestamp_from_local_time($timeString);
            $action = $s[1];
            $categories = $s[2];
            
            if (wp_next_scheduled('wpexre_scheduled_action',array($id, $timestamp, $action, $categories))) {
                wp_clear_scheduled_hook('wpexre_scheduled_action',array($id, $timestamp, $action, $categories));
            }
        }
    }
    
    private function get_timestamp_from_local_time($timeString) {
        $offset = $this->get_formatted_offset();
        $timeStringTz = $timeString . " " . $offset;
        $timestamp = strtotime($timeStringTz);
        return $timestamp;
    }
    
    private function get_local_date_from_timestamp($timestamp) {
        $offset = $this->get_formatted_offset();
        $timezone  = new DateTimeZone(str_replace(':', '', $offset));
        $datetime = new DateTime("@".$timestamp);
        $datetime->setTimezone($timezone);
        return $datetime->format("Y-m-d H:i");
    }
    
    private function get_formatted_offset() {
        $min    = 60 * get_option('gmt_offset');
        $sign   = $min < 0 ? "-" : "+";
        $absmin = abs($min);
        $offset = sprintf("%s%02d:%02d", $sign, floor($absmin/60), $absmin%60);
        return $offset;
    }
    
    public function wpexre_scheduled_action($id, $timestamp, $action, $categories) {

        if (empty($id) || is_null(get_post($id))) {
            return false;
        }

        $categories = explode(",", $categories);
        
        kses_remove_filters();
        
        if ($action == 'expire') {
            wp_set_post_categories($id, $this->dead_category_ID, true);
        } elseif ($action == 'draft') {
            wp_update_post(array('ID' => $id, 'post_status' => 'draft'));
        } elseif ($action == 'private') {
            wp_update_post(array('ID' => $id, 'post_status' => 'private'));
        } elseif ($action == 'delete') {
            wp_delete_post($id);
        } elseif ($action == 'trash') {
            wp_trash_post($id);
        } elseif ($action == 'stick') {
            stick_post($id);
        } elseif ($action == 'unstick') {
            unstick_post($id);
        } elseif ($action == 'republish') {
            wp_update_post(array(
                'ID' => $id, 
                'post_date' => current_time( 'mysql' ), 
                'post_date_gmt' => current_time( 'mysql', 1 ),
                'post_status' => 'publish'
            ));
        } elseif ($action == 'category-replace') {
            if (!empty($categories)) {
                wp_update_post(array('ID' => $id, 'post_category' => $categories));
            }
        } elseif ($action == 'category-add') {
            if (!empty($categories)) {
                $cats = wp_get_post_categories($id);
                $merged = array_merge($cats, $categories);
                wp_update_post(array('ID' => $id, 'post_category' => $merged));
            }
        } elseif ($action == 'category-remove') {
            if (!empty($categories)) {
                $cats = wp_get_post_categories($id);
                $merged = array();
                foreach ($cats as $cat) {
                    if (!in_array($cat, $categories)) {
                        $merged[] = $cat;
                    }
                }
                wp_update_post(array('ID' => $id, 'post_category' => $merged));
            }
        }
        
        $this->clear_done_schedule($id, $timestamp, $action, $categories);
            
    }
    
    private function clear_done_schedule($id, $timestamp, $action, $categories) {
        
        $postSettings = get_post_meta($id, "wpexre_data", true);
        $filtered = [];
        $categories = implode(",", $categories);
        foreach ($postSettings["schedule"] as $s) {
            if ($s[0] !== $timestamp || $s[1] !== $action || $s[2] !== $categories) {
                $filtered[] = $s;
            }
        }
        $postSettings["schedule"] = $filtered;
        update_post_meta($id, "wpexre_data", $postSettings);
    }
    
    public function include_datepicker() {

        wp_register_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr');
        wp_enqueue_script( 'flatpickr' );

        wp_register_style('flatpickrStyle', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', 'all');
        wp_enqueue_style('flatpickrStyle');
        
    }
    
    public function wpexre_activate() {
                
         $posts = get_posts(
            array(
                'post_type' => "any",
                'meta_key' => "wpexre_data",
                'posts_per_page' => -1,
                'post_status' => 'any'
            )
        );
                
        foreach( $posts as $post ) {
            $postSettings = get_post_meta( $post->ID, "wpexre_data", true );
            if ($postSettings && isset($postSettings["schedule"]) && $postSettings["schedule"]) {
                $this->schedule_actions($post->ID, $postSettings["schedule"]);
            }
        }
        
    }
    
    public function wpexre_deactivate() {
        wp_unschedule_hook("wpexre_scheduled_action");
    }
       
}

class WPEXRE_Category_Walker extends Walker {
	var $tree_type = 'category';
	var $db_fields = array ('parent' => 'parent', 'id' => 'term_id');

	var $disabled = '';

	function setDisabled() {
		$this->disabled = 'disabled="disabled"';
	}

	function start_lvl(&$output, $depth = 0, $args = array()) {
		$indent = str_repeat("\t", $depth);
		$output .= "$indent<ul class='children' style='margin-left: 18px;'>\n";
	}

	function end_lvl(&$output, $depth = 0, $args = array()) {
		$indent = str_repeat("\t", $depth);
		$output .= "$indent</ul>\n";
	}

	function start_el(&$output, $category, $depth = 0, $args = array(), $current_object_id = 0) {
		extract($args);
		if ( empty($taxonomy) )
			$taxonomy = 'category';

		$name = 'wpexre_category_new';

		$class = in_array( $category->term_id, $popular_cats ) ? ' class="expirator-category"' : '';
		$output .= "\n<li id='expirator-{$taxonomy}-{$category->term_id}'$class>" . '<label class="selectit"><input value="' . $category->term_id . '" type="checkbox" name="'.$name.'" id="expirator-in-'.$taxonomy.'-' . $category->term_id . '"' . checked( in_array( $category->term_id, $selected_cats ), true, false ) . disabled( empty( $args['disabled'] ), false, false ) . ' '.$this->disabled.'/> ' . $category->name . '</label>';
	}

	function end_el(&$output, $category, $depth = 0, $args = array()) {
		$output .= "</li>\n";
	}
}

function wpexre_uninstall() {
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->prefix}postmeta WHERE meta_key='wpexre_data'");
}
register_uninstall_hook(__FILE__, 'wpexre_uninstall');

new WordPress_Expire_And_Republish;