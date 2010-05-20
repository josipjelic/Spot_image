<?php 
defined('SYSPATH') or die('No direct script access.');
class Controller_Spot_Image extends Controller
{    
    
    public function action_index ($params)
    {
        if ($_GET)
        {
            $path = $params;
            $type = $_GET;
        }
        else 
        {
            list ($type, $path) = explode("/", $params);            
        }
        foreach (Kohana::config("spot_image.folders") as $folder)
        {            
            if (is_file($folder.$path))
            {
                $file = $folder.$path;
            }
        }

        if (Kohana::config("spot_image.cache"))
        {
            // Check for cached image
            $cache_filename = Spot_Image::get_cache_filename($file, $type);
            if (file_exists(Kohana::config("spot_image.cache_directory").$cache_filename))
            {
                Spot_Image::get_from_cache(Kohana::config("spot_image.cache_directory").$cache_filename);
            }
            else
            {
                Spot_Image::factory ($file, $type);
            }
        }
        else
        {
            Spot_Image::factory ($file, $type);
        }        
    }

} // End Welcome
