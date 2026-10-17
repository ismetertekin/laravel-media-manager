<?php

namespace Yazilim360\MediaManager\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;
use Yazilim360\MediaManager\Support\VariantGenerator;
use Yazilim360\MediaManager\Support\VariantSpec;

class GenerateVariantsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /**
     * @param  list<array>  $specs  VariantSpec::toArray() rows
     * @param  array<string, array>  $crops
     */
    public function __construct(public Media $media, public array $specs, public array $crops = [])
    {
        if ($connection = config('media-manager.queue_connection')) {
            $this->onConnection($connection);
        }
    }

    public function handle(VariantGenerator $generator): void
    {
        $generator->generate($this->media, VariantSpec::parseMany($this->specs), $this->crops);
    }

    public function failed(Throwable $e): void
    {
        $this->media->setCustomProperty('variants_status', 'failed');
        $this->media->save();
    }
}
