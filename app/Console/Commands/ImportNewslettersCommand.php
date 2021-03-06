<?php

namespace App\Console\Commands;

use App\Jobs\SendTweetJob;
use App\Models\Newsletter;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Spatie\ResponseCache\Facades\ResponseCache;

class ImportNewslettersCommand extends Command
{
    protected $signature = 'blog:import-newsletter-archive';

    protected $description = 'Import the newsletter archive';

    public function handle()
    {
        $importedNewsletters = $this
            ->getFeedItems(config('services.sendy.archive_feed_url'))
            ->reject(function (array $newsletterProperties) {
                return Newsletter::where('title', $newsletterProperties['title'])->first();
            })
            ->map(function (array $newsletterProperties) {
                return Newsletter::create([
                    'title' => $newsletterProperties['title'],
                    'url' => $newsletterProperties['link'],
                    'description' => $newsletterProperties['description'],
                    'sent_at' => Carbon::create($newsletterProperties['pubDate']),
                ]);
            });

        if ($importedNewsletters->count()) {
            ResponseCache::clear();

            dispatch(new SendTweetJob($importedNewsletters->first()));
        }

        $this->comment("Imported {$importedNewsletters->count()} newsletter(s)");

        $this->info('All done!');
    }

    private function getFeedItems(string $feedUrl): Collection
    {
        $feedContent = file_get_contents($feedUrl);

        $xml = simplexml_load_string($feedContent, "SimpleXMLElement", LIBXML_NOCDATA);

        $json = json_encode($xml);

        $array = json_decode($json, true);

        return collect($array['channel']['item']);
    }
}
