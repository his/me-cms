<?php

/*
 * Plugin for the resizing of images.
 */

defined( '_JEXEC' ) or die( 'Restricted access' );

jimport( 'joomla.plugin.plugin' );
jimport('joomla.session.session');

class plgSystemMcsimagecalc extends JPlugin {

	function onAfterRender() {

		$this->cachepath = JPATH_SITE.DS."resizedimages";
		$imgsrcpattern = "@<img.*?src\s*=\s*['\"](.*?)['\"].*?>@i";

		// Nicht für den Adminbereich
		if ($this->isAdmin()) return;

		// Plugin-Parameter holen
		$plugin = &JPluginHelper::getPlugin('system', 'mcscontent');
		$pluginParams = new JParameter($plugin->params);

		$pluginParams->def('removeimages',0);
		$pluginParams->def('checkimageswithoutsize',1);
		$pluginParams->def('cssstopclass','');
		$pluginParams->def('urlregexstop','');
		$pluginParams->def('donthandleotherservers',1);
		$pluginParams->def('stripattributes', 1);

		$this->removeimages = $pluginParams->get('removeimages');
		$this->checkimageswithoutsize = $pluginParams->get('checkimageswithoutsize');
		$this->cssstopclass = $pluginParams->get('cssstopclass');
		$this->urlregexstop = $pluginParams->get('urlregexstop');
		$this->donthandleotherservers = $pluginParams->get('donthandleotherservers');
		$this->stripattributes = $pluginParams->get('stripattributes');

		$session = &JFactory::getSession();

		$currentWurflData = $session->get('mcs.wurfldata');

		if ($currentWurflData && $currentWurflData['product_info']['is_wireless_device']) {

			$content = &JResponse::getBody();

			if ($this->removeimages) {
				$content = preg_replace($imgsrcpattern, "", $content);
			} else {

				preg_match_all($imgsrcpattern, $content, $matches);
					
				foreach ($matches[0] as $animagetag) {

					$imagedata = $this->getImageData($animagetag);

					if ($imagedata['src'] && $this->shouldImageBeHandled($imagedata)) {
							
						$imgfilename = substr($imagedata['src'], strrchr($imagedata['src'], "/")); // No use of dirname() because it may be on another server
						$imgfoldername = substr($imagedata['src'],0, strlen($imagedata['src']-strlen($imgfilename)));

						$wurfldata = $session->get('mcs.wurfldata');
						$displayWidth = $wurfldata['display']['resolution_width'];
						$displayHeight = $wurfldata['display']['resolution_height'];

						if ($this->hasPercentualWidth($imagedata)) {
							$newwidth = floor($displayWidth * $imagedata['width']);
							$newimagetag = $this->createResizedImage($imagedata, $imgfilename, $imgfoldername, $newwidth);
							$content = str_replace($animagetag, $newimagetag, $content);
						} else if ($this->hasAbsoluteWidth($imagedata)) {

							// if imagewidth>screenwidth create image resized to screen width
							if ($imagedata['width']>$displayWidth) {
								$newimagetag = $this->createResizedImage($imagedata, $imgfilename, $imgfoldername, floor($displayWidth*0.95));
								$content = str_replace($animagetag, $newimagetag, $content);
							} else {
								$newimagetag = $this->createResizedImage($imagedata, $imgfilename, $imgfoldername, $imagedata['width'], $imagedata['height']);
								$content = str_replace($animagetag, $newimagetag, $content);
							}
							
						} else {

							if ($this->checkimageswithoutsize) {
								$newimagetag = $this->createResizedImageForImageWithoutWidth($imagedata, $imgfilename, $imgfoldername, $displayWidth);
								$content = str_replace($animagetag, $newimagetag, $content);
							}
						}
					}

				}
			}

			JResponse::setBody($content);
		}

	}

	/**
	 * Returns true if a version of the image resized to the specified width exists in the cache.
	 *
	 * @param $imgfilename
	 * @param $imagefoldername
	 * @param $imgwidth
	 * @return unknown_type
	 */
	function isResizedImageExists($imgfilename, $imagefoldername, $imgwidth) {
		return file_exists($this->getFullFilenameForResizedImage($imgfilename, $imagefoldername, $imgwidth));
	}

	/**
	 * Liefert den vollständigen Dateinamen eines auf die Breite umgerechneten Bildes.
	 *
	 * @param $imgfilename
	 * @param $imagefoldername
	 * @param $imgwidth
	 * @return string
	 */
	function getFullFilenameForResizedImage($imgfilename, $imagefoldername, $imgwidth) {
		$filenameparts = $this->getFilenameAndExt($imgfilename);

		return $this->cachepath.DS.$this->getFoldernameHash($imagefoldername).DS.$filenameparts[0]."_X_".$imgwidth.".png";
	}

	/**
	 * Liefert den vollständigen Dateinamen eines auf die Breite umgerechneten Bildes.
	 *
	 * @param $imgfilename
	 * @param $imagefoldername
	 * @param $imgwidth
	 * @return string
	 */
	function getFullFilenameForResizedImageWithDimensions($imgfilename, $imagefoldername, $imgwidth, $imgheight) {
		$filenameparts = $this->getFilenameAndExt($imgfilename);

		return $this->cachepath.DS.$this->getFoldernameHash($imagefoldername).DS.$filenameparts[0]."_X_".$imgwidth."_Y_".$imgheight.".png";
	}

	/**
	 * Returns the directory structure below the cache folder created for an image loaded from
	 * the given origin (foldername).
	 *
	 * The folder structure will be created if createFolder is true (default).
	 *
	 * Current implementation: md5, four folders created from the first three triplets of the hash plus one for the tail.
	 *
	 * @param $foldername
	 * @param $createFolder create Folder inside cache folder if not exists
	 * @return string
	 */
	function getFoldernameHash($foldername, $createFolder=true) {
		$folderHash = md5($foldername);

		$foldername = substr($folderHash, 0,3).DS.substr($folderHash,3,3).DS.substr($folderHash,6,3).DS.substr($folderHash,9);

		$fullFoldername = $this->cachepath .DS. $foldername;

		if (!file_exists($fullFoldername)) {
			mkdir($fullFoldername, 0700, true);
		}

		return $foldername;
	}

	/**
	 * Returns the base filename and the file extension in an array (bild.jpg => ['bild','.jpg']).
	 * File extension is an empty string if no file extension existed.
	 *
	 * @param $filename
	 * @return unknown_type
	 */
	function getFilenameAndExt($filename){
		$result = array();
		$base = basename($filename);

		$pos = strripos($base, '.');
		if($pos === false){
			$result[1] = '';
		} else {
			$result[1] = substr($base, $pos);
		}

		$result[0] = basename($filename, $result[1]);

		return $result;
	}


	/**
	 * Creates the resized image, saves it in the designated cache position and returns an
	 * image tag with only the needed attributes or all attributes, depending on the corresponding parameter.
	 *
	 * @param $imagedata
	 * @param $imgfilename
	 * @param $imgfoldername
	 * @param $newwidth
	 * @return unknown_type
	 */
	function createResizedImage($imagedata, $imgfilename, $imgfoldername, $newwidth) {
		// Load image
		$filenameparts = $this->getFilenameAndExt($imgfilename);

		if (substr($imgfilename, 5)!='http:' && substr($imgfilename, 6)!='https:') {
			if(strpos($imgfilename,"/") === 0 || strpos($imgfilename,"\\") === 0 ){
				$imgfilename = $_SERVER['DOCUMENT_ROOT'].$imgfilename;
			}else{
				$imgfilename = $_SERVER['DOCUMENT_ROOT'].DS.$imgfilename;
			}
			
		}

		$image = $this->loadImage($imgfilename);


		// Get factor
		$oldwidth = imagesx($image);
		$oldheight = imagesy($image);
		$factor = $oldwidth / $newwidth;
		$newheight = floor($oldheight/$factor);

		// Get save filename (will create folder)
		$newfilename = $this->getFullFilenameForResizedImage($imgfilename, $imgfoldername, $newwidth);

		if ($oldwidth == $newwidth) {
			copy($imgfilename, $newfilename);
		} else {
			if (!file_exists($newfilename)) {
				// Create resized image
				$resizedimage = imagecreatetruecolor($newwidth, $newheight);
				imagecopyresampled($resizedimage, $image, 0,0,0,0, $newwidth, $newheight, $oldwidth, $oldheight);
				imagepng($resizedimage, $newfilename);
			}
		}
		// Free memory
		imagedestroy($image);

		$imagedata['width'] = $newwidth;
		$imagedata['height'] = $newheight;

		// Create img tag
		$imgtag = $this->createImageTag($newfilename, $imagedata);

		return $imgtag;
	}

	/**
	 * Creates the resized image, saves it in the designated cache position and returns an
	 * image tag with only the needed attributes or all attributes, depending on the corresponding parameter.
	 *
	 * @param $imagedata
	 * @param $imgfilename
	 * @param $imgfoldername
	 * @param $newwidth
	 * @param $newheight
	 * @return unknown_type
	 */
	function createResizedImageWithDimensions($imagedata, $imgfilename, $imgfoldername, $newwidth, $newheight) {
		// Load image
		$filenameparts = $this->getFilenameAndExt($imgfilename);

		if (substr($imgfilename, 5)!='http:' && substr($imgfilename, 6)!='https:') {
			if(strpos($imgfilename,"/") === 0 || strpos($imgfilename,"\\") === 0 ){
				$imgfilename = $_SERVER['DOCUMENT_ROOT'].$imgfilename;
			}else{
				$imgfilename = $_SERVER['DOCUMENT_ROOT'].DS.$imgfilename;
			}
		}

		$image = $this->loadImage($imgfilename);

		// Get save filename (will create folder)
		$newfilename = $this->getFullFilenameForResizedImageWithDimensions($imgfilename, $imgfoldername, $newwidth);

		if (!file_exists($newfilename)) {
			// Create resized image
			$resizedimage = imagecreatetruecolor($newwidth, $newheight);
			imagecopyresampled($resizedimage, $image, 0,0,0,0, $newwidth, $newheight, $oldwidth, $oldheight);
			imagepng($resizedimage, $newfilename);
		}

		// Free memory
		imagedestroy($image);

		$imagedata['width'] = $newwidth;
		$imagedata['height'] = $newheight;

		// Create img tag
		$imgtag = $this->createImageTag($newfilename, $imagedata);

		return $imgtag;
	}

	/**
	 * Creates the resized image, saves it in the designated cache position and returns an
	 * image tag with only the needed attributes or all attributes, depending on the corresponding parameter.
	 *
	 * @param $imagedata
	 * @param $imgfilename
	 * @param $imgfoldername
	 * @param $newwidth
	 * @return unknown_type
	 */
	function createResizedImageForImageWithoutWidth($imagedata, $imgfilename, $imgfoldername, $displaywidth) {
		// Load image
		$filenameparts = $this->getFilenameAndExt($imgfilename);

		if (substr($imgfilename, 5)!='http:' && substr($imgfilename, 6)!='https:') {
			if(strpos($imgfilename,"/") === 0 || strpos($imgfilename,"\\") === 0 ){
				$imgfilename = $_SERVER['DOCUMENT_ROOT'].$imgfilename;
			}else{
				$imgfilename = $_SERVER['DOCUMENT_ROOT'].DS.$imgfilename;
			}
		}

		$image = $this->loadImage($imgfilename);

		if ($image) {
			// Get factor
			$oldwidth = imagesx($image);
			$oldheight = imagesy($image);

			if ($oldwidth>$displaywidth) {
				$newwidth = floor(0.95*$displaywidth);
				$factor = $oldwidth / $newwidth;
			} else {
				$newwidth = $oldwidth;
				$factor = 1;
			}

			$newheight = floor($oldheight/$factor);

			// Get save filename (will create folder)
			$newfilename = $this->getFullFilenameForResizedImage($imgfilename, $imgfoldername, $newwidth);

			if ($oldwidth==$newwidth) {
				copy($imgfilename, $newfilename);
			} else {

				if (!file_exists($newfilename)) {
					// Create resized image
					$resizedimage = imagecreatetruecolor($newwidth, $newheight);
					imagecopyresampled($resizedimage, $image, 0,0,0,0, $newwidth, $newheight, $oldwidth, $oldheight);
					imagepng($resizedimage, $newfilename);
				}
			}

			// Free memory
			imagedestroy($image);

			$imagedata['width'] = $newwidth;
			$imagedata['height'] = $newheight;

			// Create img tag
			$imgtag = $this->createImageTag($newfilename, $imagedata);

		}
		return $imgtag;
	}

	function createImageTag($imgfilename, $imagedata) {
		$width = $imagedata['width'];
		$height = $imagedata['height'];

		$imgfilename = substr($imgfilename, strlen($_SERVER['DOCUMENT_ROOT']));
		
		$imgfilename = str_replace("\\", "/", $imgfilename);


		$imgtag = "<img src=\"".$imgfilename."\" width=\"".$width."\" height=\"".$height."\" ";

		// Alt (required)
		if ($imagedata['alt']) {
			$imgtag = $imgtag." alt=\"".$imagedata['alt']."\" ";
		} else {
			// It is not a good idea to put in anything here. Especially when using decorating images an alt text like the filename will be a problem for people using screenreaders.
			$imgtag = $imgtag." alt=\"\" ";
		}

		// Add all attributes
		if (!$this->stripattributes) {
			foreach ($imagedata as $key => $value) {
				// except for already set attributes
				if (!strcmp($key, 'alt') && !strcmp($key, 'width') && !strcmp($key, 'height')) {
					$imgtag = $imgtag . " ".$key."=\"".$value."\" ";
				}
			}
		}

		$imgtag = $imgtag." />";
		return $imgtag;
	}

	/**
	 * Loads an image. Returns the image resource.
	 *
	 * @param $imgfilename
	 * @return unknown_type
	 */
	function loadImage($imgfilename) {


		$filenameparts = $this->getFilenameAndExt($imgfilename);
		$result = null;

		if (strcmp($filenameparts[1],".png")==0) {
			$result = @imagecreatefrompng($imgfilename);
		}
		if (strcmp($filenameparts[1],".jpg")==0 || strcmp($filenameparts[1],".jpeg")==0) {
			$result = @imagecreatefromjpeg($imgfilename);
		}
		if (strcmp($filenameparts[1],".gif")==0) {
			$result = @imagecreatefromgif($imgfilename);
		}
		return $result;
	}

	/**
	 * Returns true if the imagedata contains percentual width.
	 *
	 * @param $imagedata
	 * @return unknown_type
	 */
	function hasPercentualWidth($imagedata) {
		return (isset($imagedata['width']) && strpos($imagedata['width'], "%"));
	}

	/**
	 * Returns true if the imagedata contains absolute width.
	 *
	 * @param $imagedata
	 * @return unknown_type
	 */
	function hasAbsoluteWidth($imagedata) {
		$result = false;

		if (isset($imagedata['width'])) {
			$result = (false===strpos($imagedata['width'], "%"));
		}

		return $result;
	}

	/**
	 * Returns true if the image should be handled by the recalculations, false otherwise.
	 * @param $imagedata
	 * @return boolean
	 */
	function shouldImageBeHandled($imagedata) {

		if (isset($imagedata['class']) && $this->cssstopclass && preg_match($this->cssstopclass, $imagedata['class'])) {
			return false;
		}

		if (isset($imagedata['src']) && $this->urlregexstop && preg_match($this->urlregeexstop, $imagedata['src'])) {
			return false;
		}

		if (isset($imagedata['src']) && $this->isOnOtherServer($imagedata['src'])) {
			return $this->donthandleotherservers;
		}

		return true;
	}

	/**
	 * Returns true if the image url points to an image on another server.
	 * @param $srcurl
	 * @return unknown_type
	 */
	function isOnOtherServer($srcurl) {
		if (substr($srcurl, 5)!='http:' && substr($srcurl, 6)!='https:') {
			return false;
		} else {
			$servername = $_SERVER['HTTP_HOST'];

			if (substr($srcurl, 0, strlen($servername))===$servername) {
				return false;
			} else {
				return true;
			}

		}

		return false;
	}

	/**
	 * Returns an array of image attributes
	 * @return array
	 */
	function getImageData($imagetag) {
		$result = array();

		// Regex to find attributes within the img tag
		$attrre = "@(\w+)=\"([^\"]*)\"@i";

		preg_match_all($attrre, $imagetag, $attrmatches);

		foreach (array_keys($attrmatches[0]) as $aKey) {
			$result[strtolower($attrmatches[1][$aKey])] = $attrmatches[2][$aKey];
		}

		return $result;
	}

	function isAdmin() {
		global $mainframe;
		return $mainframe->isAdmin();
	}

}


?>