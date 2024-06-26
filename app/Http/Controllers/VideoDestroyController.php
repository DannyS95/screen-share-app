<?php

namespace App\Http\Controllers;

use App\Models\Video;

class VideoDestroyController extends Controller
{
    public function __invoke(Video $video)
    {
        $video->delete();

        return to_route('videos.capture');
    }
}
