<?php defined('SYSPATH') or die('No direct script access.');
class Spot_Image {

    private $file;
    private $image_type;
    private $image_params;

    // Resizing contraints
    const NONE    = 0x01;
    const WIDTH   = 0x02;
    const HEIGHT  = 0x03;
    const AUTO    = 0x04;
    const INVERSE = 0x05;

    /**
	 * Factory method
	 *
	 * @param string $file Filename to be resized
	 * @param string $type Image type to be applied
	 * @return SpotResize instance
	 */
    public static function factory($file, $image_type)
    {
        return new Spot_Image($file, $image_type);
    }

    /**
     * Returns fully formed img HTML tag
     *
     * @param string $file
     * @param string $type
     * @param string $default
     * @param array $attributes img attributes
     * @return unknown
     */
    public static function img ($file, $type, $default = NULL, $attributes = array())
    {
        $filepath = Spot_Image::find_file($file);

        if (is_file($filepath))
        {
            if (is_string($type))
            {
                return HTML::image(Kohana::config("spot_image.prefix")."/".$type."/".$file, $attributes);
            }
            elseif (is_array($type))
            {
                $type = URL::query($type);
                return HTML::image(Kohana::config("spot_image.prefix")."/".$file."/".$type, $attributes);
            }
        }
        else
        {
            return HTML::image($default, $attributes);
        }

        return FALSE;
    }
    
    /**
     * Returns src part of img HTML tag // Usefull with lightboxes and inline stlyes for background images
     *
     * @param string $file
     * @param string $type
     * @param string $default
     * @param array $attributes img attributes
     * @return unknown
     */
    public static function img_src ($file, $type, $default = NULL)
    {
        $filepath = Spot_Image::find_file($file);

        if (is_file($filepath))
        {
            if (is_string($type))
            {
                return URL::base().Kohana::config("spot_image.prefix")."/".$type."/".$file;
            }
            elseif (is_array($type))
            {
                $type = URL::query($type);
                return URL::base().Kohana::config("spot_image.prefix")."/".$file."/".$type;
            }
        }
        else
        {
            return $default;
        }

        return FALSE;
    }

    
    /**
     * Searches for image in defined folders and returns Kohana_Image 
     *
     * @param string $file
     * @return Kohana_Image
     */
    public static function image ($file)
    {
        $filepath = Spot_Image::find_file($file);

        if ($filepath)
        {
            return Image::factory($filepath);
        }

        return FALSE;
    }

    public static function find_file ($path)
    {
        foreach (Kohana::config("spot_image.folders") as $folder)
        {
            if (is_file($folder.$path))
            {
                return ($folder.$path);
            }
        }
    }

    /**
	 * Constructor method
	 *
	 * @param string $file Filename to be resized
	 * @param string $type Image type to be applied
	 * @return unknown
	 */
    public function __construct($file, $image_type)
    {
        $this->file = $file;
        $this->image_type = $image_type;

        if (is_array($image_type))
        {
            $this->image_params = $image_type;
        }
        else
        {
            if (Kohana::config("spot_image.use_database"))
            {
                $this->image_params = DB::select("*")
                ->from(Kohana::config("spot_image.image_sizes_table"))
                ->where("name", "=", $image_type)
                ->as_object()
                ->execute(Kohana::config("spot_image.image_sizes_table_db"))
                ->current();
            }
        }

        if (!$this->image_params)
        {
            $image_params = Kohana::config("spot_image.image_types");
            $this->image_params = $image_params[$image_type];
        }

        if (!$this->image_params)
        {
            throw new Kohana_Exception('image_type definition not found');
        }

        $this->run();
    }

    /**
	 * Returns filename in cache directory
	 *
	 * @param string $file Original filename
	 * @param string $type Image type
	 * @return Cached filename
	 */
    public static function get_cache_filename ($file, $image_type)
    {
        $file_size = filesize($file);
        $file_date = filemtime($file);

        if (is_array($image_type))
        {
            $image_params = $image_type;
            $image_type = "by_parameters";
        }
        else
        {
            $image_params = DB::select("*")
            ->from(Kohana::config("spot_image.image_sizes_table"))
            ->where("name", "=", $image_type)
            ->as_object()
            ->execute(Kohana::config("spot_image.image_sizes_table_db"))
            ->current();
        }

        $path_parts = pathinfo($file);

        $filename = $path_parts['filename'].'_'
        .$image_type.'_'
        .md5($file_size.$file_date.$image_type.serialize($image_params).$path_parts['dirname']).'.'
        .$path_parts['extension'];

        return $filename;
    }

    /**
	 * Renders image from cache with proper headers
	 *
	 * @param string $file Filename to be dumped
	 */
    public static function get_from_cache ($file)
    {
        if (Spot_Expires::check(Kohana::config("spot_image.browser_cache_expire")) === FALSE)
        {
            Spot_Expires::set(Kohana::config("spot_image.browser_cache_expire"));
        }

        $image = Image::factory($file);

        Request::instance()->headers['Content-Type'] = File::mime_by_ext(pathinfo($file, PATHINFO_EXTENSION));
        Request::instance()->response = $image;
    }

    /**
	 * Function that applies image_type
	 *
	 */
    private function run () {

        $image = Image::factory($this->file);

        $image_params = (object) $this->image_params;

        // If width OR height is specified
        if (isset($image_params->width) OR isset($image_params->height))
        {
            $width = isset($image_params->width) ? $image_params->width : NULL;
            $height = isset($image_params->height) ? $image_params->height : NULL;

            if (isset($image_params->constraint))
            {
                switch ($image_params->constraint)
                {
                    case "none":
                        $constraint = Image::NONE;
                        break;
                    case "minimal":
                        if ($image->width / $image_params->width < $image->height / $image_params->height)
                        {
                            $constraint = Image::WIDTH;
                        }
                        else
                        {
                            $constraint = Image::HEIGHT;
                        }
                        break;
                    case "width":
                        $constraint = Image::WIDTH;
                        break;
                    case "height":
                        $constraint = Image::HEIGHT;
                        break;
                    case "auto":
                        $constraint = Image::AUTO;
                        break;
                    case "inverse":
                        $constraint = Image::INVERSE;
                        break;
                    default:
                        $constraint = Image::AUTO;
                        break;
                }
            }
            else
            {
                $constraint = Image::AUTO;
            }

            if (($width < $image->width OR $height < $image->height) OR Kohana::config("spot_image.allow_enlarge"))
            {
                $image->resize($width, $height, $constraint);
            }
        }

        // If crop_width OR crop_height is specified
        if (isset($image_params->crop_width) || isset($image_params->crop_height)) {

            $crop_width = isset($image_params->crop_width) ? $image_params->crop_width : NULL;
            $crop_height = isset($image_params->crop_height) ? $image_params->crop_height : NULL;
            $crop_x = isset($image_params->crop_x) ? $image_params->crop_x : NULL;
            $crop_y = isset($image_params->crop_y) ? $image_params->crop_y : NULL;

            $image->crop($crop_width, $crop_height, $crop_x, $crop_y);
        }

        // If rotate is specified
        if ($image_params->rotate)
        {
            $image->rotate($image_params->rotate);
        }

        // If flip is specified
        if (isset($image_params->flip))
        {
            if ($image_params->flip == "horizontal") 	$direction = Image::HORIZONTAL;
            if ($image_params->flip == "vertical") 		$direction = Image::VERTICAL;
            $image->flip($image_params->flip, $direction);
        }

        // If sharpen is specified
        if (isset($image_params->sharpen))
        {
            $image->sharpen($image_params->sharpen);
        }

        $quality = isset($image_params->quality) ? $image_params->quality : 100;

        //create cache_filename
        $cache_filename = Spot_Image::get_cache_filename($this->file, $this->image_type);

        // Clear cache folder
        Spot_Image::clear_cache_folder();

        // Save image to cache
        $image->save(Kohana::config("spot_image.cache_directory").$cache_filename, $quality);

        // If the URL is expired set new expiration and render
        if (Spot_Expires::check(Kohana::config("spot_image.browser_cache_expire")) === FALSE)
        {
            Spot_Expires::set(Kohana::config("spot_image.browser_cache_expire"));
        }

        /*echo Kohana::debug($image_params);
        echo Kohana::debug($image);
        die();*/

        // Render image
        Request::instance()->headers['Content-Type'] = File::mime_by_ext(pathinfo($this->file, PATHINFO_EXTENSION));
        Request::instance()->response = $image->render(NULL, $quality);
    }

    /**
	 * Cache folder cleaner
	 *
	 */
    public static function clear_cache_folder () {
        $cache_maxage = Kohana::config("spot_image.cache_maxage");
        $cache_maxsize = Kohana::config("spot_image.cache_maxsize");
        $cache_maxfiles = Kohana::config("spot_image.cache_maxfiles");
        $cache_directory = Kohana::config("spot_image.cache_directory");

        $directory_handle = dir($cache_directory);

        $filesize_total = 0;
        $files_num      = 0;

        while (false !== ($entry = $directory_handle->read()))
        {
            if (($entry == ".") OR ($entry == "..")) continue;
            $files_age[$entry]  = @fileatime($cache_directory.$entry);
            $current_file_size  = @filesize($cache_directory.$entry);
            $filesize_total     +=  $current_file_size;
            $files_num ++;
        }

        // If folder is empty exit function
        if (!isset($files_age)) return;

        // Sort array of files by age
        array_multisort($files_age);

        $files_age_sorted = array_keys($files_age);

        // Maintain cache folder size
        if ($cache_maxsize != NULL)
        {
            while ($filesize_total > $cache_maxsize)
            {
                $file_to_delete = array_shift($files_age_sorted);
                $filesize_total -= @filesize($cache_directory.$file_to_delete);
                $files_num --;
                @unlink($cache_directory.$file_to_delete);
            }
        }

        // Mantain maximum number of files
        if ($cache_maxfiles != NULL)
        {
            while ($files_num > $cache_maxfiles)
            {
                $file_to_delete = array_shift($files_age_sorted);
                $files_num --;
                @unlink($cache_directory.$file_to_delete);
            }
        }

        // Maintain maximum cache age
        if ($cache_maxage != NULL)
        {
            foreach ($files_age as $filename => $age)
            {
                if ($age + $cache_maxage < time())
                {
                    @unlink($cache_directory.$filename);
                }
            }
        }
        $directory_handle->close();
    }
}