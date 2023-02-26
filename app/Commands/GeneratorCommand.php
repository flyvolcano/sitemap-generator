<?php

namespace App\Commands;

use DateTimeInterface;
use function Termwind\{render};
use FluidXml\FluidXml;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;
use LaravelZero\Framework\Commands\Command;
use Psr\Http\Message\UriInterface;
use Spatie\Sitemap\SitemapGenerator;

class GeneratorCommand extends Command
{
    const BLACKLIST_FILE = 'blacklist.map';
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
            return 0;
        }
        $url = filter_var($url, FILTER_SANITIZE_URL);

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->error('Not a valid URL. Valid URL should include scheme: ex: https://example.com');
            return 0;
        }

        $blackList = [];

        if(Storage::exists(self::BLACKLIST_FILE)) {
            if(!empty($blackListFile = Storage::get(self::BLACKLIST_FILE))) {
                foreach (explode(PHP_EOL, $blackListFile) as $blackListURL) {
                    $blackList[] = $blackListURL;
                }
                $blackList = collect($blackList)->filter()->values()->toArray();
            }
        }

        $generator = SitemapGenerator::create($url)
            ->shouldCrawl(function (UriInterface $url) use ($blackList) {
                $fullURL = sprintf('%s://%s%s', $url->getScheme(), rtrim($url->getHost(), "/"), rtrim($url->getPath(), "/"));
                if(!empty($url->getQuery())) {
                    $fullURL = sprintf('%s://%s%s?%s', $url->getScheme(), rtrim($url->getHost(), "/"), rtrim($url->getPath(), "/"), $url->getQuery());
                }
                return !in_array($fullURL, $blackList);
            })
            ->getSitemap();


        $tags = $generator->getTags();

        $bar = $this->output->createProgressBar(count($tags));
        $bar->start();

        $xml = new FluidXml('urlset', []);
        $xml->setAttribute("xmlns", "http://www.sitemaps.org/schemas/sitemap/0.9");
        $xml->setAttribute("xmlns:xhtml", "http://www.w3.org/1999/xhtml");

        foreach ($tags as $tag) {
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
        render(<<<'HTML'
            <div>
                <div class="px-1 bg-green-600">Sitemap Generator 🪄</div>
                <em class="ml-1">
                  Sitemap generated
                </em>
            </div>
        HTML);
        return 0;
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
