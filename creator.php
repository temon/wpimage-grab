<?php
/**
 * Plugin Name: Creator
 * Plugin URI: http://tmon.co/wpplugins
 * Description: Easy create post with external image without download it first.
 * Version: 1.0
 * Author: temon
 * Author URI: http://tmon.co/
 **/

require 'Fastimage.php';


/**
 * Adds a box to the main column on the Post and Page edit screens.
 */
function creator_add_meta_box() {

    $screens = array( 'post' );

    foreach ( $screens as $screen ) {

        add_meta_box(
            'myplugin_sectionid',
            __( 'External Image', 'myplugin_textdomain' ),
            'myplugin_meta_box_callback',
            $screen
        );
    }
}
add_action( 'add_meta_boxes', 'creator_add_meta_box' );


/**
 * Prints the box content.
 *
 * @param WP_Post $post The object for the current post/page.
 */
function myplugin_meta_box_callback( $post ) {
    global $post;
    // Use nonce for verification
    wp_nonce_field( plugin_basename( __FILE__ ), 'dynamicMeta_noncename' );
    ?>
    <div id="meta_inner">
    <?php

    //get the saved meta as an arry
    $songs = get_post_meta($post->ID,'songs',true);

    $c = 0;
    if ( count( $songs ) > 0 ) {
        foreach( $songs as $track ) {
            if ( isset( $track['title'] ) || isset( $track['track'] ) ) {
                printf( '<p>Song Title <input type="text" name="songs[%1$s][title]" value="%2$s" /> -- Track number : <input type="text" name="songs[%1$s][track]" value="%3$s" /><span class="remove">%4$s</span></p>', $c, $track['title'], $track['track'], __( 'Remove Track' ) );
                $c = $c +1;
            }
        }
    }

    ?>
    <span id="here"></span>
    <span class="add"><?php _e('Add URL'); ?></span>
    <script>
        var $ =jQuery.noConflict();
        $(document).ready(function() {
            var count = <?php echo $c; ?>;
            $(".add").click(function() {
                count = count + 1;

                $('#here').append('<p> Insert Url <input type="text" class="inputurl" name="url['+count+'][title]" value="" /> <span class="grab">Grab</span> <span class="save">SAVE</span><span class="remove">Remove </span></p>' );
                return false;
            });
            $(".save").live('click', function(){
            	var data=new Array(), parent = $(this).parent();

				$('.multiselect input[type=checkbox]:checked').map(function(_, el) {
				    data.push({title:parent.find(".title").val(), description:parent.find(".description").val(), url:$(el).val()});
				}).get();

				$.ajax({
                    url: "admin-ajax.php",
                    data: {
                        'action': 'saveurl',
                        'data':  data
                    },
                    dataType:"json",
                    type: "POST"
                })
                .done(function(msg){
                	parent.find(".multiselect").remove();    
                });
            });
            $(".grab").live('click', function(){
                var urlVal, parent;
                parent = $(this).parent();
                urlVal = parent.find('input').val();
                $.ajax({
                    url: "admin-ajax.php",
                    data: {
                        'action': 'submiturl',
                        'data':  urlVal
                    },
                    dataType:"json",
                    type: "POST"
                })
                .done(function(msg){
                	parent.append(msg.data);    
                });
            });
            $(".remove").live('click', function() {
                $(this).parent().remove();
            });
        });
    </script>
    <style type="text/css">
    	#myplugin_sectionid .inputurl {
			width: 400px;
    	}
    	#myplugin_sectionid .title {
		    position: absolute;
			top: 20px;
			right: 30px;
			width: 450px;
    	}
    	#myplugin_sectionid .description {
		    position: absolute;
		    top: 30px;
		    right: 30px;
		    margin-top: 30px;
		    width: 450px;
    	}
    	#myplugin_sectionid .checkboximg {
			right: 30px;
			position: absolute;
			top: 100px;
    	}
    	#myplugin_sectionid .grab,
    	#myplugin_sectionid .save,
    	#myplugin_sectionid .add,
    	#myplugin_sectionid .remove {
    		cursor: pointer;
    		background-color: #eee;
		    padding: 5px 10px;
		    border-radius: 5px;
		    margin: 0px 5px;
    	}
    	.multiselect {
    		padding: 20px 0px;	
    	}
    </style>
    </div><?php
}

/**
 * When the post is saved, saves our custom data.
 *
 * @param int $post_id The ID of the post being saved.
 */
function myplugin_save_meta_box_data( $post_id ) {

    // verify if this is an auto save routine.
    // If it is our form has not been submitted, so we dont want to do anything
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
        return;

    // verify this came from the our screen and with proper authorization,
    // because save_post can be triggered at other times
    if ( !isset( $_POST['dynamicMeta_noncename'] ) )
        return;

    if ( !wp_verify_nonce( $_POST['dynamicMeta_noncename'], plugin_basename( __FILE__ ) ) )
        return;

    // OK, we're authenticated: we need to find and save the data

    $songs = $_POST['songs'];

    update_post_meta($post_id,'songs',$songs);
}
add_action( 'save_post', 'myplugin_save_meta_box_data' );





// ajax grab image
add_action( 'wp_ajax_submiturl', 'submiturl' );

function submiturl() {

    try {
        $url = $_POST['data'];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $content = curl_exec($ch);
        curl_close($ch);
    } catch (Exception $e) {
        $curl_scraped_page = $e;
    }

    $result = "<div class=\"multiselect\">";

    $doc = new DOMDocument();
    $doc->loadHTML($content);
    $doc->preserveWhiteSpace = false;
    $xpath = new DOMXpath($doc);
    $imgs = $xpath->query("//img");

	for ($i=0; $i < $imgs->length; $i++) {
	    $img = $imgs->item($i);
	    $src = $img->getAttribute("src");
	    $image = new FastImage($src);
	    list($width, $height) = $image->getSize();
	    if ($width >= 550){
	        $result .= "<div style=\"position:relative;\">";
	        $result .= '<img src="' . $src . '" style="max-width:245px" />';
			$result .= '<input class="title" caption="Title here" type="text" />';
			$result .= '<input class="description" caption="Description here" type="text" />';
			$result .= '<input class="checkboximg" name="images[]" checked="checked" value="'. $src .'" type="checkbox" />';
	        $result .= "</div>";
	    }
	}
	$result .= "</div>";

    // ignore the request if the current user doesn't have
    // sufficient permissions
    if ( current_user_can( 'edit_posts' ) ) {

        // generate the response
        $response = json_encode( array( 'data' => $result, 'success' => true, 'source' => $curl_scraped_page) );

        // response output
        header( "Content-Type: application/json" );
        echo $response;
    }

    // IMPORTANT: don't forget to "exit"
    exit;
}


// ajax save image
add_action( 'wp_ajax_saveurl', 'saveurl' );

function saveurl() {
	$urls = $_POST['data'];

	foreach ($urls as $key => $value) {
		$title = $value["title"];
		$description = $value["description"];
		$url = $value["url"];

		$uploaddir = wp_upload_dir();

		$contents= file_get_contents($url);

		$file_info = new finfo(FILEINFO_MIME);  
		$mime_type = $file_info->buffer(file_get_contents($file));
		 
		switch($mime_type) {
			case "image/png" : 
				$filename = strtolower(str_replace(' ', '-', $title)) . '-' . uniqid() . '.png';
				$typefile = '.png';
				break;
		    default :
		    	$filename = strtolower(str_replace(' ', '-', $title)) . '-' . uniqid() . '.jpeg';
		    	$typefile = '.jpeg';
		    	break; 
		}
		
		if (file_exists($filename)){
			$filename = strtolower(str_replace(' ', '-', $title)) . '-' . uniqid() . $typefile;
		}

		$uploadfile = $uploaddir['path'] . '/' . $filename;
		$savefile = fopen($uploadfile, 'w');
		fwrite($savefile, $contents);
		fclose($savefile);

		$wp_filetype = wp_check_filetype(basename($filename), null );

		$attachment = array(
		    'post_mime_type' => $wp_filetype['type'],
		    'post_title' => $title,
			'post_excerpt' => $title,
		    'post_content' => $description,
		    'post_status' => 'inherit'
		);

		$attach_id = wp_insert_attachment( $attachment, $uploadfile );

		$imagenew = get_post( $attach_id );
		$fullsizepath = get_attached_file( $imagenew->ID );
		$attach_data = wp_generate_attachment_metadata( $attach_id, $fullsizepath );
		wp_update_attachment_metadata( $attach_id, $attach_data );
		add_post_meta( $attach_id, '_wp_attachment_image_alt', $title );
	}

	if ( current_user_can( 'edit_posts' ) ) {
		$response = json_encode( array( 'data' => $urls, 'success' => true) );
	    header( "Content-Type: application/json" );
	    echo $response;
	}

	exit();
}
