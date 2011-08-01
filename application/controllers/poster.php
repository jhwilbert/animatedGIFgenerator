<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * Required software: http://www.tcpdf.org/download.php
 * TCPDF integartion: http://codeigniter.com/wiki/TCPDF_Integration/
 */

class Poster extends CI_Controller {

	function _remap() {
		$username = $this->uri->segment(2);	
	    $artwork_url = isset($_GET['artwork']) ? urldecode(filter_var($_GET['artwork'])) : null;
	    $album_id = isset($_GET['albumid']) ? urldecode(filter_var($_GET['albumid'])) : null;
	
		if($username and $artwork_url) { 
			$this->index($username,$artwork_url,$album_id); 
		}      
	}
	
	public function index($username, $artwork_url, $album_id) {
		// load values from config
		$tmp_dir = $this->config->item('tmp_dir');
		$poster_dir = $this->config->item('poster_dir');
		
		// check if poster already exists and create it if it doesn't
		$poster_path = $poster_dir.$username."_".(isset($album_id) ? $album_id.'_' : '').basename($artwork_url).".pdf";


		if(!file_exists($poster_path)) {
			// load the artwork image and save it to file
			$artwork_path = $tmp_dir.$username."_".(isset($album_id) ? $album_id.'_' : '').basename($artwork_url)."_".rand(1,999999999);
			$error = $this->loadImage($artwork_url, $artwork_path);
			if(!$error) {
				// load values from config
				$poster_font = $this->config->item('poster_font');
				$poster_texts = $this->config->item('poster_texts');
				$poster_texts[count($poster_texts)-1] = $poster_texts[count($poster_texts)-1].$username.(isset($album_id) ? '/'.$album_id : '');
				$poster_texts[count($poster_texts)-2] = $poster_texts[count($poster_texts)-2].$username.(isset($album_id) ? '/'.$album_id : '');
				
				// create poster
				$error = $this->createPoster($poster_path, $username, $artwork_path, $poster_font, $poster_texts);
				
				// delete artwork file
				unlink($artwork_path);
				
				if($error) {
					echo $error;
					return;
				}
			} else {
				echo($error);
				return;
			}
		}
		
		$filename = $username."_".(isset($album_id) ? $album_id.'_' : '').basename($artwork_url).".pdf";
		
		header("Content-type: application/pdf");
		header("Content-Description: File Transfer"); 
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		header("Content-Transfer-Encoding: binary"); 
		header('Content-Length: '. filesize($poster_path)); 
 	    flush();
	 	readfile($poster_path);
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
	
	public function createPoster($destination_poster_path, $username, $source_img_path, $font, $texts) {
		$this->load->library('Pdf');
		
		// create new PDF document
		$pdf = new Pdf(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false, $source_img_path);
		$pdf->setBackgroundImgPath($source_img_path);
		
		// set document information
		$pdf->SetTitle("Poster");
		
		// set header fonts
		$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
		
		// set default monospaced font
		$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
		
		//set margins
		$pdf->SetMargins(0, 0, 0);
		$pdf->SetHeaderMargin(0);
		$pdf->SetFooterMargin(0);
		
		// remove default footer
		$pdf->setPrintFooter(false);
		
		//disable auto page breaks
		$pdf->SetAutoPageBreak(FALSE, 0);
		
		//set image scale factor
		$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
		
		
		// main text
		$pdf->AddPage();
		$pdf->SetFillColor(0,0,0);
		$main_text = '';
		for($i = 0; $i < count($texts)-1; $i++) {
			$text_length = strlen($texts[$i]);
			$main_text = $main_text.'<br/><br/><span style="color: #FFF; font-family: arial; '.($text_length < 18 ? 'font-weight: bold;' : '').' font-size: '.($text_length < 18 ? '46' : '18').'pt;">'.$texts[$i].'</span>';
		}
		$main_text = $main_text.'<br/>';
		
		$pdf->writeHTMLCell(180, 0, 15, 155, $main_text, 0, 0, true, true, "C", false);
        
        
        // tearaway text        
        $pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'round', 'dash' => 4, 'color' => array(200, 200, 200)));
		
		$tearaway_count = 9;
		$tearaway_width = 210 / $tearaway_count;
		$url = $texts[count($texts)-1];
		for($i = 0; $i < $tearaway_count; $i++) {
			$shift = $tearaway_width * ($i+1);
			if($i < $tearaway_count-1) {
	        	$pdf->Line($shift, 211, $shift, 294);
	        	$scissors = '<img src="_frame/static/images/scissors.png"/>';			
				$pdf->writeHTMLCell(0, 0, $shift - 4.3, 286, $scissors, 0, 0, false, true, "L", false);
	        }
		}
		$pdf->Rotate(90, 145.5, 147.5);	
		for($i = 0; $i < $tearaway_count; $i++) {
			$shift = $tearaway_width * ($i+1);
			$tearaway_text = '<div style="color: #000; font-family: helvetica; font-weight: bold; font-size: 9pt;">'.$url.'</div>';			
			$pdf->writeHTMLCell(0, 0, 0, $shift - $tearaway_width/2, $tearaway_text, 0, 0, false, true, "L", false);
		}
        
        
        // generate PDF and save to file
        $poster = $pdf->Output($destination_poster_path, "S"); 
		if(strlen($poster) > 7) {
			$output = fopen($destination_poster_path, 'w');
			fwrite($output, $poster);
			fclose($output);
		} else {
			$error = 'PDF poster could not be created';
		}
		
		return isset($error) ? $error : NULL;
	}
	
}

/* End of file poster.php */
/* Location: ./application/controllers/poster.php */