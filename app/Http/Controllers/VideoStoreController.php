<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Support\Str;
use App\Jobs\ConvertVideoFormat;
use Illuminate\Http\UploadedFile;
use App\Http\Requests\VideoStoreRequest;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;
use Pion\Laravel\ChunkUpload\Handler\ContentRangeUploadHandler;

class VideoStoreController extends Controller
{
    public function __invoke(VideoStoreRequest $request)
    {
        $receiver = new FileReceiver(
            UploadedFile::fake()->createWithContent('file', $request->getContent()),
            $request,
            ContentRangeUploadHandler::class,
        );

        $save = $receiver->receive();

        if ($save->isFinished()) {
            return $this->storeAndAttachFile($save->getFile(), $request);
        }

        $save->handler();

        return redirect()->route('videos.index');
    }

    private function storeAndAttachFile(UploadedFile $file, VideoStoreRequest $request)
    {
        $video = $request->user()->videos()->create(
            $request->only('title', 'description') + [
                'video_path' => $file->storeAs('videos', Str::uuid(), 'public')
            ]
        );

        ConvertVideoFormat::dispatch($video);
    }
}
