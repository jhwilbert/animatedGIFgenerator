<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');


class Banner extends CI_Controller {
	
	function _remap() {
		// $format == 1; - leaderboard
		// $format == 2; - button
		$format = $this->uri->segment(2);
		$username = $this->uri->segment(3);
	    $artwork_url = isset($_GET['artwork']) ? urldecode(filter_var($_GET['artwork'])) : null;
	    $album_id = isset($_GET['albumid']) ? urldecode(filter_var($_GET['albumid'])) : null;
	
		if($format and $username and $artwork_url) { 
			$this->index($format,$username,$artwork_url,$album_id); 
		}      
	}
	
	public function index($format, $username, $artwork_url, $album_id) {

		// load values from config
		$tmp_dir = $this->config->item('tmp_dir');
		$banner_dir = $this->config->item('banner_dir');
		
		// check if banner already exists and create it if it doesn't
		$banner_path = $banner_dir.$format."/".$username."_".(isset($album_id) ? $album_id.'_' : '').basename($artwork_url).".gif";


		if(!file_exists($banner_path)) {
			// load the artwork image and save it to file
			$artwork_path = $tmp_dir.$format."/".$username."_".(isset($album_id) ? $album_id.'_' : '').basename($artwork_url)."_".rand(1,999999999);
			$error = $this->loadImage($artwork_url, $artwork_path);
			if(!$error) {
				// load values from config
				$banner_font = $this->config->item('banner_font');
				$banner_texts = $this->config->item('banner_texts');
				$banner_texts[count($banner_texts)-1] = $banner_texts[count($banner_texts)-1].$username.(isset($album_id) ? '/'.$album_id : '');
				
				// create banner frames
				$frames = array();
				for($i = 0; $i < count($banner_texts); $i++) {
					// create frame
					$frame_path = $tmp_dir.$format."/".$i."_".$username."_".(isset($album_id) ? $album_id.'_' : '').basename($artwork_url)."_".rand(1,999999999).".gif";
					$error = $this->cropWatermarkImage($format, $artwork_path, $frame_path, $banner_font, $banner_texts[$i]);
					if($error) {
						break;
					}
					$frames[$i] = $frame_path;
				}
				
				// delete artwork file
				unlink($artwork_path);
				
				if($error) {
					echo $error;
					return;
				}
				
				// create animated banner from frames
				$error = $this->createBanner($frames, $banner_path);
				
				// delete frames
				for($i = 0; $i < count($frames); $i++) {
					unlink($frames[$i]);
				}
				
				if($error) {
					echo($error);
					return;
				}
			} else {
				echo($error);
				return;
			}
		}
		
		header('Content-Type: image/gif');
 	    flush();
	 	readfile($banner_path);
	}
	
	public function loadImage($url, $path) {
		if(strtolower(substr($url, 0, 7)) == 'http://' || strtolower(substr($url, 0, 8) == 'https://')) {
			$output = fopen($path, 'w');
			
			$curl = curl_init($url);
			$options = array(CURLOPT_HEADER => false, CURLOPT_TIMEOUT => 30, CURLOPT_FILE => $output, CURLOPT_FOLLOWLOCATION => true);
			curl_setopt_array($curl, $options);
			$result = curl_exec($curl);
			$content_type = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
			curl_close($curl);
			
			fclose($output);
		} else {
			$error = "Requested resource ".$url.' could not be loaded';
		}
		
		return isset($error) ? $error : NULL;
	}
	
	public function cropWatermarkImage($format, $source_img_path, $destination_img_path, $font, $text) {
		$image_info = getImageSize($source_img_path);
		switch ($image_info['mime']) {
			case 'image/gif':
				if (imagetypes() & IMG_GIF) {
					$source_img = imageCreateFromGIF($source_img_path) ;
				} else {
					$error = 'GIF images are not supported';
				}
				break;
			case 'image/jpeg':
				if (imagetypes() & IMG_JPG)  {
					$source_img = imageCreateFromJPEG($source_img_path) ;
				} else {
					$error = 'JPEG images are not supported';
				}
				break;
			case 'image/png':
				if (imagetypes() & IMG_PNG)  {
					$source_img = imageCreateFromPNG($source_img_path) ;
				} else {
					$error = 'PNG images are not supported';
				}
				break;
			case 'image/wbmp':
				if (imagetypes() & IMG_WBMP)  {
					$source_img = imageCreateFromWBMP($source_img_path) ;
				} else {
					$error = 'WBMP images are not supported';
				}
				break;
			default:
				$error = ($image_info['mime'] != NULL ? $image_info['mime'].' images are not supported' : basename($source_img_path).' is not an image file');
				break;
		}
		
		if (!isset($error)) {
			// get image width and height
			$source_img_width = imagesx($source_img);
			$source_img_height = imagesy($source_img);
			
			// crop and watermark image
			if($format == 1) { // leaderboard
				$destination_img_width = 468;
				$destination_img_height = 60;
				// crop
				$destination_img = imageCreateTrueColor($destination_img_width, $destination_img_height);			
				imageCopyResampled($destination_img, $source_img, 0, 0, 0, $source_img_height/2, $destination_img_width, $destination_img_height, $destination_img_width, $destination_img_height);
				
				// remove forced breaks
				$text = str_replace("|", "", $text);
				
				// text bounding box
				$text_length = strlen($text);
				$font_size = ($text_length < 14 ? 36 : ($text_length < 24 ? 24 : 34 - $text_length/2));
				
				$this->watermarkImage($destination_img, $font_size, $font, $text, null);
				
			} else { // button
				$destination_img_width = 178;
				$destination_img_height = 150;
				// crop
				$destination_img = imageCreateTrueColor($destination_img_width, $destination_img_height);			
				imageCopyResampled($destination_img, $source_img, 0, 0, 0, $source_img_height/2, $destination_img_width, $destination_img_height, $destination_img_width, $destination_img_height);
					
				$words = preg_split("/[\\|]+/", $text);
				if(count($words) > 1) {
					for($i = 0; $i < count($words); $i++) {
						$text = trim($words[$i]);
						
						// text bounding box
						$text_length = strlen($text);
						$font_size = ($text_length < 7 ? 32 : 22 - $text_length/2);
						
						$position = (count($words) / 2) - ($i + 1);
						
						$this->watermarkImage($destination_img, $font_size, $font, $text, $position);
					}
				} else {
					// text bounding box
					$text_length = strlen($text);
					$font_size = ($text_length < 7 ? 32 : ($text_length < 12 ? 19 : 22 - $text_length/2));
					
					$this->watermarkImage($destination_img, $font_size, $font, $text, null);
				}
				
			}
			
			// save image to file and free the memory
			imageGIF($destination_img, $destination_img_path);
			imageDestroy($source_img);
			imageDestroy($destination_img);
		}
		return isset($error) ? $error : NULL;
		
	}
	
	public function watermarkImage($img, $font_size, $font, $text, $position) {
		$white = hexdec("0xFFFFFF");
		$black = hexdec("0x222222");
		$gray = hexdec("0x333333");
		
		if($font_size < 12)
			$font_size = 12;
			
		$bbox = imagettfbbox($font_size, 0, $font, $text);
		$tx = $bbox[0] + (imagesx($img) / 2) - ($bbox[4] / 2);
		$ty = $bbox[1] + (imagesy($img) / 2) - ($bbox[5] / 2) - $bbox[3];
		
		if(isset($position)) {
			$wh = ($bbox[7] + $bbox[1]) * -1 + 10;
			$ty = $ty - ($wh * $position) - $wh / 2;
		}
		
		// watermark
		imagefttext($img, $font_size, 0, $tx+2, $ty+2, $gray, $font, $text);
		imagefttext($img, $font_size, 0, $tx+1, $ty+1, $black, $font, $text);
		imagefttext($img, $font_size, 0, $tx, $ty, $white, $font, $text);
	}
	
	public function createBanner($frames, $destination_banner_path) {
		$speed = array();
		for($i = 0; $i < count($frames)-1; $i++) {
			$speed[$i] = 100;
		}
		$speed[count($frames)-1] = 500;
		
		$params = array($frames, $speed, 0, 2, 0, 0, 0, "url");
		$this->load->library('Gifencoder', $params);
		
		$animation = $this->gifencoder->GetAnimation();
		if(strlen($animation) > 7) {
			$output = fopen($destination_banner_path, 'w');
			fwrite($output, $animation);
			fclose($output);
		} else {
			$error = 'GIF animation could not be created';
		}
		
		return isset($error) ? $error : NULL;
	}
}

/* End of file banner.php */
/* Location: ./application/controllers/banner.php */