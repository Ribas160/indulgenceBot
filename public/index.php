<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Telegram\Bot\FileUpload\InputFile;


class IndulgenceBot
{

    /**
     * @var string
     */
    private const INDULGENCIA_MAIN_URL = 'https://vk.com/indulgencia';


    /**
     * @var string
     */
    private const INDULGENCIA_OTPUSTIT_URL = 'https://vk.com/@indulgencia-otpustit';


    /**
     * @var string
     */
    private const JSON_FILE = __DIR__ . '/../posts.json';


    /**
     * @var object
     */
    private $telegram;




    /**
     * IndulgenceBot constructor
     */
    public function __construct()
    {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->load();

        $this->telegram = new Telegram\Bot\Api($_ENV['BOT_TOKEN']);
    }


    /**
     * @return array
     */
    private function parseIndulgenciaMain(): array
    {
        $posts = [];

        $html = file_get_contents(self::INDULGENCIA_MAIN_URL);
        $pq = phpQuery::newDocument($html);
        $wi_body = $pq->find('.wi_body');

        foreach ($wi_body as $block) {
            $block = pq($block);

            if (count($block->find('.medias_audio')) > 0) continue;
            if (count($block->find('.mt_dur')) > 0) continue;

            $pi_text = $block->find('.pi_text');
            $pi_text = pq($pi_text);
            $pi_text_html = $pi_text->html();

            if ($pi_text_html) {
                if (!substr_count($pi_text_html, '<img') && substr_count($block->html(), 'class="thumb_map_img') < 2) {
                    $posts[] = preg_replace(['/<span.*?>/', '/<\/span>/', '/<a.*?<\/a>/'], '', $pi_text_html);
                }
            } else {
                $thumb_map_img = $block->find('.thumb_map_img');
                $style = $thumb_map_img->attr('style');

                preg_match('/background-image: url\((?<url>.*?)\)/', $style, $matches);

                $posts[] = $matches['url'];
            }
        }

        return $posts;
    }



    /**
     * @return string
     */
    private function parseIndulgenciaOtpustit(): string
    {
        $article = $url = '';
        $i = 1;
        $hasAticle = true;

        while ($hasAticle) {
            $url = ($i === 1) ? self::INDULGENCIA_OTPUSTIT_URL : self::INDULGENCIA_OTPUSTIT_URL . $i;

            $getArticle = function () use ($url): string
            {
                $html = file_get_contents($url);
                $pq = phpQuery::newDocument($html);
                $article = $pq->find('p.article_decoration_first');
                return $article->html();
            };
            
            ($getArticle() !== '') ? $article = $getArticle() : $hasAticle = false;

            $i++;
        }
        
        return $article;
    }



    /**
     * @return array
     */
    private function getPosts(): array
    {
        if (!file_exists(self::JSON_FILE)) return [];
        else return json_decode(file_get_contents(self::JSON_FILE), true);
    }



    /**
     * @return void
     */
    private function savePosts(array $posts): void
    {
        file_put_contents(self::JSON_FILE, json_encode($posts));
    }



    /**
     * @return void
     */
    private function publicPost(string $post): void
    {
        if (substr_count($post, 'http')) {
            $this->telegram->sendPhoto([
                'chat_id' => $_ENV['CHANNEL_ID'],
                'photo' => new InputFile($post),
            ]);
        } else {
            $this->telegram->sendMessage([
                'chat_id' => $_ENV['CHANNEL_ID'],
                'text' => str_replace('<br>', "\n", $post),
            ]);
        }
    }


    /**
     * @return void
     */
    public function run(): void
    {
        $indulgenciaPosts = $this->parseIndulgenciaMain();
        // $indulgenciaOtpustitPost = $this->parseIndulgenciaOtpustit();

        // $indulgenciaPosts[] = $indulgenciaOtpustitPost;
        $oldPosts = $this->getPosts();

        $newIndulgenciaPosts = array_diff($indulgenciaPosts, $oldPosts);

        if ($newIndulgenciaPosts) {
            $this->savePosts($indulgenciaPosts);

            foreach ($newIndulgenciaPosts as $newPost) {
                $this->publicPost($newPost);
            }
        }
    }

}


$bot = new IndulgenceBot();
$bot->run();