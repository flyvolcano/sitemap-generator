<?php

namespace App\Commands;

use DateTimeInterface;
use FluidXml\FluidXml;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;
use LaravelZero\Framework\Commands\Command;
use Psr\Http\Message\UriInterface;
use Spatie\Sitemap\SitemapGenerator;

class GeneratorCommand extends Command
{
    const BLACKLIST_FILE = 'blacklist.map';

    private array $blackList;
    private array $tags;
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'generate
                            {url : The base URL of the website}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Generate sitemap for the given domain';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $url = $this->argument('url');
        if(empty($url)) {
            $this->error('The base URL is needed.');
            return false;
        }
        $url = filter_var($url, FILTER_SANITIZE_URL);

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->error('Not a valid URL. Valid URL should include scheme: ex: https://example.com');
            return false;
        }

        $this->blackList = $blackList = [];

        $this->task('Checking for `blacklist.map` file', function () use ($blackList) {
            if(Storage::exists(self::BLACKLIST_FILE)) {
                if(!empty($blackListFile = Storage::get(self::BLACKLIST_FILE))) {
                    foreach (explode(PHP_EOL, $blackListFile) as $blackListURL) {
                        $blackList[] = $blackListURL;
                    }
                    $this->blackList = collect($blackList)->filter()->values()->toArray();
                }
            }
            return true;
        });

        $this->task('Crawling links', function () use ($url) {
            $generator = SitemapGenerator::create($url)
                ->shouldCrawl(function (UriInterface $url) {
                    $fullURL = sprintf('%s://%s%s', $url->getScheme(), rtrim($url->getHost(), "/"), rtrim($url->getPath(), "/"));
                    if(!empty($url->getQuery())) {
                        $fullURL = sprintf('%s://%s%s?%s', $url->getScheme(), rtrim($url->getHost(), "/"), rtrim($url->getPath(), "/"), $url->getQuery());
                    }
                    $this->output->write('<info>.</info>');
                    return !in_array($fullURL, $this->blackList);
                })
                ->getSitemap();
            $this->tags = $generator->getTags();
            return true;
        }, 'Crawling... Please be patient if the website is huge.');


        $this->task('Generating sitemap.xml file', function () {
            $bar = $this->output->createProgressBar(count($this->tags));
            $bar->start();

            $xml = new FluidXml('urlset', []);
            $xml->setAttribute("xmlns", "http://www.sitemaps.org/schemas/sitemap/0.9");
            $xml->setAttribute("xmlns:xhtml", "http://www.w3.org/1999/xhtml");

            foreach ($this->tags as $tag) {
                try {
                    $xml->addChild('url', true)
                        ->addChild('loc', rtrim($tag->url, "/"))
                        ->addChild('lastmod', $tag->lastModificationDate->format(DateTimeInterface::ATOM))
                        ->addChild('changefreq', $tag->changeFrequency)
                        ->addChild('priority', number_format($tag->priority,1));
                } catch (\Exception $exception) {
                }
                $bar->advance();
            }
            Storage::put("sitemap.xml", $xml->xml());
            return true;
        });

        return true;
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
         $schedule->command(static::class)->weekly();
    }
}
