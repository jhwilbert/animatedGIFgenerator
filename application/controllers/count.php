<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Count extends CI_Controller {
	
	public function index() {
		$banner_dir = $this->config->item('banner_dir');
		$poster_dir = $this->config->item('poster_dir');
		$count_b1 = count(glob($banner_dir . "/1/*"));
		$count_b2 = count(glob($banner_dir . "/2/*"));
		$count_p = count(glob($poster_dir . "/*"));
		
		$tmp_dir = $this->config->item('tmp_dir');
		$count_tmp_b1 = count(glob($tmp_dir . "/1/*"));
		$count_tmp_b2 = count(glob($tmp_dir . "/2/*"));
		$count_tmp_p = count(glob($tmp_dir . "/*")) - 2;

		echo('{"count": {"b1": '.$count_b1.', "b2": '.$count_b2.', "p": '.$count_p.'}, "tmp": {"b1": '.$count_tmp_b1.', "b2": '.$count_tmp_b2.', "p": '.$count_tmp_p.'}}');
		
	}	
}

/* End of file count.php */
/* Location: ./application/controllers/poster.php */