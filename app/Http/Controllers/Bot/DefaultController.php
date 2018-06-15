<?php

namespace App\Http\Controllers\Bot;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram;
use App\Mail\Resume;
use Illuminate\Support\Facades\Mail;
use Goutte\Client;
use Illuminate\Support\Facades\Storage;
use Google\Cloud\Vision\VisionClient;
use Carbon\Carbon;

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
//       $this->extractTextFromImage();
//       die;
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
  
//   private $channel = 144068960;
  private $channel = '@phpdfvagas';
  
    public function sendChannelMessage(Request $request)
    {
      try {
        $vagasEnviadas = 'vagasEnviadas.txt';
        if (!Storage::exists($vagasEnviadas)) {
          Storage::put($vagasEnviadas, '');
        }
        
        $time = Storage::lastModified('lastTimeSent.txt');
        $diffTime = time() - $time;
        Log::info('lastTimeSent', [time() - $time]);
        $arrBody = $request->all();
//         $queryBody = $request->getContent();
//         parse_str($queryBody, $arrBody);
        
        //throw new \Exception('Deu ruim');
        if (!empty($arrBody)) {
          Log::info('BODY', [$arrBody]);
          foreach($arrBody as $threadId => $body) {
            if(Storage::exists($threadId)) {
              continue;
            } else {
              Storage::put($threadId, $threadId);
            }
            $subject = "@phpdfbot\r\n\r\n*".(isset($body['subject']) ? $body['subject'] : 'Vagas @phpdf')."*\r\n\r\n";
            if(isset($body['image']) && is_array($body['image'])) {
                foreach($body['image'] as $img) {
                  $url = $img;
                  if (filter_var($img, FILTER_VALIDATE_URL) === false) {
                      $filename = 'img/phpdfbot-'.time().".png";
                      $pngUrl = public_path().'/'.$filename;
                      $image = base64_decode($img);
                      Storage::disk('uploads')->put($filename, $image);
                      $url = Storage::disk('uploads')->url($filename);   
                  } else {
                      $image = file_get_contents($url);
                  }
                  $msg = '';
                  if (isset($body['message'])) {
  //                   $msg = strip_tags($body['message']);
  //                   $msg = str_replace(['*','_','`'], '', $msg);
  //                   $msg = str_split($msg, 200-strlen($subject));
  //                   $msg = reset($msg);
                  }

                  if ($image) {
                    $textFromImage = $this->extractTextFromImage($image);
                    if ($textFromImage && strlen(trim($textFromImage['text'])) > 0) {
                      $tPhoto = Telegram::sendPhoto([
                          'chat_id' => $this->channel,
                          'photo' => $url,
                          'caption' => $subject, //.$msg,
                          'parse_mode' => 'Markdown'
                      ]);
                      $textFromImage = $textFromImage['text'];
                      $textFromImage = str_replace(['*','_','`'], '', $textFromImage);
                      $tMsg = Telegram::sendMessage([
                          'reply_to_message_id' => $tPhoto->getMessageId(),
                          'chat_id' => $this->channel,
                          'text' => $textFromImage,
                      ]);
                      if(!isset($body['message'])) {
                        $body['message'] = $textFromImage;
                      } else {
                        $body['message'] .= $textFromImage;
                      }
                    }
                  }
                }
              }
            if(isset($body['message'])) {
              $message = $subject . $body['message'];
              $bodyArr = str_split($message, 4096);
              foreach($bodyArr as $bodyStr) {
                //$bodyStr = strip_tags($bodyStr);
                $bodyStr = trim($bodyStr, " \t\n\r\0\x0B-");
                $bodyStr = str_replace('##', '`', $bodyStr);
                $bodyStr = str_replace(['>>','--'], '', $bodyStr);
                $lines = explode(PHP_EOL, $bodyStr);
                foreach ($lines as $key => $line) {
                  $line = trim($line);
                  $first = substr($line, 0, 1);
                  $last = substr($line, -1);
                  $lines[$key] = $line;
                  //Log:info('EX', [$line, $first, $last]);
                  if ((in_array($first, ['*','_','`']) || in_array($last, ['*','_','`'])) && $first !== $last) {
                    $lines[$key] = str_replace(['*','_','`'], '', $lines[$key]);
                    $lines[$key] = $first . $lines[$key] . $first;
                  }
                }
                $bodyStr = implode(PHP_EOL, $lines);
                $bodyStr = strip_tags($bodyStr);
                $bodyStr = preg_replace("/(\r\n)+/", "\r\n", $bodyStr);

                $this->checkContentToSendMail($bodyStr);
                
                $sendMsg = [
                    'chat_id' => $this->channel,
                    'parse_mode' => 'Markdown',
                    'text' => $bodyStr,
                ];
                if (isset($tMsg)) {
                  $sendMsg['reply_to_message_id'] = $tMsg->getMessageId();
                  Log::info('PHOTO_SENT', [$tMsg->getMessageId()]);
                }
                Log::info('MSG_SEND', [$sendMsg]);

                try {
                  $tMs = Telegram::sendMessage($sendMsg);
                } catch (\Exception $ex) {
                  if ($ex instanceof \Telegram\Bot\Exceptions\TelegramResponseException && $ex->getCode() == 400) {
                    $bodyStr = str_replace(['*','_','`'], '', $bodyStr);
                    $sendMsg['text'] = $bodyStr;
                    unset($sendMsg['Markdown']);
                    $tMs = Telegram::sendMessage($sendMsg);
                  }
                  Log::info('EX', [$ex]);
                }
                $subj = explode('] ', $subject);
                $subj = end($subj);
                $subj = trim($subj);
                $subj = str_replace(['*','`'], '', $subj);
                Storage::append($vagasEnviadas, json_encode(['id' => $tMs->getMessageId(), 'subject' => $subj]));
                Log::info('MSG_SENT', [$tMs->getMessageId()]);
//                 Storage::put('lastSentMsg.txt', $tMs->getMessageId());
              }
            }
          }
        }
        return response()->json([
          'results' => 'ok'
        ]);
      } catch (\Exception $e) {
        Log::info('EX', [$e]);
        return response()->json([
          'results' => $e->getMessage()
        ], 500);
      }
    }
  
  public function notifyGroup() {
    try {
      $vagasEnviadas = 'vagasEnviadas.txt';
      $contents = Storage::get($vagasEnviadas);
      $contents = trim($contents);
      $contents = explode("\n", $contents);
      $vagas = [];
      foreach($contents as $content) {
        $content = json_decode($content, true);
        $vagas[] = [[
          'text' => $content['subject'],
          'url' => 'https://t.me/phpdfvagas/'.$content['id']
        ]];
      }
      Telegram::sendMessage([
        'parse_mode' => 'Markdown',
        'chat_id' => '@phpdf',
        'text' => "*[".date('Y-m-d H:i:s')."]*\r\nOpa! Temos novas vagas no canal! \r\nConfere lá: @phpdfvagas 😉",
        'reply_markup' => json_encode([
          'inline_keyboard' => $vagas
        ])
      ]);
      Storage::put($vagasEnviadas, '');
      return response()->json(['status'=>'success', 'results' => 'ok']);
    } catch (\Exception $ex) {
      throw $ex;
    }
  }
  
  public function crawler()
  {
    try {
      $body = [];
      $this->getComoequetala();
      $this->getQueroworkar();
     
      return response()->json(['status'=>'success', 'results' => 'ok']);
    } catch (\Exception $e) {
      Log::info('EX', [$e]);
      return response()->json([
        'results' => $e->getMessage()
      ]);
    }
  }
  
  public function sendFromCrawler($text)
  {
    $bodyArr = str_split($text, 4096);
        foreach($bodyArr as $bodyStr) {
          $bodyStr = trim($bodyStr, " \t\n\r\0\x0B-");
          Telegram::sendMessage([
              'parse_mode' => 'Markdown',
              //'parse_mode' => 'HTML',
              'chat_id' => '@phpdfvagas',
//               'chat_id' => '144068960',
              'text' => "@phpdfbot\r\n\r\n".$bodyStr
          ]);
        }
  }
  
  public function sendResume($email)
  {
    $email = is_array($email) ? reset($email) : $email;
    $client = new Client();
    $res = preg_match_all("/(castgroup|stefanini|engesoftware|indra|otimicar|montreal)/i",$email);
    if (!$res) {
      $crawler = $client->request('GET', env("RESUME_URL").$email);
    }
  }
  
  private function extractEmail($body)
  {
    $res = preg_match_all("/[a-z0-9]+[_a-z0-9\.-]*[a-z0-9]+@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})/i",$body,$matches);
    if ($res) {
        return array_unique($matches[0]);
    }
    else{
        return null;
    }
  }
  
  private function checkContentToSendMail($text)
  {
    $words = strtolower($text);
    $matches1 = preg_match_all("/(bras[íi]lia|distrito federal|df|bsb)/i",$words);
    $matches2 = preg_match_all("/(php|full[ -]*stack|arquiteto|(front|back)[ -]*end)/i",$words);
    $matches3 = preg_match_all("/(\.net|java(?!script)|(asp|dot)[ \.-]net|)/i",$words);
    Log::info('MATCHS', [
      'matches1' => $matches1,
      'matches2' => $matches2,
      'matches3' => !$matches2,
    ]);
    if (
//       $matches1
//         && $matches2
//         && !$matches3
//       (strpos($words, 'brasília') !== false || strpos($words, 'brasilia') !== false || strpos($words, 'distrito federal') !== false || strpos($words, 'df') !== false || strpos($words, 'bsb') !== false)
       (strpos($words, 'php') !== false || strpos($words, 'fullstack') !== false || strpos($words, 'full-stack') !== false || strpos($words, 'full stack') !== false || strpos($words, 'arquiteto') !== false || strpos($words, 'frontend') !== false || strpos($words, 'front-end') !== false || strpos($words, 'front end') !== false)
    ) {
      $emails = $this->extractEmail($words);
      if(count($emails) > 0) {
        Log::info('EMAILS', [$emails]);
        $this->sendResume($emails);
      }
    }
  }
  
  private function extractTextFromImage($imageResource)
  {
//     phpdfbot-1524852138.png
    $vision = new VisionClient([
        'keyFile' => json_decode(Storage::disk('uploads')->get('vision.json'), true),
        'projectId' => env("GOOGLE_PROJECT_ID")
    ]);
//     $imageResource = Storage::disk('uploads')->get('img/phpdfbot-1524852138.png');
    $image = $vision->image($imageResource, [
        'TEXT_DETECTION'
    ]);
    $annotation = $vision->annotate($image);
    Log::info('FULLTEXT', [$annotation->fullText()]);
    $text = $annotation->fullText();
    if ($text) {
      return $text->info();
    } else {
      return null;
    }
  }
  
  private function getComoequetala()
  {
    $client = new Client();
      $crawler = $client->request('GET', 'https://comoequetala.com.br/vagas-e-jobs');
      $crawler->filter('.uk-list.uk-list-line.uk-list-space > li')->each(function ($node) {
        $client = new Client();
        //$text = $node->filter('.uk-link')->text();
        if(preg_match_all('#(wordpress|desenvolvedor|developer|programador|php|front-end|back-end|sistemas|full stack|full-stack|frontend|backend|arquiteto|fullstack)#i', $node->text(), $matches)) { 
          $data = $node->filter('[itemprop="datePosted"]')->attr('content');
          $data = new \DateTime($data);
          $today = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
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
            $text = "```\r\n".
              "[ComoEQueTala]\r\n\r\n```".
              $text;
            
            $this->checkContentToSendMail($text);
            $this->sendFromCrawler($text);
          }      
        }
      });
  }
  
  private function getQueroworkar()
  {
    $client = new Client();
      $crawler = $client->request('GET', 'http://queroworkar.com.br/blog/');
      $crawler->filter('.jobs-post')->each(function ($node) {
        $client = new Client();
        $jobsPlace = $node->filter('.jobs-place')->text();
        if(preg_match_all('#(Em qualquer lugar)#i', $jobsPlace, $matches)) { 
          $data = $node->filter('.jobs-date')->text();
          $data = preg_replace("/(  )+/", " ", $data);
          $data = trim($data);
          $months = [
             'January' => 'Jan',
              'February' => 'Fev',
              'March' => 'Mar',
              'April' => 'abr',
              'May' => 'Maio',
              'June' => 'Jun',
              'July' => 'Jul',
              'August' => 'Ago',
              'November' => 'Nov',
              'September' => 'Set',
              'October' => 'Out',
              'December' => 'Dez'
          ];
          $data = str_ireplace($months, array_keys($months), $data);
          $data = strtolower($data);
          $data = explode(" ", $data);
          $data = [
            $data[1],
            $data[0].',',
            $data[2]
          ];
          $data = implode(" ", $data);
          $data = new \DateTime($data);
          $today = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
          if ($data->format('Ymd') === $today->format('Ymd')) {
            $link = $node->filter('a')->first()->attr('href');
            $crawler2 = $client->request('GET', $link);
            $h3 = $crawler2->filter('.section-content .title')->text();
//             $img = $crawler2->filter('.section-content img')->attr('src');
//             Log::info('IMG:', [$img]);
            $content = $crawler2->filter('.section-content')->html();
            $content = str_ireplace('(adsbygoogle = window.adsbygoogle || []).push({});', '', $content);
            $content = nl2br($content);
            $content = strip_tags($content);
            $content = preg_replace("/(\r\r|\t)+/", "", $content);
            $content = preg_replace("/(  |\r\r|\n\n)+/", "", $content);
            $content = preg_replace("/(\.J)+/", ".\nJ", $content);
            $content = trim($content);
            $content = explode('Descrição da Vaga', $content);
            $content = explode('Vaga expira', end($content));
            $content = reset($content);
            $text = "```\r\n".
              "[QueroWorkar]\r\n\r\n```".
              "*$h3*\r\n\r\n".
              "$content\r\n\r\n".
//               "$img\r\n\r\n".
              "$link";
           
            $this->checkContentToSendMail($text);
            $this->sendFromCrawler($text);
          }      
        }
      });
  }
}