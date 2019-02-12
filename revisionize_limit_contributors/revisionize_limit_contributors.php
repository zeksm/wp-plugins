<?php

/*
Plugin Name: Revisionize Addon: Limit Contributors
Plugin URI: 
Description: Addon for the Revisionize plugin that allows you to limit contributors/authors to revisionizing only specific posts.
Version: 1.1.0
Author: Zeljko Smiljanic
Author URI: zeksmcode@gmail.com
*/

include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

if (is_plugin_active("revisionize/revisionize.php") && in_array("contributors_can", \Revisionize\get_installed_addons()) && \Revisionize\is_addon_active("contributors_can")) {

    class RevisionizeLimitContributors extends RevisionizeAddon {

       
        private $rules = null;

        
        function name() {
            return 'revisionize_limit_contributors';
        }

        
        function version() {
            return '1.0.0';
        }

        
        function init() {
            add_action('admin_init', array($this, 'setup_settings'));
            add_filter('revisionize_is_create_enabled', array($this, 'is_post_revision_allowed_for_user'), 12, 2);
        }
        
        function get_setting($key, $default = "") {
            $settings = get_option('revisionize_limit_contributors_settings');  
            return !empty($settings[$key]) ? $settings[$key] : $default;           
        }
        
        function set_setting($key, $value) {
            $settings = get_option('revisionize_limit_contributors_settings');  
            $settings[$key] = $value;
            update_option('revisionize_limit_contributors_settings', $settings);
        }
      
      
        function is_post_revision_allowed_for_user($is_enabled, $post) {
            
            if (! $is_enabled) {
                return false;
            }
            
            if ($this->check_if_admin_or_editor()) {
                return true;
            }
            
            $setting =  $this->get_setting('rules', []);
            
            if (! $setting) {
                return false;
            }
            
            $allowed = $this->get_rules($setting);
            
            $user_id = get_current_user_id();
            
            if (array_key_exists($user_id, $allowed)) {
                $allowed_posts = $allowed[$user_id];
                $allowed_posts = explode(",", $allowed_posts);
                $allowed_posts_array = [];
                foreach ($allowed_posts as $post_id) {
                    $post_id = trim($post_id);
                    if (! is_numeric($post_id)) {
                        continue;
                    }
                    $allowed_posts_array[] = (int)$post_id;
                }
                $post_id = (int)$post->ID;
            } else {
                return false;
            }
            
            if (in_array($post_id, $allowed_posts_array)) {
                return true;
            } else {
                return false;
            }
            
        }

        
        function check_if_admin_or_editor(){
            return in_array('administrator',  wp_get_current_user()->roles) || in_array('editor',  wp_get_current_user()->roles);
        }
        
        
        function setup_settings() {
            
            register_setting('revisionize', 'revisionize_limit_contributors_settings');
            
            add_settings_section('revisionize_section_limit_contributors', '', '__return_null', 'revisionize');
            
            add_settings_field('revisionize_limit_contributors_settings','Limit Contributors to Posts', array($this, 'build_settings_html'), 'revisionize', 'revisionize_section_limit_contributors', array(
                'label_for' => 'revisionize_limit_contributors_settings',
                'description' => 'Limit the posts a contributor or author can revisionize:',
                'default' => ''
              ));

            add_action('revisionize_settings_fields', array($this, 'settings_fields'), 10);
            
        }

        
        function settings_fields() {
           \Revisionize\do_fields_section('revisionize_section_limit_contributors');
        }
        
        
        function get_rules($setting) {
            
            if (!$this->rules) {
                $this->rules = $this->build_rules($setting);
            }
            
            return $this->rules; 
        }
        
        
        function build_rules($setting) {
            
            $rules = [];
            $user_ids = [];
            
            if (isset($setting["users"]) && !empty($setting["users"])) {
                for ($i=0, $imax=count($setting["users"]); $i < $imax; $i++) {
                    $user_id = $setting["users"][$i];
                    if ($user_id && !in_array($user_id, $user_ids)) {
                        $post_ids = $setting["posts"][$i];
                        $rules[$user_id] = $post_ids;
                        $user_ids[] = $user_id;
                    } else {
                        unset($setting["users"][$i]);
                        unset($setting["posts"][$i]);
                        $this->set_setting("rules", $setting);
                    }
                }
            }
            
            return $rules;
        }
        
        
        function build_settings_html($args) {
            
            $setting = $this->get_setting("rules", []);
            
            $rules = $this->get_rules($setting);
            
            $all_users = get_users(array(
                "role__in" => array("contributor", "author"),
                "fields" => array("user_login", "ID"),
                "orderby" => "ID",
                "order" => "ASC"
                )
            );
            
            $id = esc_attr($args['label_for']);
            
            ?>
            
            <style>
                #revisionize_setting_limit_contributors {
                    display: inline-block;
                    text-align: center;
                }
                #limit_contributors_add_rule, .limit_contributors_row {
                    margin-top: 10px;
                }
                .limit_contributors_row select, .limit_contributors_row input {
                    margin-right: 10px;
                }
                
            </style>
            
            <div id="revisionize_setting_limit_contributors">
                <div><?php echo $args['description']?></div>
                <button id="limit_contributors_add_rule" type="button">ADD RULE</button>
                <div id="limit_contributors_rules">
                <?php if ($rules): ?>
                    <?php foreach ($rules as $user_id => $post_ids): ?>
                    <div class="limit_contributors_row">
                        <?php echo $this->build_settings_html_row($all_users, $user_id, $post_ids); ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                </div>
            </div>
            
            <script>
            
                function removeRule() {
                    var deleteDiv = this.parentElement;
                    deleteDiv.parentElement.removeChild(deleteDiv);
                }

                var removeRuleButtons = document.querySelectorAll("button.limit_contributors_remove_rule");
                console.log(removeRuleButtons.length);
                for (var i=0,imax=removeRuleButtons.length; i < imax; i++) {
                    removeRuleButtons[i].addEventListener("click", removeRule)
                }
            
                var addRuleButton = document.querySelector("#limit_contributors_add_rule");
                var rulesDiv = document.querySelector("#limit_contributors_rules");
                
                addRuleButton.addEventListener("click", function() {
                    
                    var rule = document.createElement("div");
                    rule.className = "limit_contributors_row";
                    rule.innerHTML = "<?php echo $this->build_settings_html_row($all_users); ?>";
                    
                    rulesDiv.appendChild(rule);
                    
                    rule.querySelector(".limit_contributors_remove_rule").addEventListener("click", removeRule);
                    
                });
                
            </script>
            
            <?php  
        }
        
        
        function build_settings_html_row($users, $selected = "", $posts = "") {
            
            $html = "USER: <select name='revisionize_limit_contributors_settings[rules][users][]'>";
            $html .= "<option></option>";
            foreach ($users as $user) {
                $option = "<option value='{$user->ID}'";
                if ($selected && ((int)$selected === (int)$user->ID)) { 
                    $option .= " selected";
                };
                $option .= ">";
                $option .= $user->user_login . " (" . $user->ID . ")";
                $option .= "</option>";
                $html .= $option;
            };
            $html .= "</select>";
            $html .= "POSTS: <input type='text' name='revisionize_limit_contributors_settings[rules][posts][]' value='{$posts}'>";
            $html .= "<button type='button' class='limit_contributors_remove_rule'>REMOVE RULE</button>";
            
            return $html;
            
        }
     
     
    }
    
        
    $revisionize_limit_contributors = new RevisionizeLimitContributors;
    
    $revisionize_limit_contributors->init();
    
}