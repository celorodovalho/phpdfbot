<?php

namespace App\Http\Controllers\Bot;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram;
use App\Mail\Resume;
use Illuminate\Support\Facades\Mail;
use Goutte\Client;

class DefaultController extends Controller
{
    public function show()
    {
      $cards = Card::where(['name' => 'elesh norn'])->all();
        $card = reset($cards);
      dump($card);
      //dump('https://magiccards.info/scans/en/'.$set.'/'.$multiverseid.'.jpg');
      
        return 'ok';
    }

    public function setWebhook()
    {
      $token = env("TELEGRAM_BOT_TOKEN");
        $response = Telegram::setWebhook(['url' => "https://marcelorodovalho.com.br/dsv/workspace/phpdfbot/public/index.php/$token/webhook"]);
        //$update = Telegram::commandsHandler(true);
        return $response;
    }

    public function removeWebhook()
    {
        $response = Telegram::removeWebhook();
        dump($response);
        return 'ok';
    }

    public function getUpdates()
    {
        $updates = Telegram::getUpdates();
      dump($updates);
        die;
    }

    public function getWebhookInfo()
    {
        Telegram::commandsHandler(true);
        $updates = Telegram::getWebhookInfo();
        dump($updates);
        die;
    }

    public function getMe()
    {
        $updates = Telegram::getMe();
        dump($updates);

        Telegram::sendMessage([
            'parse_mode' => 'Markdown',
            'chat_id' => '144068960',
            'text' => '*UPDATE:*' . "\r\n" .
                $updates->getId()
        ]);

//         Telegram::sendMessage([
//             'parse_mode' => 'Markdown',
//             'chat_id' => '-201366561',
//             'text' => '*UPDATE:*' . "\r\n" .
//                 $updates->getId()
//         ]);

        die;
    }

    public function sendMessage(Request $request)
    {
        $arrBody = $request->all();
        if(!count($arrBody)) {
          $arrBody = [1,2,3];
        }
        Log::info("Message: ", $arrBody);
        if (!empty($arrBody)) {
            Telegram::sendMessage([
                'parse_mode' => 'Markdown',
                //'chat_id' => '-201366561',
                'chat_id' => '144068960',
                'text' => implode("\r\n\r\n", $arrBody)
            ]);
        }


        die;
    }

    public function mail()
    {
      Mail::to('marcelo2208@gmail.com')->send(new Resume());
      die;
    }
  
    public function sendChannelMessage(Request $request)
    {
      $arrBody = $request->all();
      if (!empty($arrBody)) {
        foreach($arrBody as $body) {
          $bodyArr = str_split($body, 4096);
          foreach($bodyArr as $bodyStr) {
            $bodyStr = trim($bodyStr, " \t\n\r\0\x0B-");
            $bodyStr = str_replace('##', '`', $bodyStr);
            $lines = explode(PHP_EOL, $bodyStr);
            foreach ($lines as $key => $line) {
              $first = substr($line, 0, 1);
              $last = substr($line, -1);
              $lines[$key] = $line;
              if (in_array($first, ['*','_','`']) && $first !== $last) {
                $lines[$key] .= $first;
              }
            }
            $bodyStr = implode(PHP_EOL, $lines);
            $bodyStr = strip_tags($bodyStr);
            try {
              Telegram::sendMessage([
                  'parse_mode' => 'Markdown',
                  //'parse_mode' => 'HTML',
                  'chat_id' => '@phpdfvagas',
                  //'chat_id' => '144068960',
                  'text' => "@phpdfbot\r\n\r\n".$bodyStr
              ]);
            } catch (\Exception $ex) {
              if ($ex instanceof \Telegram\Bot\Exceptions\TelegramResponseException && $ex->getCode() == 400) {
                Telegram::sendMessage([
                    //'parse_mode' => 'HTML',
                    'chat_id' => '@phpdfvagas',
                    'text' => "@phpdfbot\r\n\r\n".strip_tags($bodyStr)
                ]);
              }
              Log:info('EX', [$ex]);           
            }
          }
        }
      }
      die;
    }
  
  public function crawler()
  {
    $client = new Client();
    $crawler = $client->request('GET', 'https://comoequetala.com.br/vagas-e-jobs');
    $crawler->filter('.uk-list.uk-list-line.uk-list-space > li')->each(function ($node) {
      $client = new Client();
      //$text = $node->filter('.uk-link')->text();
      if(preg_match_all('#(wordpress|desenvolvedor|developer|programador|php|front-end|back-end|sistemas|full stack|frontend|backend)#i', $node->text(), $matches)) { 
        $data = $node->filter('[itemprop="datePosted"]')->attr('content');
        $data = new \DateTime($data);
        $today = new \DateTime();
        //$interval = $data->diff($today);
        if ($data->format('Ymd') === $today->format('Ymd')) {
          $link = $node->filter('[itemprop="url"]')->attr('content');
          $crawler2 = $client->request('GET', $link);
          $h3 = $crawler2->filter('.uk-panel.uk-panel-box.uk-margin-large-bottom h3')->text();
          $p = $crawler2->filter('.uk-panel.uk-panel-box.uk-margin-large-bottom')->eq(1)->filter('p')->text();
          $text = "*".$node->filter('.uk-link')->text()."*\r\n\r\n";
          $text .= "*Empresa:* ".$node->filter('.vaga_empresa')->text()."\r\n\r\n";
          $text .= "*Local:* ".trim($node->filter('[itemprop="addressLocality"]')->text())."/"
            .trim($node->filter('[itemprop="addressRegion"]')->text())."\r\n\r\n";
          $text .= trim($node->filter('[itemprop="description"]')->text())."\r\n\r\n";
          $text .= $h3.":\r\n".$p;
          
          $bodyArr = str_split($text, 4096);
          foreach($bodyArr as $bodyStr) {
            $bodyStr = trim($bodyStr, " \t\n\r\0\x0B-");
            Telegram::sendMessage([
                'parse_mode' => 'Markdown',
                //'parse_mode' => 'HTML',
                'chat_id' => '@phpdfvagas',
                //'chat_id' => '144068960',
                'text' => "@phpdfbot\r\n\r\n".$bodyStr
            ]);
          }
        }      
      }
    });
    die;
    //
    
  }
}