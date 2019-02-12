<?php
/*
Plugin Name: Slack Embed
Plugin URI: 
Description: Embed a Slack channel into your page
Version: 1.0.0
Author: Zeljko Smiljanic
Author URI: zeksmcode@gmail.com
*/

defined( 'ABSPATH' ) || die( 'No direct script access allowed!' );

class WP_Slack_Channel_Embed {
    
    protected $embedded_channels = [];
    
    public function __construct() {
        
        add_shortcode("slack", array ($this, "slack_shortcode"));
        add_action( 'admin_menu', array($this, 'add_plugin_options'));
        add_action( 'wp_ajax_check_channel', array($this, 'check_channel') );
        add_action( 'wp_ajax_nopriv_check_channel', array($this, 'check_channel') );
        
    }
    
    public function add_plugin_options() {
        add_options_page( 'Slack Embed Options', 'Slack Embed', 'manage_options', 'slack-embed-options', array($this, 'setup_menu'));
    }

    public function setup_menu() {
        if ( !current_user_can( 'manage_options' ) )  {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }
        
        // variables for the field and option names 
        $channel_list_opt = 'slack_channel_embed_list';
        $token_opt = "slack_token";
        $number_opt = "slack_message_number";
        
        $hidden_field_name = 'slack_submit_hidden';
        $channel_field_name = 'slack_channel_input';
        $token_field_name = "slack_token";
        $message_number_field_name = "slack_message_number";

        // See if the user has posted us some information
        // If they did, this hidden field will be set to 'Y'
        if( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ) {
            
            $channel_list_val = $_POST[ $channel_field_name ];
            $channel_val_array = array_map('trim',explode(",",$channel_list_val));
            update_option( $channel_list_opt, $channel_val_array );
            
            $token_val = $_POST[ $token_field_name ];
            update_option($token_opt, $token_val);
            
            $number_val = $_POST[ $message_number_field_name ];
            update_option($number_opt, $number_val);
            
            echo "<div class='updated'><p><strong>Plugin settings updated!</strong></p></div>";

        } else {
            
            $channel_list_val = get_option( $channel_list_opt );
            if ($channel_list_val === false) {
                $channel_list_val = "";
            } else {
                $channel_list_val = implode(",", $channel_list_val);
            }
            
            $token_val = get_option( $token_opt );
            if ($token_val === false) {
                $token_val = "";
            }
            
            $number_val = get_option( $number_opt );
            if ($number_val === false) {
                $number_val = "";
            }
               
        }

        echo '<div class="wrap">';
        echo "<h2>Slack Options</h2>";
        
?>

<form name="form1" method="post" action="">
<input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">

<p>Slack Channels to Monitor:
<input type="text" name="<?php echo $channel_field_name; ?>" value="<?php echo $channel_list_val; ?>" size="60">
</p><hr />

<p>Slack Token:
<input type="text" name="<?php echo $token_field_name; ?>" value="<?php echo $token_val; ?>" size="30">
</p><hr />

<p>Number of messages to display per channel (up to 100):
<input type="text" name="<?php echo $message_number_field_name; ?>" value="<?php echo $number_val; ?>" size="30">
</p><hr />

<p class="submit">
<input type="submit" name="Submit" class="button-primary" value="SAVE" />
</p>

</form>
</div>

<?php

    }
    
    
    public function slack_shortcode($atts) {
        
        $channelAtts = $atts["channel"];
        
        $channels = explode(",", $channelAtts);
        
        $setupChannels = get_option("slack_channel_embed_list");
        
        $tabs = [];
        
        foreach ($channels as $channel) {
        
            if (in_array($channel, $setupChannels)) {
                
                if (count($this->embedded_channels) === 0) {
                    $this->enqueue_js();
                    $this->enqueue_css();
                }
                
                $this->embedded_channels[] = $channel;
                
                $this->update_slack_data($channel);
                
                $channelName = $this->get_channel_name($channel);
                
                $tabs[$channel] = $channelName;
                
            }
            
        }
        
        $tabNum = count($tabs);
        
        if ($tabNum > 0) {
            
            $dir = wp_upload_dir()["baseurl"];
            $ajaxurl = admin_url( 'admin-ajax.php' );
            
            if ($tabNum > 1) {
                
                $output = "
                <div class='slack_embed_tabs'>
                    <div class='slack_embed_tabs_labels'>";
                
                $counter = 1;
                foreach ($tabs as $channel => $channelName) {
                    
                    $output = $output . "
                    <div class='slack_embed_tabs_label";
                    
                    if ($counter === 1) {
                        $output = $output . " slack_embed_tabs_label_selected";
                    }
                    
                    $output = $output . "' id='slack_embed_tab-{$channel}'>
                    {$channelName}
                    </div>";
                    
                    $counter++;
                    
                }
                
                $output = $output . "
                </div>
                <div class='slack_embed_tabs_content'>";
                
                $counter = 1;
                foreach ($tabs as $channel => $channelName) {
                    
                    $output = $output . "
                    <div class='slack_channel_embed";
                    
                    if ($counter === 1) {
                        $output = $output . " slack_channel_embed_selected";
                    }
                    
                    $output = $output . "' id='slack-{$channel}'>
                        <div class='slack_embed_loading'>LOADING CHANNEL, PLEASE WAIT</div>
                    </div>";
                    
                    $counter++;
                    
                }
                
                $output = $output . "
                    </div>
                </div>";
                
                
            } else {
        
                $output = "
                <div class='slack_channel_name'>{$channelName}</div>
                <div class='slack_channel_embed' id='slack-{$channel}'>
                    <div class='slack_embed_loading'>LOADING CHANNEL, PLEASE WAIT</div>
                </div>";
                
            }
            
            $output = $output . "
            <script>
            var slackEmbedWPUploadDir = '{$dir}';
            var ajaxurl = '{$ajaxurl}';
            </script>";
            
            return $output;
        
        } else {
            
            return;
            
        }
        
    }
    
    protected function get_channel_name($channel) {
        
        $token = get_option("slack_token");
        $url = "https://slack.com/api/channels.info?token=".$token."&channel=".$channel;

        $response = wp_remote_get($url);
        if ( is_array( $response ) ) {
          $header = $response['headers']; // array of http header lines
          $body = $response['body']; // use the content
        }
        
        $body = json_decode($body, true);
        
        $name = "#" . $body["channel"]["name"];
        
        return $name;
        
    }
    
    protected function update_slack_data($channel) {
        
        update_option("slack_update_last_ran", time());
        $messages = $this->get_channel_history($channel);
        $users = $this->get_user_info($messages, $channel);
        $data = [];
        $data["users"] = $users;
        $data["messages"] = $messages;
        $this->save_channel_history($channel, $data);
        update_option("slack_update_last_ran", time());
        
    }
    
    public function check_channel() {
        
        $channel = $_GET["channel"];
        $last_ran = get_option("slack_update_last_ran");
        if (time() - $last_ran > 10) {
            $this->update_slack_data($channel);  
        }
            
        $data = $this->load_channel_history($channel);
        echo $data;
        wp_die();
        
    }
    
    
    protected function enqueue_js() {
        
		$js_file = 'js/slack-embed.js';
		$js_url = plugins_url($js_file, __FILE__);
		wp_enqueue_script('slack_embed_js', $js_url, array(), false, true);
        
	}
    
    protected function enqueue_css() {
        
		$css_file = 'css/slack-embed.css';
		$css_url = plugins_url($css_file, __FILE__);
		wp_enqueue_style('slack_embed_css', $css_url);
        
	}
    
    protected function get_channel_history($channel) {
        
        $token = get_option("slack_token");
        $count = get_option("slack_message_number");
        
        $url = "https://slack.com/api/channels.history?token=".$token."&channel=".$channel;
        if ($count) {
            $url = $url . "&count=" . (string)$count; 
        }

        $response = wp_remote_get($url);
        if ( is_array( $response ) ) {
          $header = $response['headers']; // array of http header lines
          $body = $response['body']; // use the content
        }
        
        $body = json_decode($body, true);
        $messages = $body["messages"];
        
        return $messages;
        
    }
    
    protected function get_user_info($messages, $channel) {
        
        $users = [];
        
        foreach ($messages as $message) {
            $user = $message["user"];
            $users[$user] = "";
            $text = $message["text"];
            preg_match_all("/<@(.*?)>/", $text, $matches);
            foreach ($matches[1] as $m) {
               $users[$m] = "";
            }
        }
        
        $history = $this->load_channel_history($channel);
        if ($history) {
            $cachedUsers = json_decode($history)->users;
        } else {
            $cachedUsers = [];
        }
        
        
        foreach ($users as $key => $value) {
            
            if (!array_key_exists($key, $cachedUsers) OR ((time() - $cachedUsers->$key->lastCheck) > 300)) {
        
                $token = get_option("slack_token");
                $url = "https://slack.com/api/users.info?token={$token}&user={$key}";
                $response = wp_remote_get($url);
                if ( is_array( $response ) ) {
                  $header = $response['headers']; // array of http header lines
                  $body = $response['body']; // use the content
                }
                $body = json_decode($body, true);
                
                $user = $body["user"];
                $name = $user["name"];
                $realName = $user["real_name"];
                $avatar = $user["profile"]["image_24"];
                
                $users[$key] = ["name" => $name, "realName" => $realName, "avatarLink" => $avatar, "lastCheck" => time()];
                
            } else {
                $users[$key] = $cachedUsers->$key;
            }
            
        }
        
        return $users;
    }
    
    protected function save_channel_history($channel, $messages) {
        
        $upload_dir = wp_upload_dir();
        $plugin_save_dir = $upload_dir['basedir'] . '/slack-embed';
        wp_mkdir_p($plugin_save_dir);
        
        file_put_contents($plugin_save_dir . "/{$channel}.json", wp_json_encode($messages));
        
    }
    
    protected function load_channel_history($channel) {
        
        $upload_dir = wp_upload_dir();
        $plugin_save_dir = $upload_dir['basedir'] . '/slack-embed';
        $file = $plugin_save_dir . "/{$channel}.json";
        
        if (file_exists($file)) {
        
            $data = file_get_contents($file);
            return $data;
            
        }
        
        else {
            return false;
        }
        
    }
    
    protected function log($message) {
        $upload_dir = wp_upload_dir();
        $plugin_save_dir = $upload_dir['basedir'] . '/slack-embed';
        file_put_contents("{$plugin_save_dir}/log.txt", time(). " - " . $message . PHP_EOL, FILE_APPEND);
    }
    
}

new WP_Slack_Channel_Embed;