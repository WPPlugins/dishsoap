<?php

/*
Plugin Name: DishSoap
Plugin URI: http://withan.es/dishsoap
Description: Cleanup those old posts with DishSoap. DishSoap is an post unpublishing plugin for those that want to automatically unpublish or unsticky a post on a specified date and time.
Version: 1.2
Author: Mark Thomes (with an Es)
Author URI: http://withan.es/dishsoap
*/


//////-ADMIN AREA-//////
//                    //
//  PERSONAL options  //
//                    //
////////////////////////

// CHOOSE to see DEBUG code
$dishsoap_debug = false;

// TARGET different Post Type
$ds_post_type = "post"; // LEAVE blank "" if you want it to apply to all post_types
// WARNING -- Changing this after expirations have
//            already been set for a post will that
//            expiraton will no longer be editable.


////-ADMIN AREA-/////
//                 //
//  Style the box  //
//                 //
/////////////////////

function dishsoap_style() { 

  // STYLE dishsoap
  wp_enqueue_style( 'my-style', plugins_url( '/style.css', __FILE__ ), false, '1.0', 'all' ); // Inside a plugin

}

add_action('admin_head', 'dishsoap_style');


////////-BOTH AREAS-/////////
//                         //
//  Cleanup Expired Posts  //
//   (this does it all)    //
//                         //
/////////////////////////////

add_action( 'pre_get_posts', 'dishsoap_hide_post' );


/////-ADMIN AREA-////
//                 //
//  SETUP actions  //
//                 //
/////////////////////

add_action( 'load-post.php', 'dishsoap_meta_boxes_setup' );
add_action( 'load-post-new.php', 'dishsoap_meta_boxes_setup' );


//////////-ADMIN AREA-/////////
//                           //
//  Dishsoap setup function  //
//                           //
///////////////////////////////

function dishsoap_meta_boxes_setup() {

  // ADD meta_box HOOK
  add_action( 'add_meta_boxes', 'dishsoap_add_meta_boxes' );

  // ADD save_post HOOK
  add_action( 'save_post', 'dishsoap_save_meta', 10, 2 );

}


////////-ADMIN AREA-///////
//                       //
//  CREATE dishsoap box  //
//                       //
///////////////////////////

function dishsoap_add_meta_boxes() {

  global $ds_post_type;

  add_meta_box(
    'dishsoap-meta',
    esc_html__( 'Dishsoap', 'dishsoap' ),
    'dishsoap_meta_box',
    "$ds_post_type",
    'side',
    'default'
  );

}


///////-ADMIN AREA-///////
//                      //
//  CUSTOM post_status  //
//                      //
//////////////////////////

function dishsoap_expired_post_status() {
  
  // DEFINE custom post_status
  register_post_status( 'expired', array(
      'label' => _x( 'Expired', 'post' ),
      'public' => false,
      'exclude_from_search' => true,
      'show_in_admin_all_list' => true,
      'show_in_admin_status_list' => true,
      'label_count'               => _n_noop( 'Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>' ),
  ) );

}

add_action( 'init', 'dishsoap_expired_post_status' );


////////-ADMIN AREA-////////
//                        //
//  DISPLAY dishsoap box  //
//                        //
////////////////////////////

function dishsoap_meta_box( $object, $box ) {

  // SET timezone
  date_default_timezone_set(get_option('timezone_string'));
  
  // DEFINE
  global $wp_query, $wpdb, $dishsoap_debug;
  $tnow = time();

  // SESSION START (used to display errors and maintain form)
  @session_start();

  wp_nonce_field( basename( __FILE__ ), 'dishsoap_meta_nonce' ); ?>

  <p>
    Unpublish post on...
    
    <?php

      // GET dishsoap_meta
      $dishsoap_uts = get_post_meta( $object->ID, 'dishsoap_meta', true );
      
      // SPLIT dishsoap_meta into TIMESTAMP and OPTIONS
      $dishsoap_meta_info = explode(":", $dishsoap_uts);
      
      // CONVERT dishsoap_meta_info (human readable)
      $dishsoap_timestring = @date('m,d,Y,H,i', $dishsoap_meta_info[0]);

      // EXPLODE dishsoap_timestring
      $dishsoap_values = explode(",", $dishsoap_timestring);

      // STATUS MESSAGES
      if ( $dishsoap_meta_info[1] == '0' ) {
        $dishsoap_expired_status = "<span class=\"statuspost\">Post set to Expire</span>";
      } elseif ( $dishsoap_meta_info[1] == '1') {
        $dishsoap_expired_status = "<span class=\"expiredpost\">Post Expired</span>";
      } elseif ( $dishsoap_meta_info[1] == '2') {
        $dishsoap_expired_status = "<span class=\"statuspost\">Post set to Unstick</span>";
      } elseif ( $dishsoap_meta_info[1] == '3') {
        $dishsoap_expired_status = "<span class=\"expiredpost\">Post Unstuck</span>";
      }

    ?>
      <br>
    <?php
      // STATUS message DISPLAY
      if (!empty($_SESSION['date_fail'])) {
        echo "<span class=\"expiredpost\">";
        echo $_SESSION['date_fail'];
        echo "</span>";
        $_SESSION['date_fail'] = '';
      } else {
        echo $dishsoap_expired_status;
      }
    ?>
    <div class="timestamp-wrap">
      <select id="mm" name="dishsoap-meta-month" tabindex="4">
        <?php

          // RENDER select List
          $dishsoap_months = array('01'=>"01-Jan",  
                                   '02'=>"02-Feb",  
                                   '03'=>"03-Mar",  
                                   '04'=>"04-Apr",  
                                   '05'=>"05-May",  
                                   '06'=>"06-Jun",  
                                   '07'=>"07-Jul",  
                                   '08'=>"08-Aug",  
                                   '09'=>"09-Sep",  
                                   '10'=>"10-Oct",  
                                   '11'=>"11-Nov",  
                                   '12'=>"12-Dec");

          // MAINTAIN select List
          foreach ($dishsoap_months as $k => $v) {
            $dishsoap_selected = '';
            if (isset($dishsoap_values[0]) && $dishsoap_values[0] == $k || $_SESSION['dishsoap-meta-month'] == $k ) {
               $dishsoap_selected = ' selected="true"';
            }
            echo("<option value=\"$k\"$dishsoap_selected>$v</option>");
          }

          // IF SESSION or DATABASE varibles
          if(!empty($_SESSION['dishsoap-meta-day'])) {
            $ds_day = $_SESSION['dishsoap-meta-day'];
          } else {
            $ds_day = $dishsoap_values[1];
          }

          if(!empty($_SESSION['dishsoap-meta-year'])) {
            $ds_year = $_SESSION['dishsoap-meta-year'];
          } else {
            $ds_year = $dishsoap_values[2];
          }

          if(!empty($_SESSION['dishsoap-meta-hour'])) {
            $ds_hour = $_SESSION['dishsoap-meta-hour'];
          } else {
            $ds_hour = $dishsoap_values[3];
          }

          if(!empty($_SESSION['dishsoap-meta-minute'])) {
            $ds_minute = $_SESSION['dishsoap-meta-minute'];
          } else {
            $ds_minute = $dishsoap_values[4];
          }

        ?>
      </select>
        <input type="text" id="jj" size="2" maxlength="2" tabindex="4" autocomplete="off"
          name="dishsoap-meta-day"
          value="<?php echo $ds_day; ?>"
        >,
        <input type="text" id="aa" size="4" maxlength="4" tabindex="4" autocomplete="off"
          name="dishsoap-meta-year"
          value="<?php echo $ds_year; ?>"
        > @
        <input type="text" id="hh"  size="2" maxlength="2" tabindex="4" autocomplete="off"
          name="dishsoap-meta-hour"
          value="<?php echo $ds_hour; ?>"
        > :
        <input type="text" id="mn" size="2" maxlength="2" tabindex="4" autocomplete="off"
          name="dishsoap-meta-minute"
          value="<?php echo $ds_minute; ?>"
        >
        <br>
        <label for="ds_option" id="ds_option_select">Option:
          <select id="ds_option" name="dishsoap-meta-option" tabindex="4">
            <?php
              // SET default OPTION
              $ds_default_option = "Choose...";
              if (isset($dishsoap_meta_info[1]) && $dishsoap_meta_info[1] >= 0) {
                $ds_default_option = "Clear..."; // CHANGE to CLEAR after data is present
              }
              // RENDER select List
              $dishsoap_options = array(''=>"$ds_default_option",
                                       '0'=>"Expire this post",
                                       '2'=>"Unstick this post");

              // MAINTAIN Select List
              foreach ($dishsoap_options as $k => $v) {
                $dishsoap_opselected = '';
                if (isset($_SESSION['dishsoap-meta-option']) && $_SESSION['dishsoap-meta-option'] == $k) {
                  $dishsoap_opselected = ' selected="true"';
                } elseif ( (isset($dishsoap_meta_info[1]) && $dishsoap_meta_info[1] == $k) || (isset($dishsoap_meta_info[1]) && $dishsoap_meta_info[1] == ($k + 1)) ) {
                    $dishsoap_opselected = ' selected="true"';
                }
                echo("<option value=\"$k\"$dishsoap_opselected>$v</option>");
              }

              // SESSION CLEAR (only display errors once)
              @session_destroy();

            ?>
          </select>
        </label>
      </div>
      <?php if($dishsoap_debug) { ?>
              <div class="dishsoap_debug" debugstyle="margin-top:1em; border: 1px solid #9da53c; padding: 5px;"><b>Debug Code Area:</b><br>
      <?php
              // RENDER timestamp DEBUG code
              echo "Current timestamp: $tnow";
              echo "<br>";
              echo "Dishsoap timestamp: $dishsoap_uts";
      ?>
      </div>
      <?php } ?>
  </p>
<?php } // <-- END dishsoap_meta_box


///////////-ADMIN AREA-////////////
//                               //
//  Save the Dishsoap Form Data  //
//     (mutistep process)        //
//                               //
///////////////////////////////////

function dishsoap_save_meta( $post_id, $post ) {

  // SET timezone
  date_default_timezone_set(get_option('timezone_string'));

  // Start SESSION for validation ERRORS
  @session_start();

  // CHECK origin of post data 
  if ( !isset( $_POST['dishsoap_meta_nonce'] ) || !wp_verify_nonce( $_POST['dishsoap_meta_nonce'], basename( __FILE__ ) ) )
    return $post_id;

  // CHECK post_type
  $post_type = get_post_type_object( $post->post_type );

  // CHECK current_user_can
  if ( !current_user_can( $post_type->cap->edit_post, $post_id ) )
    return $post_id;

  // GET meta_key
  $meta_key = 'dishsoap_meta';

  // GET current meta_value
  $meta_value = get_post_meta( $post_id, $meta_key, true );

  // SET or CLEAR meta_value
  if ( $_POST['dishsoap-meta-option'] == '' ) {
    
    // DELETE dishsoap_meta
    delete_post_meta( $post_id, $meta_key, $meta_value );
  
  } else {

    //-------------------//
    //  Form Validation  //
    //-------------------//

    // Date VALIDATION
    $valid = true;
    $_SESSION['date_fail'] = '';
    if (empty($_POST['dishsoap-meta-day'])) {
       $_SESSION['date_fail'] .= "Missing DAY value<br>";
       $valid = false;
    }
    if (empty($_POST['dishsoap-meta-year'])) {
       $_SESSION['date_fail'] .= "Missing YEAR value<br>";
       $valid = false;
    }
    if (empty($_POST['dishsoap-meta-hour'])) {
       $_SESSION['date_fail'] .= "Missing HOUR value<br>";
       $valid = false;
    }
    if (empty($_POST['dishsoap-meta-minute'])) {
       $_SESSION['date_fail'] .= "Missing MINUTE value<br>";
       $valid = false;
    } 

    // Sticky VALIDATION
    if ( !isset($_POST['sticky']) && $_POST['dishsoap-meta-option'] == '2' ) {
      $_SESSION['date_fail'] .= "Stick this post... not checked";
      $valid = false;
    }

    // SESSION WRITE
    if(!$valid) {
      $_SESSION['dishsoap-meta-month']  = $_POST['dishsoap-meta-month'];
      $_SESSION['dishsoap-meta-day']    = $_POST['dishsoap-meta-day'];
      $_SESSION['dishsoap-meta-year']   = $_POST['dishsoap-meta-year'];
      $_SESSION['dishsoap-meta-hour']   = $_POST['dishsoap-meta-hour'];
      $_SESSION['dishsoap-meta-minute'] = $_POST['dishsoap-meta-minute'];
      $_SESSION['dishsoap-meta-option'] = $_POST['dishsoap-meta-option'];
      return $post_id;
    }

    //----------------//
    //  Data Writing  //
    //----------------//

    // CONVERTING POST DATA
    $dishsoap_meta_string = $_POST['dishsoap-meta-year'] . "-" . $_POST['dishsoap-meta-month'] . "-" . $_POST['dishsoap-meta-day'] . " " . $_POST['dishsoap-meta-hour'] . ":" . $_POST['dishsoap-meta-minute'] . ":00";

    // DATE DATA to TIMESTAMP
    $dishsoap_meta_timestamp = strtotime($dishsoap_meta_string);

    // APPENDA meta_option
    $new_meta_value = $dishsoap_meta_timestamp . ":" . $_POST['dishsoap-meta-option'];

    // WRITE or UPDATE dishsoap_meta DATA
    if ( $new_meta_value && '' == $meta_value ) {
      add_post_meta( $post_id, $meta_key, $new_meta_value, true );
    } elseif ( $new_meta_value != $meta_value) {
      update_post_meta( $post_id, $meta_key, $new_meta_value );
    }

  }

}


///////-USER AREA-////////
//                      //
//  Hide Expired Posts  //
//                      //
//////////////////////////

function dishsoap_hide_post() {

  // DEFINE
  global $wp_query, $wpdb;
  $tnow = time();

  // QUERY wp_postmeta
  $query = "SELECT * FROM $wpdb->postmeta WHERE (meta_key='dishsoap_meta' AND SUBSTRING_INDEX(meta_value,':',1) <= $tnow AND SUBSTRING(meta_value,-1) = 0) OR (meta_key='dishsoap_meta' AND SUBSTRING_INDEX(meta_value,':',1) <= $tnow AND SUBSTRING(meta_value,-1) = 2)";

  // LOOP through results
  foreach( $wpdb->get_results($query) as $key => $row) {
    
    // DEFINE meta_values
    $ds_postid = $row->post_id;
    $ds_timestamp = $row->meta_value;

    // EXPLODE meta_values
    $ds_metavalues = explode(":", $ds_timestamp);

    // ADJUST meta_values
    $ds_plus_one = $ds_metavalues[1] + 1; // add 1 to meta_data status
    $new_ds_meta_value = $ds_metavalues[0] . ":" . $ds_plus_one;

    // EXPIRE QUERY'S
    if ($ds_metavalues[1] == 0) { // EXPIRED UPDATE

      // UPDATE post_status
      $query = "UPDATE $wpdb->posts SET post_status = 'expired' WHERE ID = $ds_postid;";
      $wpdb->query($query);
 
      // UPDATE meta_value
      $query = "UPDATE $wpdb->postmeta SET meta_value = '$new_ds_meta_value' WHERE post_id = $ds_postid;";
      $wpdb->query($query);

    } elseif ($ds_metavalues[1] == 2) { // STICKY UPDATE

      // UPDATE post_option
      $ds_stickies = get_option('sticky_posts');
      if ( !is_array($ds_stickies) )
        return;
      if ( ! in_array($ds_postid, $ds_stickies) )
        return;
      $offset = array_search($ds_postid, $ds_stickies);
      if ( false === $offset )
        return;
      array_splice($ds_stickies, $offset, 1);
      update_option('sticky_posts', $ds_stickies);

      // UPDATE meta_value
      $query = "UPDATE $wpdb->postmeta SET meta_value = '$new_ds_meta_value' WHERE post_id = $ds_postid;";
      $wpdb->query($query);
    }
    
  } // END LOOP

}

?>